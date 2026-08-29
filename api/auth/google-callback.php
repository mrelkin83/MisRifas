<?php
/**
 * Google OAuth Callback
 * Maneja la respuesta de Google después de la autenticación
 */

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (getenv('APP_ENV') ?: 'development') === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Auth.php';

// Configuración de Google OAuth
$GOOGLE_CLIENT_ID = getenv('GOOGLE_CLIENT_ID') ?: 'TU_GOOGLE_CLIENT_ID_AQUI';
$GOOGLE_CLIENT_SECRET = getenv('GOOGLE_CLIENT_SECRET') ?: 'TU_GOOGLE_CLIENT_SECRET_AQUI';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$GOOGLE_REDIRECT_URI = $protocol . '://' . $host . BASE_PATH . '/api/auth/google-callback.php';

// Verificar estado (seguridad CSRF)
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: ' . BASE_PATH . '/public/admin/index.php?auth=login&error=invalid_state');
    exit;
}

// Verificar código de autorización
if (!isset($_GET['code'])) {
    header('Location: ' . BASE_PATH . '/public/admin/index.php?auth=login&error=no_code');
    exit;
}

$code = $_GET['code'];

try {
    // Intercambiar código por token de acceso
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $code,
        'client_id' => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenInfo = json_decode($response, true);

    if (!isset($tokenInfo['access_token'])) {
        throw new Exception('No se pudo obtener el token de acceso');
    }

    // Obtener información del usuario de Google
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenInfo['access_token']]);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($userInfoResponse, true);

    if (!isset($userInfo['email'])) {
        throw new Exception('No se pudo obtener información del usuario');
    }

    // Solo aceptar correos verificados por Google: sin esto, alguien con una
    // cuenta Google cuyo email (no verificado) coincida con el de un vendor
    // existente podría entrar a esa cuenta al hacer login social.
    if (isset($userInfo['verified_email']) && $userInfo['verified_email'] !== true) {
        throw new Exception('El correo de Google no está verificado');
    }

    // Conectar a la base de datos. Se autentica contra `vendors` -
    // la misma tabla que usa Auth.php para todo lo demas - en vez de
    // `admin_users`, que tiene su propio autoincremento independiente
    // y no comparte identidad real con `vendors` (ver hallazgo C5/H1
    // de la auditoria: escribir aqui en `admin_users` producia colision
    // de IDs entre tenants, ademas de que ese INSERT referenciaba
    // columnas -first_name/last_name/is_active/oauth_provider/oauth_id-
    // que no existen en el schema real, asi que este flujo jamas
    // funciono).
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM vendors WHERE email = ? LIMIT 1");
    $stmt->execute([$userInfo['email']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        $firstName = $userInfo['given_name'] ?? '';
        $lastName = $userInfo['family_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: ($userInfo['name'] ?? 'Vendor Google');

        $slugBase = strtolower(preg_replace('/[^a-z0-9]+/', '-', transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $fullName)));
        $slug = trim($slugBase, '-') . '-' . rand(1000, 9999);

        // Google no entrega telefono con el scope basico - se deja un
        // placeholder y el vendor lo completa despues desde su perfil.
        $insertStmt = $db->prepare("
            INSERT INTO vendors (slug, business_name, email, password_hash, phone, role, status, payment_config, email_verified_at, created_at)
            VALUES (?, ?, ?, '', '0000000000', 'vendor', 'active', '{\"mode\":\"manual\"}', NOW(), NOW())
        ");
        $insertStmt->execute([$slug, $fullName, $userInfo['email']]);
        $vendorId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($vendor['status'] !== 'active') {
        throw new Exception('Cuenta suspendida o pendiente de verificacion');
    }

    // Emitir un auth_token real, compatible con Auth::requireVendor()
    // (Bearer token) - el mismo mecanismo que usa el login normal.
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $db->prepare("UPDATE vendors SET auth_token = ?, auth_token_expires = ?, last_login = NOW() WHERE id = ?");
    $stmt->execute([Auth::hashToken($token), $expires, $vendor['id']]);

    $_SESSION['misrifas_token'] = $token;
    $_SESSION['misrifas_user'] = [
        'id' => $vendor['id'],
        'email' => $vendor['email'],
        'full_name' => $vendor['business_name'],
        'role' => $vendor['role']
    ];

    // Redirigir al panel de admin con script para guardar en localStorage
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Autenticación exitosa</title>
    </head>
    <body>
        <script>
            localStorage.setItem('misrifas_token', '<?= htmlspecialchars($token) ?>');
            localStorage.setItem('misrifas_user', '<?= htmlspecialchars(json_encode($_SESSION['misrifas_user'])) ?>');
            window.location.href = '<?= BASE_PATH ?>/public/admin/index.php';
        </script>
    </body>
    </html>
    <?php
    exit;

} catch (Exception $e) {
    error_log('Google OAuth Error: ' . $e->getMessage());
    header('Location: ' . BASE_PATH . '/public/admin/index.php?auth=login&error=oauth_failed');
    exit;
}
