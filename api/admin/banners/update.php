<?php
/**
 * API: Actualizar Banners (Slides) de Portada
 * Soporta carga de imágenes y actualización de textos.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Uploader.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $user = Auth::requireAdmin(); // Solo Super Admin o Admin
    if ($user['role'] !== 'super_admin' && $user['role'] !== 'admin_user') {
        Response::error('No tienes permisos para esto', null, 403);
    }

    $db = Database::getInstance()->getConnection();

    // 1. Obtener banners actuales
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'home_banners'");
    $stmt->execute();
    $currentBanners = json_decode($stmt->fetchColumn() ?: '[]', true);

    // 2. Procesar slides enviados (dinámico hasta 10)
    $newBanners = [];
    for ($i = 0; $i < 10; $i++) {
        // Si no hay datos para este índice en el POST ni en el sistema, terminamos o saltamos
        if (!isset($_POST["banner_{$i}_title"]) && !isset($_FILES["banner_{$i}_file"])) {
            continue; 
        }

        $image = $_POST["banner_{$i}_img_url"] ?? ($currentBanners[$i]['image'] ?? '');
        $title = $_POST["banner_{$i}_title"] ?? '';
        $sub   = $_POST["banner_{$i}_subtitle"] ?? '';
        $btnT  = $_POST["banner_{$i}_btn_text"] ?? 'Ver más';
        $btnL  = $_POST["banner_{$i}_btn_link"] ?? '#';

        // Si se subió un archivo nuevo para este slide
        if (isset($_FILES["banner_{$i}_file"]) && $_FILES["banner_{$i}_file"]['error'] === UPLOAD_ERR_OK) {
            // Eliminar imagen anterior si era local
            if (!empty($currentBanners[$i]['image']) && !str_starts_with($currentBanners[$i]['image'], 'http')) {
                Uploader::delete($currentBanners[$i]['image']);
            }
            $imagePath = Uploader::upload($_FILES["banner_{$i}_file"], 'assets/uploads/banners', 'banner');
            $image = $imagePath;
        }

        $newBanners[] = [
            'image' => $image,
            'title' => $title,
            'subtitle' => $sub,
            'button_text' => $btnT,
            'button_link' => $btnL
        ];
    }

    // 3. Guardar en system_settings
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('home_banners', ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?");
    $json = json_encode($newBanners);
    $stmt->execute([$json, $json]);

    Response::success(['message' => 'Banners actualizados con éxito', 'data' => $newBanners]);

} catch (Exception $e) {
    Response::serverError('Error al actualizar banners: ' . $e->getMessage());
}
