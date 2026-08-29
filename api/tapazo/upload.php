<?php
/**
 * API: Subir imagen de un Tapazo
 * POST /api/tapazo/upload.php  (campo multipart: "imagen")
 *
 * El Tapazo se crea sin registro (ver crear.php), así que este endpoint es
 * anónimo a propósito — a cambio se endurece: validación real de imagen vía
 * Uploader (extensión + MIME por contenido + máx 5MB) y rate-limit por IP
 * para que no sea un canal de subida ilimitada.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Uploader.php';

const TAPAZO_UPLOADS_PER_HOUR = 10;

/** Rate-limit simple por IP: timestamps en logs/upload_rate/ (bloqueado por .htaccess). */
function tapazoUploadRateLimited(string $ip): bool
{
    $dir = __DIR__ . '/../../logs/upload_rate';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . sha1($ip) . '.json';
    $now = time();

    $times = [];
    if (is_file($file)) {
        $times = json_decode((string)file_get_contents($file), true) ?: [];
    }
    $times = array_values(array_filter($times, fn($t) => $t > $now - 3600));

    if (count($times) >= TAPAZO_UPLOADS_PER_HOUR) {
        return true;
    }
    $times[] = $now;
    file_put_contents($file, json_encode($times), LOCK_EX);
    return false;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (tapazoUploadRateLimited($ip)) {
        Response::error('Demasiadas subidas desde esta conexión. Intenta de nuevo en una hora.', null, 429);
    }

    // El formulario del tapazo manda "imagen"; se acepta "image" por tolerancia
    $file = $_FILES['imagen'] ?? $_FILES['image'] ?? null;
    if ($file === null) {
        Response::error('No se recibió ninguna imagen');
    }

    $path = Uploader::upload($file, 'assets/uploads/tapazos', 'banner');

    // URL absoluta al sitio: se guarda tal cual en tapazos.imagen_url y se usa
    // directo como <img src> desde /tapazo/, así que no puede ser relativa.
    Response::success([
        'url' => BASE_PATH . '/public/' . $path,
    ], 'Imagen subida correctamente');

} catch (Exception $e) {
    Response::error($e->getMessage());
}
