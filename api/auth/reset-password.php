<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', null, 405);
}

if (!RateLimiter::check('reset_pw_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 10)) {
    Response::rateLimitExceeded('Demasiados intentos. Intenta de nuevo mas tarde.');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($token) || empty($password)) {
        Response::error('Token y nueva contrasena son requeridos', null, 400);
    }

    if (strlen($password) < 6) {
        Response::error('La contrasena debe tener al menos 6 caracteres', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        Response::error('Token invalido o expirado', null, 400);
    }

    $email = $reset['email'];
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE vendors SET password_hash = ?, auth_token = NULL WHERE email = ?");
    $stmt->execute([$hash, $email]);
    $updated = $stmt->rowCount();

    if ($updated === 0) {
        $stmt = $db->prepare("UPDATE admin_users SET password_hash = ?, auth_token = NULL WHERE email = ?");
        $stmt->execute([$hash, $email]);
        $updated = $stmt->rowCount();
    }

    if ($updated === 0) {
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, auth_token = NULL WHERE email = ?");
        $stmt->execute([$hash, $email]);
    }

    $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
    $stmt->execute([$reset['id']]);

    $db->commit();

    Logger::activity('password_reset', 0, ['email' => $email]);

    Response::success(null, 'Contrasena actualizada correctamente');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::exception($e);
    Response::serverError('Error al restablecer contrasena');
}
