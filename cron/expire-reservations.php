<?php
/**
 * CRON: Expirar Reservas
 * Ejecuta cada minuto: * * * * * /path/to/php C:/xampp/htdocs/MisRifas/cron/expire-reservations.php
 *
 * Pasa números de RESERVADO → DISPONIBLE
 * cuando expires_at < NOW()
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/repositories/TicketRepository.php';

if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if (empty($cronSecret) || $cronSecret !== ($config['cron']['secret_key'] ?? '')) {
        http_response_code(403);
        die('Forbidden');
    }
}

$startTime = microtime(true);
Logger::info("=== Iniciando: Expire Reservations ===");

try {
    $db = Database::getInstance()->getConnection();

    // ========================================
    // TRANSACCIÓN: Expirar reservas
    // ========================================
    $db->beginTransaction();

    try {
        // Toda liberación pasa por la máquina de estados (bitácora incluida).
        // Antes de liberar, se completa reserved_until en tickets 'reserved'
        // huérfanos cuyo vencimiento solo vivía en numero_reservas (legado);
        // ese UPDATE no toca status, solo el dato de vencimiento.
        $db->prepare("
            UPDATE tickets t
            INNER JOIN numero_reservas nr
                ON t.raffle_id = nr.raffle_id AND t.ticket_number = nr.numero
            SET t.reserved_until = nr.expires_at
            WHERE t.status = 'reserved' AND t.reserved_until IS NULL
              AND nr.estado = 'RESERVADO' AND nr.expires_at IS NOT NULL
        ")->execute();

        $ticketRepo = new TicketRepository();
        // Reservas vencidas (TTL de reserva) + comprobantes sin respuesta
        // del vendedor (TTL de revisión, §7.4).
        $ticketsReleased = $ticketRepo->releaseExpiredReservations();
        $reviewsReleased = $ticketRepo->releaseExpiredReviews();
        $ticketsReleased += $reviewsReleased;

        // Buscar números RESERVADOS con expires_at vencido
        $stmt = $db->prepare("
            UPDATE numero_reservas
            SET estado = 'DISPONIBLE',
                user_id = NULL,
                reservation_id = NULL,
                reserved_at = NULL,
                expires_at = NULL,
                payment_intent_id = NULL,
                updated_at = NOW()
            WHERE estado = 'RESERVADO'
              AND expires_at IS NOT NULL
              AND expires_at < NOW()
        ");
        $stmt->execute();
        $expiredCount = $stmt->rowCount();

        // Actualizar payment_intents a CANCELLED
        $stmt = $db->prepare("
            UPDATE payment_intents pi
            INNER JOIN numero_reservas nr ON pi.id = nr.payment_intent_id
            SET pi.status = 'CANCELLED',
                pi.processed_at = NOW(),
                pi.updated_at = NOW()
            WHERE nr.estado = 'DISPONIBLE'
              AND pi.status = 'PENDING'
        ");
        $stmt->execute();
        $cancelledPaymentIntents = $stmt->rowCount();

        $db->commit();

        Logger::cron('expire_reservations', true, [
            'expired_reservations' => $expiredCount,
            'tickets_released' => $ticketsReleased,
            'cancelled_payment_intents' => $cancelledPaymentIntents,
            'execution_time' => round(microtime(true) - $startTime, 2) . 's'
        ]);

        echo "Reservas expiradas: {$expiredCount} | Tickets liberados: {$ticketsReleased} | Payment intents cancelados: {$cancelledPaymentIntents}\n";
        echo "Tiempo: " . round(microtime(true) - $startTime, 2) . "s\n";

    } catch (Exception $e) {
        $db->rollBack();
        Logger::exception($e);
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }

} catch (Exception $e) {
    Logger::exception($e);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
