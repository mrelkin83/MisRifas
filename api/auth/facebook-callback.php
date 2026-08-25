<?php
/**
 * Facebook OAuth Callback
 * Maneja la respuesta de Facebook después de la autenticación
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

// Configuración de Facebook OAuth
$FACEBOOK_APP_ID = getenv('FACEBOOK_APP_ID') ?: 'TU_FACEBOOK_APP_ID_AQUI';
$FACEBOOK_APP_SECRET = getenv('FACEBOOK_APP_SECRET') ?: 'TU_FACEBOOK_APP_SECRET_AQUI';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$FACEBOOK_REDIRECT_URI = $protocol . '://' . $host . '/MisRifas/api/auth/facebook-callback.php';

// Verificar estado (seguridad CSRF)
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: /MisRifas/public/admin/index.php?auth=login&error=invalid_state');
    exit;
}

// Verificar código de autorización
if (!isset($_GET['code'])) {
    header('Location: /MisRifas/public/admin/index.php?auth=login&error=no_code');
    exit;
}

$code = $_GET['code'];

try {
    // Intercambiar código por token de acceso
    $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
    $tokenParams = [
        'client_id' => $FACEBOOK_APP_ID,
        'client_secret' => $FACEBOOK_APP_SECRET,
        'redirect_uri' => $FACEBOOK_REDIRECT_URI,
        'code' => $code
    ];

    $ch = curl_init($tokenUrl . '?' . http_build_query($tokenParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenInfo = json_decode($response, true);

    if (!isset($tokenInfo['access_token'])) {
        throw new Exception('No se pudo obtener el token de acceso');
    }

    // Obtener información del usuario de Facebook
    $userInfoUrl = 'https://graph.facebook.com/v18.0/me';
    $userInfoParams = [
        'fields' => 'id,name,email,first_name,last_name',
        'access_token' => $tokenInfo['access_token']
    ];

    $ch = curl_init($userInfoUrl . '?' . http_build_query($userInfoParams));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($userInfoResponse, true);

    if (!isset($userInfo['id'])) {
        throw new Exception('No se pudo obtener información del usuario');
    }

    // Email puede no estar disponible si el usuario no lo permite
    $email = $userInfo['email'] ?? ('facebook_' . $userInfo['id'] . '@misrifas.local');

    // Conectar a la base de datos. Se autentica contra `vendors` (ver
    // nota extensa en google-callback.php: escribir en `admin_users`
    // aqui producia colision de IDs entre tenants y ademas ese INSERT
    // referenciaba columnas inexistentes en el schema real, asi que
    // este flujo jamas funciono).
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM vendors WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        $firstName = $userInfo['first_name'] ?? '';
        $lastName = $userInfo['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: ($userInfo['name'] ?? 'Vendor Facebook');

        $slugBase = strtolower(preg_replace('/[^a-z0-9]+/', '-', transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $fullName)));
        $slug = trim($slugBase, '-') . '-' . rand(1000, 9999);

        $insertStmt = $db->prepare("
            INSERT INTO vendors (slug, business_name, email, password_hash, phone, role, status, payment_config, email_verified_at, created_at)
            VALUES (?, ?, ?, '', '0000000000', 'vendor', 'active', '{\"mode\":\"manual\"}', NOW(), NOW())
        ");
        $insertStmt->execute([$slug, $fullName, $email]);
        $vendorId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($vendor['status'] !== 'active') {
        throw new Exception('Cuenta suspendida o pendiente de verificacion');
    }

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
            window.location.href = '/MisRifas/public/admin/index.php';
        </script>
    </body>
    </html>
    <?php
    exit;

} catch (Exception $e) {
    error_log('Facebook OAuth Error: ' . $e->getMessage());
    header('Location: /MisRifas/public/admin/index.php?auth=login&error=oauth_failed');
    exit;
}
