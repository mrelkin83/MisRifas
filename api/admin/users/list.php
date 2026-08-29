<?php
/**
 * API: Listar usuarios registrados (panel super_admin)
 * GET /api/admin/users/list.php
 *
 * Devuelve vendors (organizadores/admins) y users (compradores) en una lista
 * unificada con un campo `type`. Solo super_admin: es gestión de cuentas de
 * toda la plataforma. `deps` = registros dependientes, para que el frontend
 * avise que un borrado duro no es posible (sugerir suspender).
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

    $users = [];

    // Vendors (organizadores + super_admins). deps = rifas creadas.
    $stmt = $db->query("
        SELECT v.id, v.business_name AS name, v.email, v.phone, v.role, v.status,
               v.city, v.department, v.created_at,
               (SELECT COUNT(*) FROM raffles r WHERE r.created_by = v.id) AS deps
        FROM vendors v
        ORDER BY v.created_at DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $users[] = [
            'type'       => 'vendor',
            'id'         => (int)$v['id'],
            'name'       => $v['name'],
            'email'      => $v['email'],
            'phone'      => $v['phone'],
            'role'       => $v['role'],
            'status'     => $v['status'], // active | suspended | pending_verification
            'city'       => $v['city'],
            'department' => $v['department'],
            'created_at' => $v['created_at'],
            'deps'       => (int)$v['deps'],
        ];
    }

    // Compradores. deps = boletos (tickets).
    $stmt = $db->query("
        SELECT u.id, u.name, u.email, u.phone_whatsapp AS phone, u.active,
               u.city, u.department, u.created_at,
               (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id) AS deps
        FROM users u
        ORDER BY u.created_at DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $users[] = [
            'type'       => 'buyer',
            'id'         => (int)$u['id'],
            'name'       => $u['name'],
            'email'      => $u['email'],
            'phone'      => $u['phone'],
            'role'       => 'buyer',
            'status'     => ((int)$u['active'] === 1) ? 'active' : 'suspended',
            'city'       => $u['city'],
            'department' => $u['department'],
            'created_at' => $u['created_at'],
            'deps'       => (int)$u['deps'],
        ];
    }

    Response::success($users);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al listar usuarios');
}
