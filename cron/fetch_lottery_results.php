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

    $stmt = $db->prepare("
        SELECT DISTINCT r.lottery_id, DATE(r.draw_date) as target_date,
               l.name as lottery_name
        FROM raffles r
        JOIN lotteries l ON r.lottery_id = l.id
        WHERE r.status = 'active'
        AND DATE(r.draw_date) <= CURDATE()
        AND NOT EXISTS (
            SELECT 1 FROM lottery_results lr
            WHERE lr.lottery_id = r.lottery_id
            AND lr.draw_date = DATE(r.draw_date)
            AND lr.winning_number IS NOT NULL
        )
        ORDER BY target_date ASC
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll();

    Logger::info("Combinaciones pendientes: " . count($pending));

    $resultsSaved = 0;
    $errors = 0;

    foreach ($pending as $item) {
        try {
            require_once __DIR__ . '/../api/services/LotteryScraperService.php';

            $winningNumber = LotteryScraperService::fetchResult(
                $item['lottery_name'],
                $item['target_date']
            );

            if ($winningNumber && strlen($winningNumber) >= 2) {
                $stmt = $db->prepare("
                    INSERT INTO lottery_results (lottery_id, draw_date, winning_number, scraped_at, scrape_source, verified)
                    VALUES (?, ?, ?, NOW(), 'colombia.com', 1)
                    ON DUPLICATE KEY UPDATE
                        winning_number = VALUES(winning_number),
                        scraped_at = NOW(),
                        scrape_source = 'colombia.com',
                        scrape_attempts = scrape_attempts + 1,
                        verified = 1
                ");
                $stmt->execute([$item['lottery_id'], $item['target_date'], $winningNumber]);
                $resultsSaved++;

                Logger::info("Resultado guardado", [
                    'lottery_id' => $item['lottery_id'],
                    'lottery' => $item['lottery_name'],
                    'date' => $item['target_date'],
                    'number' => $winningNumber
                ]);
            } else {
                // Scrape falló: NO se inventa un número (ver LotteryScraperService)
                // y NO se inserta fila. winning_number es NOT NULL, así que la
                // ausencia de fila es justo lo que mantiene el sorteo "pendiente"
                // (la query de arriba busca combinaciones SIN resultado); así se
                // reintenta en la siguiente corrida. Solo se registra el fallo.
                Logger::warning("Scraping sin resultado (sorteo queda pendiente, se reintentará)", [
                    'lottery' => $item['lottery_name'],
                    'date' => $item['target_date']
                ]);
            }
        } catch (Exception $e) {
            $errors++;
            Logger::error("Error scraping: " . $e->getMessage(), [
                'lottery' => $item['lottery_name'],
                'date' => $item['target_date']
            ]);
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('fetch_lottery_results', true, [
        'pending' => count($pending),
        'saved' => $resultsSaved,
        'errors' => $errors,
        'time' => $executionTime . 's'
    ]);

    echo "Pendientes: " . count($pending) . " | Guardados: {$resultsSaved} | Errores: {$errors} | Tiempo: {$executionTime}s\n";

} catch (Exception $e) {
    Logger::exception($e);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
