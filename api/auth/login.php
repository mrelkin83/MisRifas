<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (getenv('APP_ENV') ?: 'development') === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    session_write_close();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', null, 405);
}

// Limitar intentos de login por IP para frenar fuerza bruta/credential stuffing
if (!RateLimiter::check('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 5)) {
    Response::rateLimitExceeded('Demasiados intentos de inicio de sesion. Intenta de nuevo en unos minutos.');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !is_array($input)) {
        Response::error('Datos invalidos');
    }

    $identifier = trim($input['email'] ?? $input['identifier'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        Response::error('Email/telefono y contrasena son requeridos', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    // 1. Buscar en vendors (vendedores + super_admin)
    $stmt = $db->prepare("
        SELECT * FROM vendors
        WHERE (email = ? OR phone = ?) AND status = 'active'
    ");
    $stmt->execute([$identifier, $identifier]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor && password_verify($password, $vendor['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $db->prepare("UPDATE vendors SET auth_token = ?, auth_token_expires = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([$token, $expires, $vendor['id']]);

        Logger::activity('vendor_login', $vendor['id'], ['email' => $vendor['email'], 'role' => $vendor['role']]);

        $_SESSION['user_id'] = $vendor['id'];
        $_SESSION['user_email'] = $vendor['email'];
        $_SESSION['user_role'] = $vendor['role'];
        session_write_close();

        Response::success([
            'token' => $token,
            'expires_at' => $expires,
            'user' => [
                'id' => $vendor['id'],
                'username' => $vendor['slug'],
                'email' => $vendor['email'],
                'full_name' => $vendor['business_name'],
                'role' => $vendor['role'],
                'phone' => $vendor['phone'],
                'slug' => $vendor['slug'],
                'source' => 'vendor'
            ]
        ], 'Login exitoso');
    }

    // 2. Buscar en users (compradores)
    $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR phone_whatsapp = ?)");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
        $token = bin2hex(random_bytes(32));

        $stmt = $db->prepare("UPDATE users SET auth_token = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([$token, $user['id']]);

        Logger::activity('buyer_login', $user['id'], ['email' => $user['email']]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = 'buyer';
        session_write_close();

        Response::success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['name'],
                'email' => $user['email'],
                'full_name' => $user['name'],
                'role' => 'buyer',
                'phone' => $user['phone_whatsapp'] ?? '',
                'source' => 'buyer'
            ]
        ], 'Login exitoso');
    }

    if ($user && empty($user['password_hash'])) {
        Response::error('Esta cuenta no tiene contrasena. Usa el enlace de recuperacion.', null, 403);
    }

    Logger::warning('Login fallido', ['identifier' => $identifier]);
    Response::error('Email/telefono o contrasena incorrectos', null, 401);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al iniciar sesion');
}
