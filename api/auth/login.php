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
require_once __DIR__ . '/../../api/utils/Auth.php';

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
        $stmt->execute([Auth::hashToken($token), $expires, $vendor['id']]);

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
                'source' => 'vendor',
                'verified' => !empty($vendor['email_verified_at']) || !empty($vendor['phone_verified_at'])
            ]
        ], 'Login exitoso');
    }

    // 2. Buscar en users (compradores)
    $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR phone_whatsapp = ?)");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
        // Unificación: cualquier usuario puede organizar rifas. Se asegura/crea
        // su identidad de vendedor y se inicia sesión en el panel de organizador
        // (que también muestra sus boletas compradas). Defensivo: si no se puede
        // provisionar (p. ej. sin email), cae al panel de comprador y el login
        // nunca se rompe.
        $provVendor = null;
        try {
            $provVendor = ensureVendorForUser($db, $user);
        } catch (\Throwable $e) {
            Logger::warning('No se pudo provisionar vendor para user ' . $user['id'] . ': ' . $e->getMessage());
        }

        if ($provVendor) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $db->prepare("UPDATE vendors SET auth_token = ?, auth_token_expires = ?, last_login = NOW() WHERE id = ?");
            $stmt->execute([Auth::hashToken($token), $expires, $provVendor['id']]);
            Logger::activity('user_login_as_vendor', $provVendor['id'], ['email' => $provVendor['email'], 'from_user' => $user['id']]);

            $_SESSION['user_id'] = $provVendor['id'];
            $_SESSION['user_email'] = $provVendor['email'];
            $_SESSION['user_role'] = $provVendor['role'];
            session_write_close();

            Response::success([
                'token' => $token,
                'expires_at' => $expires,
                'user' => [
                    'id' => $provVendor['id'],
                    'username' => $provVendor['slug'],
                    'email' => $provVendor['email'],
                    'full_name' => $provVendor['business_name'],
                    'role' => $provVendor['role'],
                    'phone' => $provVendor['phone'],
                    'slug' => $provVendor['slug'],
                    'source' => 'vendor',
                    'verified' => !empty($provVendor['email_verified_at']) || !empty($provVendor['phone_verified_at'])
                ]
            ], 'Login exitoso');
        }

        // Fallback: sesión de comprador (no se pudo provisionar organizador).
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $db->prepare("UPDATE users SET auth_token = ?, auth_token_expires = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([Auth::hashToken($token), $expires, $user['id']]);

        Logger::activity('buyer_login', $user['id'], ['email' => $user['email']]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = 'buyer';
        session_write_close();

        Response::success([
            'token' => $token,
            'expires_at' => $expires,
            'user' => [
                'id' => $user['id'],
                'username' => $user['name'],
                'email' => $user['email'],
                'full_name' => $user['name'],
                'role' => 'buyer',
                'phone' => $user['phone_whatsapp'] ?? '',
                'source' => 'buyer',
                'verified' => !empty($user['email_verified_at']) || !empty($user['phone_verified_at'])
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

/**
 * Unificación de cuentas: garantiza que un usuario (comprador) tenga una
 * identidad de vendedor/organizador para poder crear rifas. Idempotente:
 * si ya existe un vendor con ese email/teléfono lo devuelve; si no, lo
 * provisiona. Devuelve null si no se puede (sin email, o vendor no activo).
 */
function ensureVendorForUser(PDO $db, array $user): ?array
{
    $email = trim((string)($user['email'] ?? ''));
    $phone = trim((string)($user['phone_whatsapp'] ?? ''));

    // vendors.email es NOT NULL: sin email no se puede provisionar.
    if ($email === '') return null;

    $stmt = $db->prepare("SELECT * FROM vendors WHERE email = ? OR (phone <> '' AND phone = ?) LIMIT 1");
    $stmt->execute([$email, $phone]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($vendor) {
        return (($vendor['status'] ?? '') === 'active') ? $vendor : null;
    }

    // Generar un slug único a partir del nombre.
    $name = trim((string)($user['name'] ?? '')) ?: 'Organizador';
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'organizador';
    $slug = $base;
    $i = 1;
    $chk = $db->prepare("SELECT 1 FROM vendors WHERE slug = ?");
    do {
        $chk->execute([$slug]);
        $taken = (bool)$chk->fetchColumn();
        if ($taken) { $slug = $base . '-' . (++$i); }
    } while ($taken && $i < 100);

    // La identidad de organizador HEREDA la verificación OTP del usuario:
    // un usuario grandfathered/verificado no repite la verificación, pero un
    // registro nuevo sin verificar no la esquiva por esta vía.
    $ins = $db->prepare("INSERT INTO vendors
        (slug, business_name, email, password_hash, phone, role, status, payment_config, email_verified_at, phone_verified_at, created_at)
        VALUES (?, ?, ?, ?, ?, 'vendor', 'active', '{\"mode\":\"manual\"}', ?, ?, NOW())");
    $ins->execute([$slug, $name, $email, $user['password_hash'], $phone,
        $user['email_verified_at'] ?? null, $user['phone_verified_at'] ?? null]);
    $newId = (int)$db->lastInsertId();

    $sel = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $sel->execute([$newId]);
    return $sel->fetch(PDO::FETCH_ASSOC) ?: null;
}
