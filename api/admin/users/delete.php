<?php
/**
 * API: Eliminar un usuario (panel super_admin)
 * POST /api/admin/users/delete.php
 * body: { type: 'vendor'|'buyer', id }
 *
 * Borrado duro SOLO si no hay registros dependientes (rifas para vendors,
 * boletos para compradores). Con dependencias se bloquea y se sugiere
 * suspender: borrar en cascada perdería ventas/rifas/historial reales.
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

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type  = $input['type'] ?? '';
    $id    = intval($input['id'] ?? 0);

    if (!in_array($type, ['vendor', 'buyer'], true) || !$id) {
        Response::error('Tipo o id de usuario inválido');
    }
    if ($type === 'vendor' && $id === (int)$admin['id']) {
        Response::error('No puedes eliminar tu propia cuenta', null, 400);
    }

    if ($type === 'vendor') {
        $deps = (int)$db->query("SELECT COUNT(*) FROM raffles WHERE created_by = {$id} OR vendor_id = {$id}")->fetchColumn();
        if ($deps > 0) {
            Response::error("Este organizador tiene {$deps} rifa(s) asociada(s). Suspéndelo en vez de eliminarlo para conservar el historial.", null, 409);
        }
        $stmt = $db->prepare("DELETE FROM vendors WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $deps = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE user_id = {$id}")->fetchColumn();
        if ($deps > 0) {
            Response::error("Este comprador tiene {$deps} boleto(s) asociado(s). Suspéndelo en vez de eliminarlo para conservar el historial.", null, 409);
        }
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }

    Logger::activity('user_deleted', $admin['id'], ['type' => $type, 'target_id' => $id]);
    Response::success(['message' => 'Usuario eliminado']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al eliminar el usuario');
}
