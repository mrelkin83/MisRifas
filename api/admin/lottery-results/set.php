<?php
/**
 * API: Ingresar/actualizar el número ganador de una lotería MANUALMENTE.
 * POST /api/admin/lottery-results/set.php   (solo super_admin)
 * body: { lottery_id, draw_date (YYYY-MM-DD), winning_number }
 *
 * Respaldo para cuando el scraper de colombia.com no obtiene el resultado
 * (sitio caído / cambió de formato). El super_admin verifica el número en la
 * fuente oficial y lo carga aquí; queda con verified=1 y scrape_source='manual',
 * y process_draws.php lo usará para cerrar los sorteos pendientes.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $admin = Auth::requireSuperAdmin();
    $db = Database::getInstance()->getConnection();

    $input     = json_decode(file_get_contents('php://input'), true) ?: [];
    $lotteryId = intval($input['lottery_id'] ?? 0);
    $drawDate  = trim($input['draw_date'] ?? '');
    $number    = trim((string)($input['winning_number'] ?? ''));

    if (!$lotteryId) {
        Response::error('Lotería requerida');
    }
    // Fecha válida YYYY-MM-DD
    $d = DateTime::createFromFormat('Y-m-d', $drawDate);
    if (!$d || $d->format('Y-m-d') !== $drawDate) {
        Response::error('Fecha inválida (formato YYYY-MM-DD)');
    }
    // Número plausible: solo dígitos, 2 a 6 cifras (igual criterio que el scraper)
    if (!preg_match('/^\d{2,6}$/', $number)) {
        Response::error('Número ganador inválido: solo dígitos, 2 a 6 cifras');
    }

    // La lotería debe existir
    $stmt = $db->prepare("SELECT name FROM lotteries WHERE id = ?");
    $stmt->execute([$lotteryId]);
    $lottery = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lottery) {
        Response::error('Lotería no encontrada', null, 404);
    }

    $stmt = $db->prepare("
        INSERT INTO lottery_results
            (lottery_id, draw_date, winning_number, fetched_at, verified, scraped_at, scrape_source, scrape_attempts, created_at)
        VALUES (?, ?, ?, NOW(), 1, NOW(), 'manual', 0, NOW())
        ON DUPLICATE KEY UPDATE
            winning_number = VALUES(winning_number),
            verified       = 1,
            scraped_at     = NOW(),
            scrape_source  = 'manual'
    ");
    $stmt->execute([$lotteryId, $drawDate, $number]);

    Logger::activity('lottery_result_manual', $admin['id'], [
        'lottery_id' => $lotteryId,
        'lottery'    => $lottery['name'],
        'draw_date'  => $drawDate,
        'number'     => $number,
    ]);

    Response::success([
        'message' => 'Resultado guardado. Los sorteos pendientes de esta lotería y fecha se procesarán en la próxima corrida.',
    ]);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al guardar el resultado');
}
