<?php
/**
 * API: Recuperar Contraseña
 * POST /api/auth/recover.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

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
    Response::error('Método no permitido', null, 405);
}

// Limitar solicitudes de recuperacion por IP (evita enumeracion/spam de emails)
if (!RateLimiter::check('recover_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 10)) {
    Response::rateLimitExceeded('Demasiadas solicitudes. Intenta de nuevo mas tarde.');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !is_array($input)) {
        Response::error('Datos inválidos. Envía JSON válido.');
    }

    $email = trim($input['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email válido requerido', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    // Buscar usuario en vendors (los que se registran hoy via el flujo SaaS)
    $stmt = $db->prepare("SELECT id, business_name FROM vendors WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Si no existe, buscar en admin_users (legacy)
    if (!$user) {
        $stmt = $db->prepare("SELECT id, full_name FROM admin_users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    // Si no existe, buscar en users (compradores)
    if (!$user) {
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    // Siempre retornar éxito para evitar enumeración de usuarios
    // Pero si existe, enviar el correo
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Guardar token de recuperación
        $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$email, $token, $expires]);

        // Aquí normalmente enviarías el correo
        // Por ahora, retornamos el token para pruebas
        Logger::activity('password_recovery_request', $user['id'] ?? 0, ['email' => $email]);
        
        // En producción, aquí enviarías el email con el enlace:
        // $resetLink = "https://misrifas.online/public/reset-password.php?token=$token";
        // mail($email, "Recuperar contraseña - MisRifas", "Haz clic aquí: $resetLink");
    }

    // Siempre retornar éxito
    Response::success([
        'message' => 'Si el email existe, recibirás un enlace de recuperación'
    ], 'Correo de recuperación enviado');

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar la solicitud');
}
