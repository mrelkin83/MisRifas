<?php
/**
 * Ticket Repository
 * Maneja operaciones de boletos con control de concurrencia
 */

require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../services/TicketStateMachine.php';

class TicketRepository extends BaseRepository
{
    protected $table = 'tickets';

    /**
     * Reservar boleto con control de concurrencia (CRÍTICO).
     * Lock pesimista + transición vía TicketStateMachine (bitácora incluida).
     * $ttlMinutes null = setting reservation_ttl_minutes (default 45).
     */
    public function reserveTicket(int $raffleId, string $ticketNumber, int $userId, ?int $ttlMinutes = null, string $source = 'web'): ?array
    {
        try {
            $this->beginTransaction();

            $ticket = TicketStateMachine::lockByNumber($this->db, $raffleId, $ticketNumber);
            if ($ticket['status'] !== TICKET_STATUS_AVAILABLE) {
                $this->rollback();
                return null; // Boleto no disponible
            }

            $ttl = $ttlMinutes ?? TicketStateMachine::reservationTtlMinutes($this->db);
            $reservedUntil = date('Y-m-d H:i:s', strtotime("+{$ttl} minutes"));

            TicketStateMachine::apply($this->db, $ticket, TICKET_STATUS_RESERVED, [
                'actor' => 'buyer', 'source' => $source, 'actor_id' => $userId,
                'fields' => [
                    'user_id' => $userId,
                    'reserved_at' => date('Y-m-d H:i:s'),
                    'reserved_until' => $reservedUntil,
                ],
            ]);

            $this->commit();

            // Obtener boleto actualizado
            return $this->findById($ticket['id']);

        } catch (TicketNotFound $e) {
            $this->rollback();
            return null;
        } catch (Exception $e) {
            $this->rollback();
            Logger::exception($e);
            throw $e;
        }
    }

    /**
     * Marcar boleto como pagado (vía máquina de estados).
     * Acepta reserved (dato legado, normalizado a pending_review) o
     * pending_review.
     */
    public function markAsPaid(int $ticketId, int $paymentId, string $source = 'dashboard', ?int $actorId = null): bool
    {
        try {
            $this->beginTransaction();

            $stmt = $this->db->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
            $stmt->execute([$ticketId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['status'], [TICKET_STATUS_RESERVED, 'pending_review'], true)) {
                $this->rollback();
                return false;
            }
            if ($row['status'] === TICKET_STATUS_RESERVED) {
                $row = TicketStateMachine::apply($this->db, $row, 'pending_review', [
                    'actor' => 'system', 'source' => $source,
                    'reason' => 'normalización previa a confirmación',
                ]);
            }
            TicketStateMachine::apply($this->db, $row, TICKET_STATUS_PAID, [
                'actor' => 'vendor', 'source' => $source, 'actor_id' => $actorId,
                'reason' => 'pago confirmado',
                'fields' => ['paid_at' => date('Y-m-d H:i:s'), 'payment_id' => $paymentId],
            ]);

            $this->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            Logger::exception($e);
            return false;
        }
    }

    /**
     * Liberar boletos con reserva vencida (cron). Uno por uno, vía máquina de
     * estados: cada liberación queda en la bitácora. pending_review NO se
     * libera aquí (tiene su propio TTL, ver releaseExpiredReviews).
     */
    public function releaseExpiredReservations(): int
    {
        try {
            $ids = $this->db->query(
                "SELECT id FROM tickets WHERE status = 'reserved' AND reserved_until < NOW() ORDER BY id"
            )->fetchAll(PDO::FETCH_COLUMN);

            $released = 0;
            foreach ($ids as $id) {
                try {
                    TicketStateMachine::transition($this->db, (int)$id, 'available', [
                        'actor' => 'system', 'source' => 'cron', 'reason' => 'reserva vencida (TTL)',
                    ]);
                    $released++;
                } catch (InvalidTransition $e) {
                    // Cambió de estado entre el SELECT y el lock (p. ej. subió
                    // comprobante): no es un error, simplemente ya no aplica.
                }
            }
            return $released;

        } catch (Exception $e) {
            Logger::exception($e);
            return 0;
        }
    }

    /**
     * Liberar boletos en pending_review sin decisión del vendedor tras el TTL
     * de revisión (setting pending_review_ttl_hours, default 12) — §7.4.
     */
    public function releaseExpiredReviews(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'pending_review_ttl_hours'");
            $stmt->execute();
            $hours = (int)$stmt->fetchColumn();
            if ($hours <= 0) {
                $hours = 12;
            }

            // El "reloj" de la revisión es el último evento pending_review.
            $ids = $this->db->prepare(
                "SELECT t.id
                   FROM tickets t
                   JOIN (SELECT ticket_id, MAX(created_at) AS at
                           FROM ticket_events
                          WHERE to_status = 'pending_review'
                          GROUP BY ticket_id) ev ON ev.ticket_id = t.id
                  WHERE t.status = 'pending_review'
                    AND ev.at < DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
                  ORDER BY t.id"
            );
            $ids->execute();

            $released = 0;
            foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
                try {
                    TicketStateMachine::transition($this->db, (int)$id, 'available', [
                        'actor' => 'system', 'source' => 'cron',
                        'reason' => 'comprobante sin respuesta del vendedor (TTL revisión)',
                    ]);
                    $released++;
                } catch (InvalidTransition $e) {
                    // El vendedor decidió justo entre el SELECT y el lock.
                }
            }
            return $released;

        } catch (Exception $e) {
            Logger::exception($e);
            return 0;
        }
    }

    /**
     * Obtener boletos disponibles de una rifa
     */
    public function getAvailableTickets(int $raffleId, int $limit = null): array
    {
        $sql = "SELECT * FROM tickets
                WHERE raffle_id = ? AND status = ?
                ORDER BY ticket_number ASC";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raffleId, TICKET_STATUS_AVAILABLE]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener estadísticas de boletos por rifa
     */
    public function getTicketStats(int $raffleId): array
    {
        $sql = "SELECT
                    COUNT(*) as total_tickets,
                    COUNT(CASE WHEN status = ? THEN 1 END) as available,
                    COUNT(CASE WHEN status = ? THEN 1 END) as reserved,
                    COUNT(CASE WHEN status = ? THEN 1 END) as paid,
                    ROUND((COUNT(CASE WHEN status = ? THEN 1 END) / COUNT(*)) * 100, 2) as sold_percentage
                FROM tickets
                WHERE raffle_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            TICKET_STATUS_AVAILABLE,
            TICKET_STATUS_RESERVED,
            TICKET_STATUS_PAID,
            TICKET_STATUS_PAID,
            $raffleId
        ]);

        return $stmt->fetch();
    }

    /**
     * Generar boletos para una rifa
     */
    public function generateTickets(int $raffleId, int $totalTickets, int $digits, int $opportunities): bool
    {
        try {
            $maxNumbers = pow(10, $digits);
            
            // Calcular cuántos boletos generar según oportunidades
            // 1 oportunidad = maxNumbers tickets
            // 2 oportunidades = maxNumbers/2 tickets
            // 4 oportunidades = maxNumbers/4 tickets
            // 5 oportunidades = maxNumbers/5 tickets
            $ticketsToGenerate = (int)floor($maxNumbers / $opportunities);
            
            // Generar todos los números posibles y mezclarlos
            $allNumbers = range(0, $maxNumbers - 1);
            shuffle($allNumbers);
            
            // Tomar los números necesarios para los tickets
            $selectedNumbers = array_slice($allNumbers, 0, $ticketsToGenerate * $opportunities);
            
            // Insertar boletos en lotes
            $batchSize = 500;
            $totalBatches = (int)ceil($ticketsToGenerate / $batchSize);

            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $start = $batch * $batchSize;
                $end = min($start + $batchSize, $ticketsToGenerate);
                $values = [];
                $placeholders = [];

                for ($i = $start; $i < $end; $i++) {
                    // Número de boleto secuencial
                    $ticketNumber = str_pad((string)($i + 1), $digits, '0', STR_PAD_LEFT);
                    
                    // Tomar los siguientes números random del pool
                    $startIndex = $i * $opportunities;
                    $opportunityNumbers = [];
                    for ($j = 0; $j < $opportunities; $j++) {
                        $opportunityNumbers[] = str_pad((string)$selectedNumbers[$startIndex + $j], $digits, '0', STR_PAD_LEFT);
                    }
                    sort($opportunityNumbers);
                    $opportunitiesJSON = json_encode($opportunityNumbers);

                    $placeholders[] = "(?, ?, ?, ?)";
                    $values[] = $raffleId;
                    $values[] = $ticketNumber;
                    $values[] = $opportunitiesJSON;
                    $values[] = TICKET_STATUS_AVAILABLE;
                }

                $sql = "INSERT INTO tickets (raffle_id, ticket_number, opportunities, status)
                        VALUES " . implode(', ', $placeholders);

                $stmt = $this->db->prepare($sql);
                $stmt->execute($values);
            }

            return true;

        } catch (Exception $e) {
            Logger::exception($e);
            return false;
        }
    }

    /**
     * Inserta tickets por lote (usando transacciones y multiples VALUES para velocidad)
     */
    public function batchInsertTickets(array $ticketsData): void
    {
        if (empty($ticketsData)) return;

        try {
            $this->beginTransaction();

            $batchSize = 500;
            $batches = array_chunk($ticketsData, $batchSize);

            foreach ($batches as $batch) {
                $placeholders = [];
                $values = [];

                foreach ($batch as $ticket) {
                    $placeholders[] = "(?, ?, ?, ?)";
                    $values[] = $ticket['raffle_id'];
                    $values[] = $ticket['ticket_number'];
                    $values[] = $ticket['opportunities'];
                    $values[] = TICKET_STATUS_AVAILABLE;
                }

                $sql = "INSERT INTO tickets (raffle_id, ticket_number, opportunities, status)
                        VALUES " . implode(', ', $placeholders);

                $stmt = $this->db->prepare($sql);
                $stmt->execute($values);
            }

            $this->commit();
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Generar números según cifras
     */
    private function generateNumbers(int $digits): array
    {
        $numbers = [];
        $max = pow(10, $digits);

        for ($i = 0; $i < $max; $i++) {
            $numbers[] = str_pad($i, $digits, '0', STR_PAD_LEFT);
        }

        return $numbers;
    }

    /**
     * Generar números de oportunidades
     */
    private function generateOpportunityNumbers(int $digits, int $count): array
    {
        $max = pow(10, $digits);
        $opportunities = [];

        // Generar números únicos
        while (count($opportunities) < $count) {
            $number = str_pad(mt_rand(0, $max - 1), $digits, '0', STR_PAD_LEFT);
            if (!in_array($number, $opportunities)) {
                $opportunities[] = $number;
            }
        }

        return $opportunities;
    }

    /**
     * Obtener boletos de un usuario
     */
    public function getUserTickets(int $userId, int $raffleId = null, int $vendorId = null): array
    {
        $sql = "SELECT t.*, r.name as raffle_name, r.draw_date, r.image_url
                FROM tickets t
                INNER JOIN raffles r ON t.raffle_id = r.id
                WHERE t.user_id = ? AND t.status IN (?, ?)";

        $params = [$userId, TICKET_STATUS_RESERVED, TICKET_STATUS_PAID];

        if ($raffleId) {
            $sql .= " AND t.raffle_id = ?";
            $params[] = $raffleId;
        }

        // $vendorId acota a "los pedidos de este comprador CON ESTE vendor" -
        // sin esto, cualquier bot de WhatsApp de cualquier vendor devolvia el
        // historial de compras del cliente en TODA la plataforma (nombre de
        // rifa, numero, estado) con cualquier otro vendor. `users` es una
        // identidad global por diseno (el mismo comprador compra en varios
        // vendors), pero el bot de un vendor no debe poder listarlo todo.
        if ($vendorId) {
            $sql .= " AND r.vendor_id = ?";
            $params[] = $vendorId;
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Verificar disponibilidad de boleto
     */
    public function isTicketAvailable(int $raffleId, string $ticketNumber): bool
    {
        $sql = "SELECT COUNT(*) FROM tickets
                WHERE raffle_id = ? AND ticket_number = ? AND status = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$raffleId, $ticketNumber, TICKET_STATUS_AVAILABLE]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Buscar tickets por números específicos
     */
    public function findByNumbers(int $raffleId, array $ticketNumbers): array
    {
        $placeholders = implode(',', array_fill(0, count($ticketNumbers), '?'));

        $sql = "SELECT * FROM tickets
                WHERE raffle_id = ? AND ticket_number IN ({$placeholders})";

        $params = array_merge([$raffleId], $ticketNumbers);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtener boletos ganadores
     */
    public function findWinningTickets(int $raffleId, string $winningNumber, string $winningMode): array
    {
        // Extraer cifras según modo
        $digits = '';
        switch ($winningMode) {
            case WINNING_MODE_LAST_2:
                $digits = substr($winningNumber, -2);
                break;
            case WINNING_MODE_FIRST_2:
                $digits = substr($winningNumber, 0, 2);
                break;
            case WINNING_MODE_LAST_3:
                $digits = substr($winningNumber, -3);
                break;
            case WINNING_MODE_FIRST_3:
                $digits = substr($winningNumber, 0, 3);
                break;
            case WINNING_MODE_LAST_4:
                $digits = substr($winningNumber, -4);
                break;
            default:
                throw new Exception("Modo de ganar inválido: {$winningMode}");
        }

        // Buscar en opportunities (JSON)
        $sql = "SELECT * FROM tickets
                WHERE raffle_id = ?
                  AND status = ?
                  AND JSON_CONTAINS(opportunities, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $raffleId,
            TICKET_STATUS_PAID,
            json_encode($digits)
        ]);

        return $stmt->fetchAll();
    }
}
