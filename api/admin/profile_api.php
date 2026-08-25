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
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../api/whatsapp/MisRifasDb.php';
require_once __DIR__ . '/../../api/whatsapp/MisRifasTenant.php';
require_once __DIR__ . '/../../api/whatsapp/MisRifasSecret.php';
require_once __DIR__ . '/../../api/whatsapp/MisRifasStorage.php';
require_once __DIR__ . '/../../api/whatsapp/RaffleDomainAdapter.php';

use ElkinLinan\WhatsappAiEngine\Engine;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos;
use ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido;
use ElkinLinan\WhatsappAiEngine\Defecto\SinUrl;

/** La config de WhatsApp ahora vive en wa_config (tabla propia del motor,
 * secretos cifrados), no en vendors.wa_config (JSON en claro, columna
 * deprecada - se deja de escribir aqui, no se dropea todavia). */
function arrancarMotorPara(int $vendorId): void
{
    Engine::reiniciar();
    Engine::arrancar([
        'db' => new MisRifasDb(),
        'dominio' => new RaffleDomainAdapter($vendorId),
        'archivo' => new MisRifasStorage($vendorId),
        'secreto' => new MisRifasSecret(),
        'negocio' => new MisRifasTenant($vendorId),
        'formato' => new PesosColombianos(),
        'funcion' => new TodoPermitido(),
        'config' => new SinUrl(),
    ]);
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT payment_config FROM vendors WHERE id = ?");
        $stmt->execute([$adminUser['id']]);
        $paymentConfig = json_decode($stmt->fetchColumn() ?: '{}', true);

        arrancarMotorPara((int)$adminUser['id']);
        $waCfg = WaConfig::paraFrontend(Engine::db());

        Response::success([
            'payment_config' => $paymentConfig,
            'wa_config'      => [
                'evo_api_url'  => $waCfg['evolution_url'] ?? '',
                'evo_instance' => $waCfg['evolution_instancia'] ?? '',
                'evo_api_key_configurado' => $waCfg['evolution_apikey_configurado'] ?? false,
            ],
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
            arrancarMotorPara((int)$adminUser['id']);

            $campos = [];
            if (isset($input['evo_api_url']))  $campos['evolution_url'] = rtrim(trim($input['evo_api_url']), '/');
            if (isset($input['evo_instance'])) $campos['evolution_instancia'] = trim($input['evo_instance']);
            // evolution_apikey esta en WaConfig::SECRETOS - un valor vacio no
            // pisa el ya guardado (mismo comportamiento que el codigo viejo).
            if (isset($input['evo_api_key']))  $campos['evolution_apikey'] = trim($input['evo_api_key']);

            WaConfig::guardar(Engine::db(), $campos);

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
