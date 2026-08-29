<?php
/**
 * Facebook OAuth Login
 * Redirige al usuario a Facebook para autenticación
 */

require_once __DIR__ . '/../../config/paths.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (getenv('APP_ENV') ?: 'development') === 'production',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Configuración de Facebook OAuth (DEBES CONFIGURAR ESTAS CREDENCIALES)
// Obtén tus credenciales en: https://developers.facebook.com/apps/
$FACEBOOK_APP_ID = getenv('FACEBOOK_APP_ID') ?: 'TU_FACEBOOK_APP_ID_AQUI';
$FACEBOOK_APP_SECRET = getenv('FACEBOOK_APP_SECRET') ?: 'TU_FACEBOOK_APP_SECRET_AQUI';

// Detectar el dominio actual
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$FACEBOOK_REDIRECT_URI = $protocol . '://' . $host . BASE_PATH . '/api/auth/facebook-callback.php';

// Sin credenciales reales configuradas, no mandar al usuario al error de
// Facebook con un app_id placeholder: volver al login con un mensaje claro.
if ($FACEBOOK_APP_ID === '' || $FACEBOOK_APP_ID === 'TU_FACEBOOK_APP_ID_AQUI') {
    header('Location: ' . BASE_PATH . '/public/admin/index.php?auth=login&error=facebook_no_configurado');
    exit;
}

// Generar estado único para seguridad (prevenir CSRF)
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// URL de autorización de Facebook
$params = [
    'client_id' => $FACEBOOK_APP_ID,
    'redirect_uri' => $FACEBOOK_REDIRECT_URI,
    'state' => $state,
    'scope' => 'email,public_profile',
    'response_type' => 'code'
];

$authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);

// Redirigir al usuario a Facebook
header('Location: ' . $authUrl);
exit;
