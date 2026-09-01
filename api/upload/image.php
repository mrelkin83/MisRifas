<?php
/**
 * API: Image Upload Endpoint (Multi-file support)
 * POST /api/upload/image.php
 */

require_once __DIR__ . '/../../config/paths.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Uploader.php';

try {
    // Este endpoint sube imagenes de rifas - solo los vendors las crean,
    // no hay razon para que un comprador logueado pueda usarlo.
    $user = Auth::requireVendor();
    error_log("User logged in for upload: " . $user['email']);

    if (!isset($_FILES['image'])) {
        // Si el lote supera post_max_size, PHP descarta el cuerpo ENTERO en
        // silencio y aquí no llega nada. Decir por qué, no un genérico.
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $max = ini_get('post_max_size');
        if ($len > 0 && $len > (int)$max * 1024 * 1024) {
            Response::error(sprintf(
                'El lote pesa %.1f MB y el servidor acepta máximo %s por envío. Sube las fotos en tandas más pequeñas o reduce su tamaño.',
                $len / 1048576, $max
            ), null, 413);
        }
        Response::error('No se recibió ninguna imagen');
    }

    $filesToProcess = [];
    if (is_array($_FILES['image']['name'])) {
        // Múltiples archivos
        foreach ($_FILES['image']['name'] as $i => $name) {
            $filesToProcess[] = [
                'name'     => $_FILES['image']['name'][$i],
                'type'     => $_FILES['image']['type'][$i],
                'tmp_name' => $_FILES['image']['tmp_name'][$i],
                'error'    => $_FILES['image']['error'][$i],
                'size'     => $_FILES['image']['size'][$i]
            ];
        }
    } else {
        // Un solo archivo
        $filesToProcess[] = $_FILES['image'];
    }

    // Una foto mala NO tumba el lote: se sube lo válido y se reporta por
    // nombre lo que falló (antes se saltaba en silencio con `continue`).
    $uploadedUrls = [];
    $fallidas = [];
    foreach ($filesToProcess as $fileData) {
        $nombre = (string)($fileData['name'] ?? 'archivo');
        if ($fileData['error'] === UPLOAD_ERR_INI_SIZE || $fileData['error'] === UPLOAD_ERR_FORM_SIZE) {
            $fallidas[] = "$nombre (supera el límite por archivo de " . ini_get('upload_max_filesize') . ')';
            continue;
        }
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            $fallidas[] = "$nombre (error de subida)";
            continue;
        }
        try {
            // Uploader centralizado (valida tipo real y tamaño 5MB)
            $uploadedUrls[] = Uploader::upload($fileData, 'assets/uploads/raffles', 'raffle');
        } catch (Exception $e) {
            $fallidas[] = "$nombre (" . $e->getMessage() . ')';
        }
    }

    if (empty($uploadedUrls)) {
        Response::error('No se pudo subir ninguna imagen. ' . ($fallidas ? 'Detalle: ' . implode('; ', $fallidas) : ''));
    }

    Response::success([
        'url'  => $uploadedUrls[0], // Retrocompatibilidad
        'urls' => $uploadedUrls,
        'fallidas' => $fallidas,
    ], 'Imágenes subidas correctamente');

} catch (Exception $e) {
    Response::error($e->getMessage());
}
