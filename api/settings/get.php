<?php
/**
 * API: Obtener configuraciones públicas
 * GET /api/settings/get.php?key=xxx
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

$key = $_GET['key'] ?? '';

if (empty($key)) {
    Response::error('Falta el parámetro key', null, 400);
}

// Lista blanca de llaves públicas
$allowed_keys = ['home_banners', 'site_name', 'contact_whatsapp', 'contact_email'];

if (!in_array($key, $allowed_keys)) {
    Response::error('Llave no permitida', null, 403);
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT setting_value, data_type FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        Response::error('Configuración no encontrada', null, 404);
    }

    $value = $setting['setting_value'];
    if ($setting['data_type'] === 'json') {
        $value = json_decode($value, true);
    }

    Response::success($value);

} catch (Exception $e) {
    Response::serverError('Error al obtener configuración');
}
