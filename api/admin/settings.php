<?php
/**
 * API: Configuración del Sistema (Admin)
 * GET /api/admin/settings.php
 * POST /api/admin/settings.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Validator.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

require_once __DIR__ . '/../../api/utils/Auth.php';

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query("SELECT * FROM system_settings");
        $settings = $stmt->fetchAll();
        
        // Secretos NUNCA viajan de vuelta al navegador (además este GET lo
        // puede llamar cualquier vendedor autenticado, no solo super_admin).
        $secretas = ['mailing_smtp_pass', 'brevo_api_key'];
        $formatted = [];
        foreach ($settings as $s) {
            $formatted[$s['setting_key']] = in_array($s['setting_key'], $secretas, true)
                ? '' : $s['setting_value'];
        }

        // Add the user's wompi config (vive dentro de vendors.payment_config)
        $paymentConfig = json_decode($adminUser['payment_config'] ?? '{}', true);
        if ($paymentConfig) {
            $formatted = array_merge($formatted, $paymentConfig);
        }
        
        Response::success($formatted);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($adminUser['role'] === 'super_admin') {
            $stmt = $db->query("SELECT setting_key FROM system_settings");
            $validKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $updateStmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");

            foreach ($input as $key => $value) {
                if (in_array($key, $validKeys)) {
                    $valToSave = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
                    $updateStmt->execute([$valToSave, $key]);
                }
            }
        }
        // Handle wompi keys for current user (guardadas dentro de
        // vendors.payment_config, no en una columna dedicada wompi_config
        // que solo existia en el admin_users legacy)
        if (isset($input['wompi_public_key']) || isset($input['wompi_private_key']) || isset($input['wompi_merchant_id'])) {
            $stmt = $db->prepare("SELECT payment_config FROM vendors WHERE id = ?");
            $stmt->execute([$adminUser['id']]);
            $currentConfig = json_decode($stmt->fetchColumn() ?: '{}', true);

            if (isset($input['wompi_public_key'])) $currentConfig['wompi_public_key'] = $input['wompi_public_key'];
            if (isset($input['wompi_private_key']) && !empty($input['wompi_private_key'])) $currentConfig['wompi_private_key'] = $input['wompi_private_key'];
            if (isset($input['wompi_merchant_id'])) $currentConfig['wompi_merchant_id'] = $input['wompi_merchant_id'];

            $stmt = $db->prepare("UPDATE vendors SET payment_config = ? WHERE id = ?");
            $stmt->execute([json_encode($currentConfig), $adminUser['id']]);
        }
        
        Logger::activity('settings_updated', $adminUser['id'], ['keys' => array_keys($input)]);
        
        Response::success(['message' => 'Configuración actualizada']);
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al guardar configuración');
}
