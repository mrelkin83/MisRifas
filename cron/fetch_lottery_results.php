<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
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
Logger::info("=== Iniciando: Smart Fetch Lottery Results ===");

try {
    $db = Database::getInstance()->getConnection();

    // La lógica vive en ScraperRunner (compartida con el botón "Ejecutar
    // ahora" del panel) e incluye el interruptor y los slugs por lotería
    // administrables desde Gestión de Rifas → Scraper.
    require_once __DIR__ . '/../api/services/ScraperRunner.php';

    if (!ScraperRunner::habilitado($db)) {
        Logger::info('Scraper APAGADO desde el panel (scraper_enabled=0): no se consulta nada.');
        echo "Scraper apagado desde el panel (scraper_enabled=0)\n";
        exit(0);
    }

    $r = ScraperRunner::correr($db);
    foreach ($r['detalle'] as $d) {
        if ($d['ok']) {
            Logger::info('Resultado guardado', ['lottery' => $d['loteria'], 'date' => $d['fecha'], 'number' => $d['numero']]);
        } else {
            Logger::warning('Scraping sin resultado (sorteo queda pendiente, se reintentará)', ['lottery' => $d['loteria'], 'date' => $d['fecha']]);
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('fetch_lottery_results', true, [
        'pending' => $r['pending'],
        'saved' => $r['saved'],
        'errors' => $r['errors'],
        'time' => $executionTime . 's'
    ]);

    echo "Pendientes: {$r['pending']} | Guardados: {$r['saved']} | Errores: {$r['errors']} | Tiempo: {$executionTime}s\n";

} catch (Exception $e) {
    Logger::exception($e);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
