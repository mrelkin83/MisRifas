<?php
/**
 * Cargador unico de variables de entorno (.env)
 * Reemplaza las 4 versiones duplicadas que existian en:
 *   - index.php (raiz)
 *   - config/database.php
 *   - config/paths.php
 *   - config/app.php
 */
function loadEnv(?string $envPath = null): void
{
    if ($envPath === null) {
        $envPath = dirname(__DIR__) . '/.env';
    }

    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!getenv($key)) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}
