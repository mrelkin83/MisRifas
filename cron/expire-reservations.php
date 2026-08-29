<?php
/**
 * CRON: Expirar Reservas
 * Ejecuta cada minuto: * * * * * /path/to/php C:/xampp/htdocs/MisRifas/cron/expire-reservations.php
 *
 * Pasa números de RESERVADO → DISPONIBLE
 * cuando expires_at < NOW()
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';

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
        // Liberar tambien la fila de `tickets` que create-reservation.php
        // marca 'reserved' (ver hallazgo H6 - dos sistemas de inventario
        // que antes no se sincronizaban) - antes de que el UPDATE de abajo
        // borre expires_at/numero_reservas y se pierda la referencia.
        $stmt = $db->prepare("
            UPDATE tickets t
            INNER JOIN numero_reservas nr
                ON t.raffle_id = nr.raffle_id AND t.ticket_number = nr.numero
            SET t.status = 'available', t.user_id = NULL, t.reserved_at = NULL, t.reserved_until = NULL
            WHERE nr.estado = 'RESERVADO' AND nr.expires_at IS NOT NULL AND nr.expires_at < NOW()
        ");
        $stmt->execute();
        $ticketsReleased = $stmt->rowCount();

        // Barrido amplio: liberar CUALQUIER ticket 'reserved' vencido aunque
        // no tenga fila en numero_reservas (reservas directas/antiguas del
        // flujo por ticket). Antes esto lo cubria el cron separado
        // release_reservations.php - ahora unificado aqui para que este sea
        // el unico cron de expiracion.
        $stmt = $db->prepare("
            UPDATE tickets
            SET status = 'available', user_id = NULL, reserved_at = NULL, reserved_until = NULL
            WHERE status = 'reserved' AND reserved_until IS NOT NULL AND reserved_until < NOW()
        ");
        $stmt->execute();
        $ticketsReleased += $stmt->rowCount();

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
