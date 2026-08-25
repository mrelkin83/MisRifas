<?php
/**
 * Ticket Repository
 * Maneja operaciones de boletos con control de concurrencia
 */

require_once __DIR__ . '/BaseRepository.php';

class TicketRepository extends BaseRepository
{
    protected $table = 'tickets';

    /**
     * Reservar boleto con control de concurrencia (CRÍTICO)
     * Usa SELECT ... FOR UPDATE para lock pesimista
     */
    public function reserveTicket(int $raffleId, string $ticketNumber, int $userId, int $hours = 2): ?array
    {
        try {
            $this->beginTransaction();

            // Lock pesimista: SELECT FOR UPDATE
            $sql = "SELECT * FROM tickets
                    WHERE raffle_id = ? AND ticket_number = ? AND status = ?
                    FOR UPDATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$raffleId, $ticketNumber, TICKET_STATUS_AVAILABLE]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                $this->rollback();
                return null; // Boleto no disponible
            }

            // Calcular reserva hasta
            $reservedUntil = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));

            // Actualizar boleto
            $updateSql = "UPDATE tickets
                         SET status = ?,
                             user_id = ?,
                             reserved_at = NOW(),
                             reserved_until = ?
                         WHERE id = ?";

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                TICKET_STATUS_RESERVED,
                $userId,
                $reservedUntil,
                $ticket['id']
            ]);

            $this->commit();

            // Obtener boleto actualizado
            return $this->findById($ticket['id']);

        } catch (Exception $e) {
            $this->rollback();
            Logger::exception($e);
            throw $e;
        }
    }

    /**
     * Marcar boleto como pagado
     */
    public function markAsPaid(int $ticketId, int $paymentId): bool
    {
        try {
            $this->beginTransaction();

            $sql = "UPDATE tickets
                    SET status = ?,
                        paid_at = NOW(),
                        payment_id = ?
                    WHERE id = ? AND status = ?";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                TICKET_STATUS_PAID,
                $paymentId,
                $ticketId,
                TICKET_STATUS_RESERVED
            ]);

            $this->commit();
            return $result && $stmt->rowCount() > 0;

        } catch (Exception $e) {
            $this->rollback();
            Logger::exception($e);
            return false;
        }
    }

    /**
     * Liberar boletos expirados
     * Usado por cron job
     */
    public function releaseExpiredReservations(): int
    {
        try {
            $sql = "UPDATE tickets
                    SET status = ?,
                        user_id = NULL,
                        reserved_at = NULL,
                        reserved_until = NULL
                    WHERE status = ? AND reserved_until < NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([TICKET_STATUS_AVAILABLE, TICKET_STATUS_RESERVED]);

            return $stmt->rowCount();

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
    public function getUserTickets(int $userId, int $raffleId = null): array
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
