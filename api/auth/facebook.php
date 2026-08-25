<?php
/**
 * Facebook OAuth Login
 * Redirige al usuario a Facebook para autenticación
 */

session_start();

// Configuración de Facebook OAuth (DEBES CONFIGURAR ESTAS CREDENCIALES)
// Obtén tus credenciales en: https://developers.facebook.com/apps/
$FACEBOOK_APP_ID = getenv('FACEBOOK_APP_ID') ?: 'TU_FACEBOOK_APP_ID_AQUI';
$FACEBOOK_APP_SECRET = getenv('FACEBOOK_APP_SECRET') ?: 'TU_FACEBOOK_APP_SECRET_AQUI';

// Detectar el dominio actual
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$FACEBOOK_REDIRECT_URI = $protocol . '://' . $host . '/MisRifas/api/auth/facebook-callback.php';

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
