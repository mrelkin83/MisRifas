<?php
/**
 * Clase Response
 * Maneja todas las respuestas HTTP de la API
 */

class Response
{
    /**
     * Enviar respuesta JSON exitosa
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Enviar respuesta JSON de error
     */
    public static function error(string $message = 'Error', $errors = null, int $statusCode = 400, string $errorCode = null): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Enviar respuesta JSON genérica
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // CORS headers
        $allowedOrigins = self::getAllowedOrigins();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Respuesta de validación fallida
     */
    public static function validationError(array $errors): void
    {
        self::error('Errores de validación', $errors, 422, ERROR_VALIDATION);
    }

    /**
     * Respuesta no autorizado
     */
    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, null, 401, ERROR_AUTHENTICATION);
    }

    /**
     * Respuesta prohibido
     */
    public static function forbidden(string $message = 'Acceso prohibido'): void
    {
        self::error($message, null, 403, ERROR_AUTHORIZATION);
    }

    /**
     * Respuesta no encontrado
     */
    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, null, 404, ERROR_NOT_FOUND);
    }

    /**
     * Respuesta de conflicto
     */
    public static function conflict(string $message = 'Conflicto con el recurso'): void
    {
        self::error($message, null, 409, ERROR_CONFLICT);
    }

    /**
     * Respuesta de error del servidor
     */
    public static function serverError(string $message = 'Error interno del servidor', $debug = null): void
    {
        $config = require __DIR__ . '/../../config/app.php';

        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => defined('ERROR_DATABASE') ? ERROR_DATABASE : 'DATABASE_ERROR',
        ];

        // Solo mostrar debug en desarrollo
        if ($config['app']['debug'] && $debug !== null) {
            $response['debug'] = $debug;
        }

        self::json($response, 500);
    }

    /**
     * Respuesta de rate limit excedido
     */
    public static function rateLimitExceeded(string $message = 'Demasiadas solicitudes'): void
    {
        header('Retry-After: 60');
        self::error($message, null, 429, ERROR_RATE_LIMIT);
    }

    /**
     * Obtener orígenes permitidos para CORS
     */
    private static function getAllowedOrigins(): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $appUrl = $config['app']['url'];

        return [
            $appUrl,
            'http://localhost',
            'http://localhost:3000',
            'http://localhost:8000',
            'http://127.0.0.1',
        ];
    }

    /**
     * Respuesta con paginación
     */
    public static function paginated(array $items, int $total, int $page, int $perPage, string $message = 'Success'): void
    {
        $totalPages = ceil($total / $perPage);

        self::json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ], 200);
    }

    /**
     * Redirección
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    /**
     * Descargar archivo
     */
    public static function download(string $filePath, string $filename = null, string $mimeType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            self::notFound('Archivo no encontrado');
        }

        $filename = $filename ?? basename($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($filePath);
        exit;
    }
}
