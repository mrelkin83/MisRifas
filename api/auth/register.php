<?php

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
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', null, 405);
}

// Limitar registros por IP para frenar creacion masiva de cuentas
if (!RateLimiter::check('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 10)) {
    Response::rateLimitExceeded('Demasiados registros desde esta conexion. Intenta de nuevo mas tarde.');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !is_array($input)) {
        Response::error('Datos invalidos');
    }

    $name = trim($input['name'] ?? '');
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $dept = trim($input['department'] ?? '');
    $city = trim($input['city'] ?? '');
    $documentId = trim($input['document_id'] ?? '');
    $role = trim($input['role'] ?? 'buyer');

    if (empty($name) && !empty($firstName)) {
        $name = trim($firstName . ' ' . $lastName);
    }

    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        Response::error('Nombre, email, telefono y contrasena son requeridos', null, 400);
    }

    if (strlen($password) < 6) {
        Response::error('La contrasena debe tener al menos 6 caracteres', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id FROM vendors WHERE email = ? UNION SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email, $email]);
    if ($stmt->fetch()) {
        Response::error('El correo electronico ya esta registrado', null, 409);
    }

    $stmt = $db->prepare("SELECT id FROM vendors WHERE phone = ? UNION SELECT id FROM users WHERE phone_whatsapp = ?");
    $stmt->execute([$phone, $phone]);
    if ($stmt->fetch()) {
        Response::error('El telefono ya esta registrado', null, 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $authToken = bin2hex(random_bytes(32));

    if ($role === 'vendor') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name)));
        $slug = trim($slug, '-') . '-' . rand(100, 999);

        $stmt = $db->prepare("SELECT id FROM vendors WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug .= '-' . time();
        }

        $stmt = $db->prepare("
            INSERT INTO vendors (slug, business_name, document_number, email, password_hash, phone, city, department, auth_token, auth_token_expires, role, status, payment_config, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 'vendor', 'active', '{\"mode\":\"manual\"}', NOW())
        ");
        $stmt->execute([$slug, $name, $documentId ?: null, $email, $passwordHash, $phone, $city ?: null, $dept ?: null, Auth::hashToken($authToken)]);
        $userId = $db->lastInsertId();
        $finalRole = 'vendor';

        Logger::activity('vendor_registration', $userId, ['email' => $email, 'slug' => $slug]);
    } else {
        $uniqueId = bin2hex(random_bytes(16));

        $stmt = $db->prepare("
            INSERT INTO users (unique_id, name, phone_whatsapp, email, password_hash, auth_token, department, city, role, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'buyer', 1, NOW(), NOW())
        ");
        $stmt->execute([$uniqueId, $name, $phone, $email, $passwordHash, Auth::hashToken($authToken), $dept ?: null, $city ?: null]);
        $userId = $db->lastInsertId();
        $finalRole = 'buyer';

        Logger::activity('buyer_registration', $userId, ['email' => $email]);
    }

    Response::success([
        'token' => $authToken,
        'user' => [
            'id' => $userId,
            'username' => $name,
            'email' => $email,
            'full_name' => $name,
            'role' => $finalRole,
            'phone' => $phone,
            'source' => $finalRole === 'vendor' ? 'vendor' : 'buyer'
        ]
    ], 'Registro exitoso');

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al registrar usuario');
}
