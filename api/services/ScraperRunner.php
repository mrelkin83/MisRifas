<?php

/**
 * Corrida del scraper de resultados: la MISMA lógica para el cron
 * (cron/fetch_lottery_results.php) y para el botón "Ejecutar ahora" del panel.
 *
 * Reglas que no se negocian:
 * - Si el scrape falla, NO se inventa número y NO se inserta fila: el sorteo
 *   queda pendiente y se reintenta (o el admin lo carga manual).
 * - Respeta el interruptor administrable scraper_enabled (system_settings).
 * - Cada lotería puede tener su slug propio en lotteries.api_source
 *   (configurable en el panel); si está vacío se auto-resuelve del nombre.
 */

require_once __DIR__ . '/LotteryScraperService.php';

class ScraperRunner
{
    public static function habilitado(PDO $db): bool
    {
        $v = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'scraper_enabled'")->fetchColumn();
        return $v === false || (string)$v !== '0'; // sin fila = habilitado
    }

    /** Combinaciones lotería+fecha de rifas activas SIN resultado aún. */
    public static function pendientes(PDO $db): array
    {
        $stmt = $db->query("
            SELECT DISTINCT r.lottery_id, DATE(r.draw_date) AS target_date,
                   l.name AS lottery_name, l.api_source
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{pending:int,saved:int,errors:int,detalle:array} */
    public static function correr(PDO $db): array
    {
        $pending = self::pendientes($db);
        $saved = 0;
        $errors = 0;
        $detalle = [];

        foreach ($pending as $item) {
            try {
                $winningNumber = LotteryScraperService::fetchResult(
                    $item['lottery_name'],
                    $item['target_date'],
                    (string)($item['api_source'] ?? '')
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
                    $saved++;
                    $detalle[] = ['loteria' => $item['lottery_name'], 'fecha' => $item['target_date'], 'numero' => $winningNumber, 'ok' => true];
                } else {
                    // Falla honesta: sin fila, el sorteo sigue pendiente y se reintenta.
                    $detalle[] = ['loteria' => $item['lottery_name'], 'fecha' => $item['target_date'], 'numero' => null, 'ok' => false];
                }
            } catch (Exception $e) {
                $errors++;
                $detalle[] = ['loteria' => $item['lottery_name'], 'fecha' => $item['target_date'], 'numero' => null, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        // Última corrida VISIBLE en el panel (estado en vivo, nunca supuesto).
        $resumen = json_encode([
            'at' => date('Y-m-d H:i:s'),
            'pending' => count($pending),
            'saved' => $saved,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
        $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'scraper_last_run'")
           ->execute([$resumen]);

        return ['pending' => count($pending), 'saved' => $saved, 'errors' => $errors, 'detalle' => $detalle];
    }
}
