<?php
/**
 * Google OAuth Callback
 * Maneja la respuesta de Google después de la autenticación
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

// Configuración de Google OAuth
$GOOGLE_CLIENT_ID = getenv('GOOGLE_CLIENT_ID') ?: 'TU_GOOGLE_CLIENT_ID_AQUI';
$GOOGLE_CLIENT_SECRET = getenv('GOOGLE_CLIENT_SECRET') ?: 'TU_GOOGLE_CLIENT_SECRET_AQUI';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$GOOGLE_REDIRECT_URI = $protocol . '://' . $host . '/MisRifas/api/auth/google-callback.php';

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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($userInfoResponse, true);

    if (!isset($userInfo['email'])) {
        throw new Exception('No se pudo obtener información del usuario');
    }

    // Conectar a la base de datos
    $db = Database::getInstance()->getConnection();

    // Verificar si el usuario ya existe
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$userInfo['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Crear nuevo usuario
        $firstName = $userInfo['given_name'] ?? '';
        $lastName = $userInfo['family_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: $userInfo['name'] ?? 'Usuario Google';

        $insertStmt = $db->prepare("
            INSERT INTO admin_users (
                email, password_hash, first_name, last_name, full_name,
                phone, department, city, document_id, role, is_active,
                oauth_provider, oauth_id, created_at
            ) VALUES (?, '', ?, ?, ?, '', '', '', '', 'vendor', 1, 'google', ?, NOW())
        ");

        $insertStmt->execute([
            $userInfo['email'],
            $firstName,
            $lastName,
            $fullName,
            $userInfo['id']
        ]);

        $userId = $db->lastInsertId();

        // Obtener el usuario recién creado
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Actualizar OAuth info si no existe
        if (empty($user['oauth_provider'])) {
            $updateStmt = $db->prepare("
                UPDATE admin_users
                SET oauth_provider = 'google', oauth_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$userInfo['id'], $user['id']]);
        }
    }

    // Generar token de sesión
    $token = bin2hex(random_bytes(32));

    // Guardar token en sesión
    $_SESSION['misrifas_token'] = $token;
    $_SESSION['misrifas_user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role']
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
    error_log('Google OAuth Error: ' . $e->getMessage());
    header('Location: /MisRifas/public/admin/index.php?auth=login&error=oauth_failed');
    exit;
}
