<?php
/**
 * MisRifas - Punto de Entrada
 * Enruta las peticiones al frontend o API
 */

define('ROOT_PATH', __DIR__);
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Cargar configuración de entorno
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!getenv($key)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Configurar timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Bogota');

// Obtener la URI solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// Eliminar el base path
$envBasePath = getenv('BASE_PATH');
if ($envBasePath === false || $envBasePath === '') {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = ($scriptDir && $scriptDir !== '/' && $scriptDir !== '\\') ? $scriptDir : '';
} else {
    $basePath = trim(parse_url(getenv('APP_URL') ?: '/', PHP_URL_PATH), '/');
}
if ($basePath) {
    $requestUri = preg_replace('#^' . preg_quote($basePath, '#') . '#', '', $requestUri, 1);
}
$requestUri = trim($requestUri, '/');

// Archivos estáticos en public/
$staticPatterns = ['css/', 'js/', 'assets/', 'uploads/', 'images/'];
foreach ($staticPatterns as $pattern) {
    if (strpos($requestUri, $pattern) === 0) {
        // Resolver la ruta real y exigir que quede DENTRO de PUBLIC_PATH.
        // Sin esto, "../" en la URI permite leer cualquier archivo legible
        // por el proceso web (.env, config, etc. - path traversal/LFI).
        $publicRealPath = realpath(PUBLIC_PATH);
        $requestedPath = PUBLIC_PATH . '/' . $requestUri;
        $realFile = realpath($requestedPath);

        if (
            $realFile === false ||
            $publicRealPath === false ||
            strpos($realFile, $publicRealPath . DIRECTORY_SEPARATOR) !== 0
        ) {
            http_response_code(404);
            exit;
        }

        $ext = pathinfo($realFile, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($realFile);
        exit;
    }
}

// Rutas de API
if (strpos($requestUri, 'api/') === 0) {
    // Solo permitir segmentos de ruta seguros (letras, numeros, guiones,
    // barras) antes de resolver a un archivo - bloquea "../" y similares
    // por si este bloque llega a ejecutarse sin la exclusion de .htaccess.
    $apiPath = str_replace('api/', '', $requestUri);
    if (!preg_match('#^[a-zA-Z0-9_\-/]+$#', $apiPath) || strpos($apiPath, '..') !== false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'API endpoint not found']);
        exit;
    }
    $apiParts = explode('/', $apiPath);
    $apiRoot = realpath(ROOT_PATH . '/api');

    $apiFile = ROOT_PATH . '/api/' . $apiPath . '.php';
    $realApiFile = realpath($apiFile);
    if ($realApiFile !== false && $apiRoot !== false && strpos($realApiFile, $apiRoot . DIRECTORY_SEPARATOR) === 0) {
        require $realApiFile;
        exit;
    }

    $dir = ROOT_PATH . '/api/' . $apiParts[0];
    if (is_dir($dir)) {
        $file = $dir . '/' . ($apiParts[1] ?? 'index') . '.php';
        $realFile = realpath($file);
        if ($realFile !== false && $apiRoot !== false && strpos($realFile, $apiRoot . DIRECTORY_SEPARATOR) === 0) {
            require $realFile;
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found']);
    exit;
}

// Rutas PHP del frontend
$routes = [
    '' => 'index.php',
    'admin' => 'admin/index.php',
    'raffle' => 'raffle.php',
    'payment' => 'payment.php',
    'mis-boletos' => 'mis-boletos.php',
    'ganadores' => 'ganadores.php',
    'recover' => 'recover.php',
    'register' => 'register.php',
    'perfil' => 'perfil.php',
    'que-es' => 'que-es.php'
];

$baseRoute = explode('/', $requestUri)[0] ?? '';

// Manejar query strings
if (preg_match('/^admin(\.php)?\?/', $requestUri)) {
    require PUBLIC_PATH . '/admin/index.php';
    exit;
}
if (preg_match('/^raffle(\.php)?\?/', $requestUri)) {
    require PUBLIC_PATH . '/raffle.php';
    exit;
}
if (preg_match('/^payment(\.php)?\?/', $requestUri)) {
    require PUBLIC_PATH . '/payment.php';
    exit;
}
if (preg_match('/^mis-boletos(\.php)?\?/', $requestUri)) {
    require PUBLIC_PATH . '/mis-boletos.php';
    exit;
}

if (isset($routes[$baseRoute])) {
    require PUBLIC_PATH . '/' . $routes[$baseRoute];
    exit;
}

require PUBLIC_PATH . '/index.php';
