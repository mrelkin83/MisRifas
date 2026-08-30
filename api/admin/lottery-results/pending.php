<?php
/**
 * API: Sorteos pendientes de resultado (solo super_admin)
 * GET /api/admin/lottery-results/pending.php
 *
 * Combinaciones lotería+fecha de rifas activas cuya fecha ya pasó y que aún
 * no tienen un resultado verificado (el scraper no lo obtuvo). Es lo que el
 * super_admin puede cargar manualmente.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/utils/Logger.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';

try {
    Auth::requireSuperAdmin();
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query("
        SELECT DISTINCT r.lottery_id, l.name AS lottery_name,
               DATE(r.draw_date) AS draw_date,
               COUNT(DISTINCT r.id) AS rifas
        FROM raffles r
        JOIN lotteries l ON r.lottery_id = l.id
        WHERE r.status = 'active'
          AND r.draw_date <= NOW()
          AND NOT EXISTS (
              SELECT 1 FROM lottery_results lr
              WHERE lr.lottery_id = r.lottery_id
                AND lr.draw_date = DATE(r.draw_date)
                AND lr.winning_number IS NOT NULL
                AND lr.verified = 1
          )
        GROUP BY r.lottery_id, l.name, DATE(r.draw_date)
        ORDER BY draw_date ASC
    ");
    $pending = array_map(function ($row) {
        return [
            'lottery_id'   => (int)$row['lottery_id'],
            'lottery_name' => $row['lottery_name'],
            'draw_date'    => $row['draw_date'],
            'rifas'        => (int)$row['rifas'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    Response::success($pending);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al listar sorteos pendientes');
}
