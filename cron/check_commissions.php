<?php
/**
 * Cron Job: Verificar y Bloquear Rifas sin Comisión Pagada
 * Frecuencia: Diario a las 4:00 AM
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/repositories/RaffleRepository.php';
require_once __DIR__ . '/../api/services/NotificationService.php';

// Verificar CLI o secret
if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if ($cronSecret !== $config['cron']['secret_key']) {
        http_response_code(403);
        die('Forbidden');
    }
}

$startTime = microtime(true);
Logger::info("=== Iniciando: Verificar comisiones pendientes ===");

try {
    $raffleRepo = new RaffleRepository();
    $notificationService = new NotificationService();

    // Obtener rifas con comisión pendiente
    $raffles = $raffleRepo->getRafflesWithPendingCommission();

    $blockedCount = 0;
    $notifiedCount = 0;

    foreach ($raffles as $raffle) {
        // Bloquear rifa
        $blocked = $raffleRepo->blockRaffle($raffle['id'], 'Comisión no pagada');

        if ($blocked) {
            $blockedCount++;

            Logger::warning("Rifa bloqueada por comisión no pagada", [
                'raffle_id' => $raffle['id'],
                'raffle_name' => $raffle['name'],
                'commission_amount' => $raffle['commission_amount'],
                'due_date' => $raffle['commission_due_date']
            ]);

            // Notificar al creador
            $message = "⚠️ Su rifa '{$raffle['name']}' ha sido bloqueada por falta de pago de comisión.\n\n";
            $message .= "Monto: $" . number_format($raffle['commission_amount'], 0, ',', '.');
            $message .= "\nFecha límite: {$raffle['commission_due_date']}\n\n";
            $message .= "Para desbloquear, realice el pago y cargue el comprobante en su panel.";

            try {
                // Notificar por WhatsApp
                $notificationService->sendWhatsAppMessage(
                    $raffle['whatsapp_contact'],
                    $message
                );
                $notifiedCount++;
            } catch (Exception $e) {
                Logger::error("Error al notificar bloqueo de rifa", [
                    'raffle_id' => $raffle['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('check_commissions', true, [
        'raffles_checked' => count($raffles),
        'blocked_count' => $blockedCount,
        'notified_count' => $notifiedCount,
        'execution_time' => $executionTime . 's'
    ]);

    echo "✅ Verificadas " . count($raffles) . " rifas\n";
    echo "✅ Bloqueadas {$blockedCount} rifas\n";
    echo "✅ Notificaciones enviadas: {$notifiedCount}\n";
    echo "⏱️  Tiempo: {$executionTime}s\n";

} catch (Exception $e) {
    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('check_commissions', false, [
        'error' => $e->getMessage(),
        'execution_time' => $executionTime . 's'
    ]);

    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
