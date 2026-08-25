<?php
/**
 * Facebook OAuth Callback
 * Maneja la respuesta de Facebook después de la autenticación
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($userInfoResponse, true);

    if (!isset($userInfo['id'])) {
        throw new Exception('No se pudo obtener información del usuario');
    }

    // Email puede no estar disponible si el usuario no lo permite
    $email = $userInfo['email'] ?? ('facebook_' . $userInfo['id'] . '@misrifas.local');

    // Conectar a la base de datos
    $db = Database::getInstance()->getConnection();

    // Verificar si el usuario ya existe
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE email = ? OR oauth_id = ? LIMIT 1");
    $stmt->execute([$email, $userInfo['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Crear nuevo usuario
        $firstName = $userInfo['first_name'] ?? '';
        $lastName = $userInfo['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: $userInfo['name'] ?? 'Usuario Facebook';

        $insertStmt = $db->prepare("
            INSERT INTO admin_users (
                email, password_hash, first_name, last_name, full_name,
                phone, department, city, document_id, role, is_active,
                oauth_provider, oauth_id, created_at
            ) VALUES (?, '', ?, ?, ?, '', '', '', '', 'vendor', 1, 'facebook', ?, NOW())
        ");

        $insertStmt->execute([
            $email,
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
                SET oauth_provider = 'facebook', oauth_id = ?, updated_at = NOW()
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
    error_log('Facebook OAuth Error: ' . $e->getMessage());
    header('Location: /MisRifas/public/admin/index.php?auth=login&error=oauth_failed');
    exit;
}
