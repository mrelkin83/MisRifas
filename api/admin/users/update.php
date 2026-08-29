<?php
/**
 * API: Actualizar un usuario (panel super_admin)
 * POST /api/admin/users/update.php
 * body: { type: 'vendor'|'buyer', id, name, email, phone, city, department, role? }
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
require_once __DIR__ . '/../../../api/utils/Validator.php';

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

    $name       = Validator::sanitize(trim($input['name'] ?? ''));
    $email      = trim($input['email'] ?? '');
    $phone      = Validator::sanitize(trim($input['phone'] ?? ''));
    $city       = Validator::sanitize(trim($input['city'] ?? ''));
    $department = Validator::sanitize(trim($input['department'] ?? ''));

    if ($name === '') {
        Response::error('El nombre es requerido');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email inválido');
    }

    if ($type === 'vendor') {
        // Email único entre vendors (excluyendo el propio)
        if ($email !== '') {
            $chk = $db->prepare("SELECT id FROM vendors WHERE email = ? AND id <> ?");
            $chk->execute([$email, $id]);
            if ($chk->fetch()) Response::error('Ya existe otro organizador con ese email');
        }

        // Rol: solo vendor|super_admin; un super_admin no puede quitarse su
        // propio rol (evita quedarse sin acceso de administrador).
        $role = $input['role'] ?? null;
        $setRole = '';
        $params = [$name, $email ?: null, $phone, $city ?: null, $department ?: null];
        if ($role !== null && in_array($role, ['vendor', 'super_admin'], true)) {
            if ($id === (int)$admin['id'] && $role !== 'super_admin') {
                Response::error('No puedes quitarte tu propio rol de super_admin', null, 400);
            }
            $setRole = ', role = ?';
            $params[] = $role;
        }
        $params[] = $id;

        $stmt = $db->prepare("
            UPDATE vendors
            SET business_name = ?, email = ?, phone = ?, city = ?, department = ?{$setRole}
            WHERE id = ?
        ");
        $stmt->execute($params);

    } else { // buyer
        if ($email !== '') {
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $id]);
            if ($chk->fetch()) Response::error('Ya existe otro comprador con ese email');
        }
        $stmt = $db->prepare("
            UPDATE users
            SET name = ?, email = ?, phone_whatsapp = ?, city = ?, department = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email ?: null, $phone, $city ?: null, $department ?: null, $id]);
    }

    Logger::activity('user_updated', $admin['id'], ['type' => $type, 'target_id' => $id]);
    Response::success(['message' => 'Usuario actualizado correctamente']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al actualizar el usuario');
}
