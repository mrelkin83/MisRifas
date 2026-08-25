<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Script de debug: solo linea de comandos.');
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de carga de .env</h1><pre>";

echo "=== Test 1: Valor inicial ===\n";
echo "getenv('BASE_PATH') inicial: " . var_export(getenv('BASE_PATH'), true) . "\n\n";

$_SERVER['SCRIPT_NAME'] = '/tapazo/index.php';

echo "=== Test 2: Simular loadEnvForPaths() ===\n";
$envFile = __DIR__ . '/.env';
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

echo "Total líneas en .env: " . count($lines) . "\n\n";

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    $line = trim($line);
    
    if (str_starts_with($line, '#')) continue;
    if (!str_contains($line, '=')) continue;
    
    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value, " \t\n\r\0\x0B\"'");
    
    if ($key === 'BASE_PATH') {
        echo "--- Encontrado BASE_PATH en línea $lineNum ---\n";
        echo "Línea completa: [$line]\n";
        echo "Key: [$key]\n";
        echo "Value: [$value]\n";
        echo "getenv('BASE_PATH') antes: " . var_export(getenv('BASE_PATH'), true) . "\n";
        echo "Condición !getenv('BASE_PATH'): " . var_export(!getenv('BASE_PATH'), true) . "\n";
        
        if (!getenv('BASE_PATH')) {
            putenv("BASE_PATH=$value");
            $_ENV['BASE_PATH'] = $value;
            $_SERVER['BASE_PATH'] = $value;
            echo "✓ BASE_PATH CARGADO\n";
        } else {
            echo "✗ BASE_PATH YA ESTABA DEFINIDO - NO SE CARGÓ DEL .ENV\n";
        }
        
        echo "getenv('BASE_PATH') después: " . var_export(getenv('BASE_PATH'), true) . "\n";
        break;
    }
}

echo "\n=== Test 3: Cargar paths.php completo ===\n";
require_once __DIR__ . '/config/paths.php';
echo "BASE_PATH constante: " . BASE_PATH . "\n";

echo "</pre>";
?>
