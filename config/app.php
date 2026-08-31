<?php
/**
 * Configuración de Aplicación
 */

// Cargar variables de entorno desde .env
if (!function_exists('loadEnv')) {
    // El .env vive en la raíz del proyecto: un nivel arriba de config/.
    function loadEnv($path = __DIR__ . '/../.env')
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse line
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remover comillas si existen
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Cargar .env
loadEnv();

// paths.php configura la salida de errores (nunca al output de un endpoint
// JSON, ver comentario ahi) ademas de BASE_PATH - lo necesitan tanto los
// archivos que solo requieren app.php como los que solo requieren database.php.
require_once __DIR__ . '/paths.php';

// Configuración global
return [
    'app' => [
        'name' => getenv('APP_NAME') ?: 'MisRifas',
        'env' => getenv('APP_ENV') ?: 'development',
        'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
        'url' => getenv('APP_URL') ?: 'http://localhost',
        'timezone' => getenv('APP_TIMEZONE') ?: 'America/Bogota',
        'secret_key' => getenv('APP_SECRET_KEY') ?: '',
    ],

    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],

    'session' => [
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 86400),
        'cookie_name' => 'misrifas_session',
        'cookie_secure' => getenv('APP_ENV') === 'production',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ],

    'csrf' => [
        'token_lifetime' => (int)(getenv('CSRF_TOKEN_LIFETIME') ?: 3600),
    ],

    'payments' => [
        'wompi' => [
            'enabled' => filter_var(getenv('WOMPI_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
            'public_key' => getenv('WOMPI_PUBLIC_KEY') ?: '',
            'private_key' => getenv('WOMPI_PRIVATE_KEY') ?: '',
            'events_secret' => getenv('WOMPI_EVENTS_SECRET') ?: '',
            'checkout_url' => getenv('WOMPI_CHECKOUT_URL') ?: 'https://checkout.wompi.co',
            'api_url' => getenv('WOMPI_API_URL') ?: 'https://api.wompi.co',
            'redirect_url' => getenv('WOMPI_REDIRECT_URL') ?: '',
        ],
        'nequi' => [
            'enabled' => filter_var(getenv('NEQUI_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
            'api_url' => getenv('NEQUI_API_URL') ?: '',
            'api_key' => getenv('NEQUI_API_KEY') ?: '',
            'api_secret' => getenv('NEQUI_API_SECRET') ?: '',
            'webhook_secret' => getenv('NEQUI_WEBHOOK_SECRET') ?: '',
            'merchant_id' => getenv('NEQUI_MERCHANT_ID') ?: '',
        ],
        'mercadopago' => [
            'enabled' => filter_var(getenv('MERCADOPAGO_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
            'public_key' => getenv('MERCADOPAGO_PUBLIC_KEY') ?: '',
            'access_token' => getenv('MERCADOPAGO_ACCESS_TOKEN') ?: '',
            'webhook_secret' => getenv('MERCADOPAGO_WEBHOOK_SECRET') ?: '',
        ],
    ],

    'notifications' => [
        'whatsapp' => [
            'enabled' => filter_var(getenv('WHATSAPP_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
            'api_url' => getenv('WHATSAPP_API_URL') ?: '',
            'access_token' => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
            'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
            'business_account_id' => getenv('WHATSAPP_BUSINESS_ACCOUNT_ID') ?: '',
        ],
        'email' => [
            'enabled' => filter_var(getenv('EMAIL_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
            'smtp_host' => getenv('SMTP_HOST') ?: '',
            'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
            'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
            'smtp_user' => getenv('SMTP_USER') ?: '',
            'smtp_password' => getenv('SMTP_PASS') ?: '',
            // Sin dominio quemado: el respaldo se deriva del host de APP_URL
            // (el dominio final de producción aún no existe; el actual es de pruebas).
            'from_address' => getenv('EMAIL_FROM_ADDRESS') ?: ('no-reply@' . (parse_url(getenv('APP_URL') ?: '', PHP_URL_HOST) ?: 'localhost')),
            'from_name' => getenv('EMAIL_FROM_NAME') ?: '',
        ],
    ],

    'uploads' => [
        'max_size' => (int)(getenv('MAX_UPLOAD_SIZE') ?: 5242880), // 5MB
        'allowed_types' => explode(',', getenv('ALLOWED_IMAGE_TYPES') ?: 'jpg,jpeg,png,gif,webp'),
        'path' => __DIR__ . '/../../uploads',
    ],

    'tickets' => [
        'reservation_hours_default' => (int)(getenv('RESERVATION_HOURS_DEFAULT') ?: 2),
        'max_per_purchase' => (int)(getenv('MAX_TICKETS_PER_PURCHASE') ?: 10),
        'min_price' => (int)(getenv('MIN_TICKET_PRICE') ?: 1000),
        'max_price' => (int)(getenv('MAX_TICKET_PRICE') ?: 1000000),
    ],

    'rate_limit' => [
        'enabled' => filter_var(getenv('RATE_LIMIT_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
        'max_requests' => (int)(getenv('RATE_LIMIT_MAX_REQUESTS') ?: 60),
        'window_minutes' => (int)(getenv('RATE_LIMIT_WINDOW_MINUTES') ?: 1),
    ],

    'logs' => [
        'level' => getenv('LOG_LEVEL') ?: 'error',
        'app_log' => getenv('LOG_FILE_PATH') ?: __DIR__ . '/../logs/app.log',
        'error_log' => getenv('ERROR_LOG_FILE_PATH') ?: __DIR__ . '/../logs/error.log',
    ],

    'cache' => [
        'enabled' => filter_var(getenv('CACHE_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
        'driver' => getenv('CACHE_DRIVER') ?: 'file',
    ],

    'cron' => [
        'enabled' => filter_var(getenv('CRON_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
        'secret_key' => getenv('CRON_SECRET_KEY') ?: '',
    ],

    'meta' => [
        'title' => getenv('META_TITLE') ?: ((getenv('APP_NAME') ?: 'MisRifas') . ' - Rifas Digitales'),
        'description' => getenv('META_DESCRIPTION') ?: 'Plataforma de rifas digitales',
        'keywords' => getenv('META_KEYWORDS') ?: 'rifas,colombia,sorteos',
        'image' => getenv('META_IMAGE') ?: 'assets/images/og-default.jpg',
    ],
];
