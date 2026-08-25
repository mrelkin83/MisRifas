<?php
/**
 * API: Delete Raffle
 * POST /api/admin/raffles/delete.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/utils/Logger.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['raffle_id'])) {
        Response::error('ID de rifa requerido');
    }

    $raffleId = intval($input['raffle_id']);

    // Check permissions
    if ($adminUser['role'] !== 'super_admin') {
        $stmt = $db->prepare("SELECT id FROM raffles WHERE id = ? AND created_by = ?");
        $stmt->execute([$raffleId, $adminUser['id']]);
        if (!$stmt->fetch()) {
            Response::error('No tienes permisos para eliminar esta rifa', null, 403);
        }
    }

    // Delete raffle (cascade will handle tickets, etc.)
    $stmt = $db->prepare("DELETE FROM raffles WHERE id = ?");
    $stmt->execute([$raffleId]);

    if ($stmt->rowCount() === 0) {
        Response::error('Rifa no encontrada', null, 404);
    }

    Logger::activity('raffle_deleted', $adminUser['id'], ['raffle_id' => $raffleId]);

    Response::success(['message' => 'Rifa eliminada correctamente']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al eliminar la rifa');
}
