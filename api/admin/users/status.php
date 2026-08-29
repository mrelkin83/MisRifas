<?php
/**
 * API: Suspender / activar un usuario (panel super_admin)
 * POST /api/admin/users/status.php
 * body: { type: 'vendor'|'buyer', id, action: 'suspend'|'activate' }
 *
 * vendor: status active <-> suspended.  buyer: active 1 <-> 0.
 * Una cuenta suspendida no puede autenticarse (Auth exige status='active' /
 * active=1), así que esto corta el acceso sin borrar datos.
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

    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $type   = $input['type'] ?? '';
    $id     = intval($input['id'] ?? 0);
    $action = $input['action'] ?? '';

    if (!in_array($type, ['vendor', 'buyer'], true) || !$id) {
        Response::error('Tipo o id de usuario inválido');
    }
    if (!in_array($action, ['suspend', 'activate'], true)) {
        Response::error('Acción inválida');
    }

    // No suspenderse a sí mismo (evita perder el acceso de administrador)
    if ($type === 'vendor' && $id === (int)$admin['id'] && $action === 'suspend') {
        Response::error('No puedes suspender tu propia cuenta', null, 400);
    }

    if ($type === 'vendor') {
        $newStatus = $action === 'suspend' ? 'suspended' : 'active';
        // Al suspender, invalidar el token para cortar la sesión activa ya.
        if ($action === 'suspend') {
            $stmt = $db->prepare("UPDATE vendors SET status = ?, auth_token = NULL WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE vendors SET status = ? WHERE id = ?");
        }
        $stmt->execute([$newStatus, $id]);
    } else {
        $active = $action === 'suspend' ? 0 : 1;
        if ($action === 'suspend') {
            $stmt = $db->prepare("UPDATE users SET active = ?, auth_token = NULL WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE users SET active = ? WHERE id = ?");
        }
        $stmt->execute([$active, $id]);
    }

    Logger::activity('user_status_changed', $admin['id'], ['type' => $type, 'target_id' => $id, 'action' => $action]);
    Response::success(['message' => $action === 'suspend' ? 'Usuario suspendido' : 'Usuario activado']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cambiar el estado del usuario');
}
