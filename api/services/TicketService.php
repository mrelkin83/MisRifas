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

    // reserveTickets() (reserva masiva) se eliminó: no tenía ningún llamador
    // y reservaba SIN bloqueo de fila (condición de carrera). Toda reserva
    // pasa por TicketRepository::reserveTicket / create-reservation.php, que
    // usan TicketStateMachine con SELECT ... FOR UPDATE.
}
