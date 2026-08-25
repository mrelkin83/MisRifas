<?php
/**
 * Script de prueba de rutas
 */

echo "<h1>Test de Rutas - MisRifas</h1>";

// Simular variables
$_SERVER['REQUEST_URI'] = '/MisRifas/ganadores';
$_SERVER['SCRIPT_NAME'] = '/MisRifas/index.php';

require_once __DIR__ . '/config/paths.php';

echo "<h2>Configuración:</h2>";
echo "<pre>";
echo "APP_URL: " . (getenv('APP_URL') ?: 'no definido') . "\n";
echo "BASE_PATH: " . BASE_PATH . "\n";
echo "PUBLIC_PATH: " . PUBLIC_PATH . "\n";
echo "API_PATH: " . API_PATH . "\n";
echo "</pre>";

echo "<h2>Funciones helper:</h2>";
echo "<pre>";
echo "url(''): " . url('') . "\n";
echo "url('ganadores'): " . url('ganadores') . "\n";
echo "url('mis-boletos'): " . url('mis-boletos') . "\n";
echo "public_url('index.php'): " . public_url('index.php') . "\n";
echo "api_url('raffles'): " . api_url('raffles') . "\n";
echo "</pre>";

echo "<h2>Enlaces del menú:</h2>";
echo "<ul>";
echo "<li><a href='" . url('') . "'>Inicio</a></li>";
echo "<li><a href='" . url('tapazo') . "'>El Tapazo</a></li>";
echo "<li><a href='" . url('mis-boletos') . "'>Consultar Boletas</a></li>";
echo "<li><a href='" . url('ganadores') . "'>Ganadores</a></li>";
echo "<li><a href='" . url('que-es') . "'>¿Qué es MisRifas?</a></li>";
echo "<li><a href='" . url('register') . "'>Crear Cuenta</a></li>";
echo "</ul>";

echo "<h2>Procesamiento de URI:</h2>";
$requestUri = $_SERVER['REQUEST_URI'];
$requestUri = parse_url($requestUri, PHP_URL_PATH);

$basePath = trim(parse_url(getenv('APP_URL') ?: '/', PHP_URL_PATH), '/');
echo "<pre>";
echo "REQUEST_URI original: " . $_SERVER['REQUEST_URI'] . "\n";
echo "REQUEST_URI limpio: " . $requestUri . "\n";
echo "basePath extraído: " . $basePath . "\n";

if ($basePath) {
    $requestUri = str_replace('/' . $basePath, '', $requestUri);
}
$requestUri = trim($requestUri, '/');

echo "REQUEST_URI final: " . $requestUri . "\n";
echo "</pre>";

echo "<h2>Verificación mod_rewrite:</h2>";
echo "<pre>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo in_array('mod_rewrite', $modules) ? "✅ mod_rewrite HABILITADO\n" : "❌ mod_rewrite DESHABILITADO\n";
} else {
    echo "⚠️ No se puede verificar (función apache_get_modules no disponible)\n";
}
echo "</pre>";
