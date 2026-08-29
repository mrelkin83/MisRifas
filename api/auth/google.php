<?php
/**
 * Google OAuth Login
 * Redirige al usuario a Google para autenticación
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

// Configuración de Google OAuth (DEBES CONFIGURAR ESTAS CREDENCIALES)
// Obtén tus credenciales en: https://console.cloud.google.com/apis/credentials
$GOOGLE_CLIENT_ID = getenv('GOOGLE_CLIENT_ID') ?: 'TU_GOOGLE_CLIENT_ID_AQUI';
$GOOGLE_CLIENT_SECRET = getenv('GOOGLE_CLIENT_SECRET') ?: 'TU_GOOGLE_CLIENT_SECRET_AQUI';

// Detectar el dominio actual
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$GOOGLE_REDIRECT_URI = $protocol . '://' . $host . BASE_PATH . '/api/auth/google-callback.php';

// Si no hay credenciales reales configuradas, no mandar al usuario a la
// página de error 400 de Google con un client_id placeholder: volver al
// login con un mensaje claro.
if ($GOOGLE_CLIENT_ID === '' || $GOOGLE_CLIENT_ID === 'TU_GOOGLE_CLIENT_ID_AQUI') {
    header('Location: ' . BASE_PATH . '/public/admin/index.php?auth=login&error=google_no_configurado');
    exit;
}

// Generar estado único para seguridad (prevenir CSRF)
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// URL de autorización de Google
$params = [
    'client_id' => $GOOGLE_CLIENT_ID,
    'redirect_uri' => $GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'offline',
    'prompt' => 'consent'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// Redirigir al usuario a Google
header('Location: ' . $authUrl);
exit;
