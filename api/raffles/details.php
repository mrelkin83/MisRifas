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

    Response::success($raffle);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar la rifa');
}
