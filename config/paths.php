<?php
/**
 * Configuración de rutas del proyecto
 * Detecta automáticamente si está en subdirectorio o en raíz
 */

// Los avisos "deprecated"/notice que PHP imprime por defecto (ej. parámetros
// nullable implícitos, muy comunes en este código bajo PHP 8.1+) quedaban
// mezclados con el JSON de cada respuesta de la API - el navegador nunca
// podía parsear response.json() y el contenido dinámico (rifas, loterías,
// etc.) simplemente no cargaba, en silencio. Los errores reales siguen
// registrándose en el log de PHP; nunca deben imprimirse en el output de
// un endpoint que promete JSON.
if (!defined('MISRIFAS_ERROR_DISPLAY_CONFIGURED')) {
    define('MISRIFAS_ERROR_DISPLAY_CONFIGURED', true);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Cargar .env si no está cargado
if (!function_exists('loadEnvForPaths')) {
    function loadEnvForPaths() {
        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorar comentarios
            if (str_starts_with($line, '#')) {
                continue;
            }
            
            // Solo procesar líneas con CLAVE=VALOR
            if (!str_contains($line, '=')) {
                continue;
            }
            
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            
            // Solo setear si no está ya definida
            if (!getenv($key)) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Cargar variables de entorno
loadEnvForPaths();

// Detectar el directorio base del proyecto
// Prioridad: Variable de entorno > Auto-detección
$basePath = getenv('BASE_PATH');

if ($basePath === false || $basePath === '') {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $basePath = '';

    if (preg_match('#^(/[^/]+)/public/#', $scriptName, $matches)) {
        $basePath = $matches[1];
    } elseif (preg_match('#^(/[^/]+)/#', $scriptName, $matches)) {
        if ($matches[1] !== '/public') {
            $basePath = $matches[1];
        }
    }
}

// Normalizar: si BASE_PATH es '/', convertir a ''
if ($basePath === '/') {
    $basePath = '';
}

// Definir constantes globales (solo si no están definidas)
if (!defined('BASE_PATH')) define('BASE_PATH', $basePath);
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', $basePath . '/public');
if (!defined('API_PATH')) define('API_PATH', $basePath . '/api');
if (!defined('ASSETS_PATH')) define('ASSETS_PATH', PUBLIC_PATH . '/assets');

// Función helper para generar URLs (para rutas manejadas por el router)
function url($path = '') {
    return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

// Alias de url() para compatibilidad con código existente
function public_url($path = '') {
    return url($path);
}

function api_url($path = '') {
    return API_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

function asset_url($path = '') {
    return PUBLIC_PATH . '/assets' . ($path ? '/' . ltrim($path, '/') : '');
}
