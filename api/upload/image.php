<?php
/**
 * API: Image Upload Endpoint (Multi-file support)
 * POST /api/upload/image.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

    $uploadedUrls = [];
    foreach ($filesToProcess as $fileData) {
        if ($fileData['error'] !== UPLOAD_ERR_OK) continue;
        
        // Usar el Uploader centralizado (valida tipos y tamaños 5MB)
        $path = Uploader::upload($fileData, 'assets/uploads/raffles', 'raffle');
        $uploadedUrls[] = $path;
    }

    if (empty($uploadedUrls)) {
        Response::error('No se pudo subir ninguna imagen válida');
    }

    Response::success([
        'url'  => $uploadedUrls[0], // Retrocompatibilidad
        'urls' => $uploadedUrls
    ], 'Imágenes subidas correctamente');

} catch (Exception $e) {
    Response::error($e->getMessage());
}
