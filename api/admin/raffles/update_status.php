<?php
/**
 * API: Update Raffle Status
 * POST /api/admin/raffles/update_status.php
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
    
    if (!$input || !is_array($input)) {
        Response::error('Datos inválidos');
    }

    $raffleId = intval($input['raffle_id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');

    if (!$raffleId || !in_array($newStatus, ['draft', 'active', 'blocked', 'completed', 'cancelled'])) {
        Response::error('Datos inválidos o estado no permitido', null, 400);
    }

    // §5.1: para PUBLICAR, el dueño de la rifa debe tener al menos una llave
    // de cobro configurada (Nequi/DaviPlata/Bre-B/efectivo) — sin ella el
    // comprador no tendría a dónde transferir.
    if ($newStatus === 'active') {
        require_once __DIR__ . '/../../../api/services/PaymentKeys.php';
        $stmt = $db->prepare("SELECT COALESCE(vendor_id, created_by) FROM raffles WHERE id = ?");
        $stmt->execute([$raffleId]);
        $ownerId = (int)$stmt->fetchColumn();
        if ($ownerId && !PaymentKeys::tieneAlguno(PaymentKeys::delVendor($db, $ownerId))) {
            Response::error(
                'Antes de publicar configura cómo te pagan tus compradores (Nequi, DaviPlata, llave Bre-B o efectivo) en Mi Perfil.',
                'PAYMENT_KEYS_REQUIRED',
                422
            );
        }
    }

    // Super admin puede cambiar cualquier rifa, otros solo las suyas
    if ($adminUser['role'] === 'super_admin') {
        $stmt = $db->prepare("UPDATE raffles SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $raffleId]);
    } else {
        $stmt = $db->prepare("UPDATE raffles SET status = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
        $stmt->execute([$newStatus, $raffleId, $adminUser['id']]);
    }

    if ($stmt->rowCount() === 0) {
        Response::error('Rifa no encontrada o no tienes permisos', null, 404);
    }

    Logger::activity('raffle_status_changed', $adminUser['id'], [
        'raffle_id' => $raffleId,
        'new_status' => $newStatus
    ]);

    Response::success(['message' => 'Estado actualizado correctamente']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al actualizar el estado');
}
