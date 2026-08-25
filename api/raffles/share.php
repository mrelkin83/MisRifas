<?php
/**
 * API: Compartir Rifa
 * POST /api/raffles/share.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/repositories/RaffleRepository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $raffleId = (int)($input['raffle_id'] ?? 0);
    $platform = $input['platform'] ?? '';

    if (!$raffleId) {
        Response::error('ID de rifa requerido', null, 400);
    }

    $raffleRepo = new RaffleRepository();
    $raffleRepo->incrementShares($raffleId);

    Logger::activity('raffle_shared', null, [
        'raffle_id' => $raffleId,
        'platform' => $platform
    ]);

    Response::success(['message' => 'Compartido exitosamente']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al registrar el share');
}
