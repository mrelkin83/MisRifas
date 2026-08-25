<?php
/**
 * API: Change Password
 * POST /api/user/change_password.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $user = Auth::requireLogin();
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !is_array($input)) {
        Response::error('Datos inválidos');
    }

    $currentPassword = trim($input['current_password'] ?? '');
    $newPassword = trim($input['new_password'] ?? '');

    if (empty($currentPassword) || empty($newPassword)) {
        Response::error('Todos los campos son requeridos', null, 400);
    }

    if (strlen($newPassword) < 6) {
        Response::error('La nueva contraseña debe tener al menos 6 caracteres', null, 400);
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        Response::error('La contraseña actual es incorrecta', null, 401);
    }

    // Update password
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newHash, $user['id']]);

    Logger::activity('password_changed', $user['id'], ['email' => $user['email']]);

    Response::success(['message' => 'Contraseña actualizada']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cambiar la contraseña');
}
