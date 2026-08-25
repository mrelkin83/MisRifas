<?php
/**
 * Clase Logger
 * Sistema de logging de la aplicación
 */

class Logger
{
    private const LEVEL_DEBUG = 'DEBUG';
    private const LEVEL_INFO = 'INFO';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_ERROR = 'ERROR';
    private const LEVEL_CRITICAL = 'CRITICAL';

    private static $config = null;

    /**
     * Cargar configuración
     */
    private static function loadConfig(): void
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../../config/app.php';
        }
    }

    /**
     * Log debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log critical
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log exception
     */
    public static function exception(Throwable $e, array $context = []): void
    {
        $message = sprintf(
            '%s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        $context['exception'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        self::error($message, $context);
    }

    /**
     * Log principal
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        self::loadConfig();

        // Verificar nivel de log configurado
        $configLevel = strtoupper(self::$config['logs']['level'] ?? 'INFO');
        if (!self::shouldLog($level, $configLevel)) {
            return;
        }

        // Formatear mensaje
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] {$level}: {$message}";

        if ($contextString) {
            $logMessage .= " | Context: {$contextString}";
        }

        $logMessage .= PHP_EOL;

        // Determinar archivo de log
        $logFile = self::getLogFile($level);

        // Escribir a archivo
        self::writeToFile($logFile, $logMessage);

        // En desarrollo, también mostrar en error_log de PHP
        if (self::$config['app']['debug']) {
            error_log($logMessage);
        }
    }

    /**
     * Determinar si se debe loggear según nivel
     */
    private static function shouldLog(string $level, string $configLevel): bool
    {
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4,
        ];

        return ($levels[$level] ?? 0) >= ($levels[$configLevel] ?? 1);
    }

    /**
     * Obtener archivo de log según nivel
     */
    private static function getLogFile(string $level): string
    {
        self::loadConfig();

        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            return self::$config['logs']['error_log'];
        }

        return self::$config['logs']['app_log'];
    }

    /**
     * Escribir a archivo
     */
    private static function writeToFile(string $filepath, string $message): void
    {
        // Crear directorio si no existe
        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Escribir con file locking
        file_put_contents($filepath, $message, FILE_APPEND | LOCK_EX);

        // Rotar log si es muy grande (> 10MB)
        if (file_exists($filepath) && filesize($filepath) > 10485760) {
            self::rotateLog($filepath);
        }
    }

    /**
     * Rotar archivo de log
     */
    private static function rotateLog(string $filepath): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $rotatedPath = $filepath . '.' . $timestamp;
        rename($filepath, $rotatedPath);

        // Comprimir log antiguo
        if (function_exists('gzopen')) {
            $gz = gzopen($rotatedPath . '.gz', 'w9');
            gzwrite($gz, file_get_contents($rotatedPath));
            gzclose($gz);
            unlink($rotatedPath);
        }
    }

    /**
     * Log de actividad de usuario
     */
    public static function activity(string $action, $userId, array $details = []): void
    {
        $context = [
            'user_id' => $userId,
            'action' => $action,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details,
        ];

        self::info("User Activity: {$action}", $context);
    }

    /**
     * Log de pago
     */
    public static function payment(string $status, array $details): void
    {
        $context = [
            'status' => $status,
            'details' => $details,
            'ip' => self::getClientIP(),
        ];

        self::info("Payment: {$status}", $context);
    }

    /**
     * Log de API externa
     */
    public static function externalAPI(string $service, string $action, bool $success, array $details = []): void
    {
        $level = $success ? self::LEVEL_INFO : self::LEVEL_ERROR;
        $status = $success ? 'SUCCESS' : 'FAILED';

        $context = [
            'service' => $service,
            'action' => $action,
            'success' => $success,
            'details' => $details,
        ];

        $message = "External API [{$service}] {$action}: {$status}";
        self::log($level, $message, $context);
    }

    /**
     * Log de base de datos
     */
    public static function database(string $query, array $params = [], ?Throwable $exception = null): void
    {
        if ($exception) {
            self::error("Database Error: {$query}", [
                'query' => $query,
                'params' => $params,
                'error' => $exception->getMessage(),
            ]);
        } else {
            self::debug("Database Query: {$query}", [
                'params' => $params,
            ]);
        }
    }

    /**
     * Log de cron job
     */
    public static function cron(string $jobName, bool $success, array $details = []): void
    {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $level = $success ? self::LEVEL_INFO : self::LEVEL_ERROR;

        $context = [
            'job' => $jobName,
            'success' => $success,
            'details' => $details,
            'execution_time' => $details['execution_time'] ?? null,
        ];

        self::log($level, "Cron Job [{$jobName}]: {$status}", $context);
    }

    /**
     * Obtener IP del cliente
     */
    private static function getClientIP(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }

    /**
     * Limpiar logs antiguos (> 30 días)
     */
    public static function cleanup(int $days = 30): int
    {
        self::loadConfig();
        $logsDir = dirname(self::$config['logs']['app_log']);
        $deletedCount = 0;

        if (!is_dir($logsDir)) {
            return 0;
        }

        $files = glob($logsDir . '/*.log.*');
        $cutoffTime = time() - ($days * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
            }
        }

        self::info("Log cleanup completed", ['deleted_files' => $deletedCount]);

        return $deletedCount;
    }
}
