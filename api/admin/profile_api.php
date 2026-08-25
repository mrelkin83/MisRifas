<?php
/**
 * API: Perfil de Integración del Vendedor
 * GET /api/admin/profile_api.php   - Obtener configuración actual
 * POST /api/admin/profile_api.php  - Guardar credenciales Nequi y/o EvolutionAPI
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {     header('Access-Control-Allow-Origin: *');
 http_response_code(200); exit; }

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT payment_config, wa_config FROM vendors WHERE id = ?");
        $stmt->execute([$adminUser['id']]);
        $row = $stmt->fetch();

        Response::success([
            'payment_config' => json_decode($row['payment_config'] ?? '{}', true),
            'wa_config'      => json_decode($row['wa_config'] ?? '{}', true),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type  = $input['type'] ?? '';

        if ($type === 'nequi') {
            $stmt = $db->prepare("SELECT payment_config FROM vendors WHERE id = ?");
            $stmt->execute([$adminUser['id']]);
            $current = json_decode($stmt->fetchColumn() ?: '{}', true);

            if (isset($input['nequi_key']))    $current['nequi_key']   = trim($input['nequi_key']);
            // No sobreescribir el secret si viene vacío (el user no lo volvió a escribir)
            if (!empty($input['nequi_secret'])) $current['nequi_secret'] = trim($input['nequi_secret']);
            if (isset($input['nequi_phone']))   $current['nequi_phone']  = preg_replace('/[^0-9]/', '', $input['nequi_phone']);

            $stmt = $db->prepare("UPDATE vendors SET payment_config = ? WHERE id = ?");
            $stmt->execute([json_encode($current), $adminUser['id']]);

            Logger::activity('profile_nequi_updated', $adminUser['id']);
            Response::success(['message' => 'Credenciales Nequi guardadas']);
        }

        if ($type === 'whatsapp') {
            $stmt = $db->prepare("SELECT wa_config FROM vendors WHERE id = ?");
            $stmt->execute([$adminUser['id']]);
            $current = json_decode($stmt->fetchColumn() ?: '{}', true);

            if (isset($input['evo_api_url']))    $current['evo_api_url']   = rtrim(trim($input['evo_api_url']), '/');
            if (!empty($input['evo_api_key']))   $current['evo_api_key']   = trim($input['evo_api_key']);
            if (isset($input['evo_instance']))   $current['evo_instance']  = trim($input['evo_instance']);

            $stmt = $db->prepare("UPDATE vendors SET wa_config = ? WHERE id = ?");
            $stmt->execute([json_encode($current), $adminUser['id']]);

            Logger::activity('profile_whatsapp_updated', $adminUser['id']);
            Response::success(['message' => 'Configuración WhatsApp guardada']);
        }

        Response::error('Tipo de configuración no reconocido', null, 400);
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en configuración de perfil');
}
