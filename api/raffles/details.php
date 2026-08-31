<?php
/**
 * API: Detalles de Rifa
 * GET /api/raffles/details.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/repositories/RaffleRepository.php';

try {
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        Response::error('ID de rifa requerido', null, 400);
    }

    $raffleRepo = new RaffleRepository();
    $raffle = $raffleRepo->getRaffleWithStats($id);

    if (!$raffle) {
        Response::notFound('Rifa no encontrada');
    }

    $raffleRepo->incrementViews($id);

    // Reseñas de compradores (v4.12): promedio del organizador, solo si el
    // sistema está habilitado.
    try {
        $db = Database::getInstance()->getConnection();
        if ($db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reviews_enabled'")->fetchColumn() === '1') {
            $vid = (int)($raffle['vendor_id'] ?: $raffle['created_by']);
            $st = $db->prepare('SELECT ROUND(AVG(rating), 1) AS a, COUNT(*) AS n FROM vendor_reviews WHERE vendor_id = ?');
            $st->execute([$vid]);
            $agg = $st->fetch(PDO::FETCH_ASSOC);
            $raffle['organizer_rating'] = $agg['a'] !== null ? (float)$agg['a'] : null;
            $raffle['organizer_reviews'] = (int)($agg['n'] ?? 0);
        }
    } catch (Exception $e) {
        // sin reseñas: la página funciona igual
    }

    Response::success($raffle);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar la rifa');
}
