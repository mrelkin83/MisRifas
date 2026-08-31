<?php
/**
 * API: Actualizar configuraciones de sistema (Solo Admin)
 * POST /api/admin/settings/update.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';

// Solo super_admin: son ajustes GLOBALES de la plataforma. El check anterior
// comparaba contra 'superadmin' (sin guion bajo) — un rol que NO existe — así
// que TODO el mundo recibía 403 y el formulario SMTP nunca pudo guardar.
$user = Auth::requireRole('super_admin');

$data = json_decode(file_get_contents('php://input'), true);
$key = $data['key'] ?? '';
$value = $data['value'] ?? '';

if (empty($key)) {
    Response::error('Falta el parámetro key', null, 400);
}

// Lista blanca de llaves editables
$editable_keys = [
    'home_banners', 'site_name', 'contact_whatsapp', 'contact_email', 'brevo_api_key',
    'platform_name', 'platform_email',
    // Correo del sistema (el formulario SMTP del panel guarda clave por clave)
    'mailing_smtp_host', 'mailing_smtp_port', 'mailing_smtp_user',
    'mailing_smtp_pass', 'mailing_smtp_from', 'mailing_from_name',
];

if (!in_array($key, $editable_keys)) {
    Response::error('Llave no editable', null, 403);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Si la llave es home_banners, validamos que sea un JSON válido
    if ($key === 'home_banners') {
        if (!is_array($value)) {
            Response::error('Valor de banners inválido', null, 400);
        }
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    $stmt = $db->prepare("
        UPDATE system_settings 
        SET setting_value = ?, updated_at = NOW() 
        WHERE setting_key = ?
    ");
    
    $stmt->execute([$value, $key]);

    if ($stmt->rowCount() === 0) {
        // Si no existe, lo insertamos (opcional, dependiendo de lo que queramos)
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, data_type, updated_at)
            VALUES (?, ?, ?, NOW())
        ");
        $type = (is_array(json_decode($value)) || is_object(json_decode($value))) ? 'json' : 'string';
        $stmt->execute([$key, $value, $type]);
    }

    Response::success(['message' => 'Configuración actualizada']);

} catch (Exception $e) {
    Response::serverError('Error al actualizar configuración: ' . $e->getMessage());
}
