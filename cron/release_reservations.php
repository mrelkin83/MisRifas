<?php
/**
 * Cron Job: Liberar Reservas Expiradas
 * Frecuencia: Cada 15 minutos
 */

// Configuración
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/repositories/TicketRepository.php';

// Verificar ejecución desde CLI
if (php_sapi_name() !== 'cli') {
    // Verificar cron secret
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';

    if ($cronSecret !== $config['cron']['secret_key']) {
        http_response_code(403);
        die('Forbidden');
    }
}

$startTime = microtime(true);
Logger::info("=== Iniciando: Liberar reservas expiradas ===");

try {
    $ticketRepo = new TicketRepository();

    // Liberar reservas
    $releasedCount = $ticketRepo->releaseExpiredReservations();

    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('release_reservations', true, [
        'released_count' => $releasedCount,
        'execution_time' => $executionTime . 's'
    ]);

    echo "✅ Liberados {$releasedCount} boletos en {$executionTime}s\n";

} catch (Exception $e) {
    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('release_reservations', false, [
        'error' => $e->getMessage(),
        'execution_time' => $executionTime . 's'
    ]);

    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
