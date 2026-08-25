<?php
/**
 * Ticket Service
 * Maneja la generación y reserva concurrente de boletos (ACID).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../repositories/TicketRepository.php';

class TicketService
{
    private $ticketRepo;
    private $db;

    public function __construct()
    {
        $this->ticketRepo = new TicketRepository();
        $this->db = Database::getInstance();
    }

    /**
     * Genera automáticamente los tickets para una rifa recién creada, balanceando los números.
     */
    public function generateTicketsForRaffle(int $raffleId, int $totalTickets, int $digits, int $opportunities)
    {
        $maxNumber = pow(10, $digits) - 1; // Ej. 3 digits -> 999
        $totalNumbersAvailable = $maxNumber + 1;

        if ($totalTickets * $opportunities > $totalNumbersAvailable) {
            throw new Exception("La cantidad de números solicitados supera el rango de cifras permitidas.");
        }

        // Crear array con todos los números posibles en el rango, formateados con ceros a la izquierda
        $format = "%0" . $digits . "d";
        $pool = [];
        for ($i = 0; $i <= $maxNumber; $i++) {
            $pool[] = sprintf($format, $i);
        }

        // Aleatorizar los números para distribución equitativa
        shuffle($pool);

        $ticketsData = [];
        $currentIndex = 0;

        for ($i = 1; $i <= $totalTickets; $i++) {
            $ticketFormat = "%0" . strlen((string)$totalTickets) . "d";
            $ticketIdentifier = sprintf($ticketFormat, $i); // El identificador "visual" del cartón
            
            $assignedNumbers = [];
            for ($j = 0; $j < $opportunities; $j++) {
                if ($currentIndex >= count($pool)) break;
                $assignedNumbers[] = $pool[$currentIndex++];
            }

            $ticketsData[] = [
                'raffle_id' => $raffleId,
                'ticket_number' => $ticketIdentifier,
                'opportunities' => json_encode($assignedNumbers)
            ];
        }

        // Insert Batch para rendimiento
        $this->ticketRepo->batchInsertTickets($ticketsData);
    }

    /**
     * Reserva tickets con prevención de dobles compras usando Transacciones y Lock For Update.
     */
    public function reserveTickets(int $raffleId, int $userId, array $requestedTickets)
    {
        $conn = $this->db->getConnection();
        
        try {
            $conn->beginTransaction();

            // Lock en FOR UPDATE en los tickets específicos
            $inClause = implode(',', array_fill(0, count($requestedTickets), '?'));
            $sql = "SELECT id, status FROM tickets WHERE raffle_id = ? AND ticket_number IN ($inClause) FOR UPDATE";
            
            $stmt = $conn->prepare($sql);
            $params = array_merge([$raffleId], $requestedTickets);
            $stmt->execute($params);
            
            $lockedRows = $stmt->fetchAll();

            if (count($lockedRows) !== count($requestedTickets)) {
                throw new Exception("Algunos boletos no existen.");
            }

            foreach ($lockedRows as $row) {
                if ($row['status'] !== 'available') {
                    throw new Exception("Uno o más boletos seleccionados ya no están disponibles.");
                }
            }

            // Obtener horas de reserva, por defecto 2 (o lo de config)
            $stmtConf = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reservation_hours_default'");
            $stmtConf->execute();
            $confRow = $stmtConf->fetch();
            $hours = $confRow ? (int)$confRow['setting_value'] : 2;

            // Update status
            $updateSql = "UPDATE tickets SET status = 'reserved', user_id = ?, reserved_at = NOW(), reserved_until = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE raffle_id = ? AND ticket_number IN ($inClause)";
            
            $updateStmt = $conn->prepare($updateSql);
            $updateParams = array_merge([$userId, $hours, $raffleId], $requestedTickets);
            $updateStmt->execute($updateParams);

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
