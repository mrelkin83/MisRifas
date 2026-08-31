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
use ElkinLinan\WhatsappAiEngine\Providers\LlmProviderManager;
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
        $waCfgRaw = WaConfig::cargar(Engine::db());

        Response::success([
            'payment_config' => $paymentConfig,
            'wa_config'      => [
                'evo_api_url'  => $waCfg['evolution_url'] ?? '',
                'evo_instance' => $waCfg['evolution_instancia'] ?? '',
                'evo_api_key_configurado' => $waCfg['evolution_apikey_configurado'] ?? false,
                'activo' => !empty($waCfg['activo']),
                'webhook_configurado' => !empty($waCfgRaw['webhook_token_hash']),
                'webhook_url_base' => rtrim(getenv('APP_URL') ?: 'http://localhost', '/') . '/api/whatsapp/webhook.php',
                'llm_proveedor' => $waCfg['llm_proveedor'] ?? '',
                'llm_modelo' => $waCfg['llm_modelo'] ?? '',
                'llm_api_key_configurado' => $waCfg['llm_api_key_configurado'] ?? false,
                'llm_proveedores' => LlmProviderManager::PROVEEDORES,
            ],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type  = $input['type'] ?? '';

        // El manejador 'nequi' (credenciales de API de pasarela) se eliminó:
        // la plataforma no verifica pagos automáticamente. Las llaves de COBRO
        // del vendedor (celular Nequi/DaviPlata, llave Bre-B, efectivo) se
        // gestionan con type 'payment_keys' (fase de métodos de pago).

        if ($type === 'whatsapp') {
            arrancarMotorPara((int)$adminUser['id']);

            $campos = [];
            if (isset($input['evo_api_url'])) {
                $evoUrl = rtrim(trim($input['evo_api_url']), '/');
                // El motor ya trae este guard (Http::destinoPublicoSeguro) para
                // exactamente este caso -URL que pone el vendor- pero nunca se
                // llamaba desde aqui: un vendor podia apuntar su propia
                // instancia a 169.254.169.254, localhost o la red interna de
                // otro tenant, y la plataforma le haria requests salientes en
                // cada mensaje que ese vendor reciba.
                if ($evoUrl !== '' && !\ElkinLinan\WhatsappAiEngine\Core\Http::destinoPublicoSeguro($evoUrl)) {
                    Response::error('La URL de EvolutionAPI debe ser una direccion publica valida (http/https, no una IP privada, loopback o de metadata)', null, 400);
                }
                $campos['evolution_url'] = $evoUrl;
            }
            if (isset($input['evo_instance'])) $campos['evolution_instancia'] = trim($input['evo_instance']);
            // evolution_apikey esta en WaConfig::SECRETOS - un valor vacio no
            // pisa el ya guardado (mismo comportamiento que el codigo viejo).
            if (isset($input['evo_api_key']))  $campos['evolution_apikey'] = trim($input['evo_api_key']);

            WaConfig::guardar(Engine::db(), $campos);

            Logger::activity('profile_whatsapp_updated', $adminUser['id']);
            Response::success(['message' => 'Configuración WhatsApp guardada']);
        }

        if ($type === 'whatsapp_llm') {
            arrancarMotorPara((int)$adminUser['id']);

            $proveedor = trim((string)($input['llm_proveedor'] ?? ''));
            if ($proveedor !== '' && !array_key_exists($proveedor, LlmProviderManager::PROVEEDORES)) {
                Response::error('Proveedor de IA no reconocido', null, 400);
            }

            $campos = [];
            if (isset($input['llm_proveedor'])) $campos['llm_proveedor'] = $proveedor;
            if (isset($input['llm_modelo']))    $campos['llm_modelo'] = trim((string)$input['llm_modelo']);
            // vacio no pisa la clave ya guardada, igual que evolution_apikey arriba
            if (isset($input['llm_api_key']))   $campos['llm_api_key'] = trim((string)$input['llm_api_key']);

            WaConfig::guardar(Engine::db(), $campos);

            Logger::activity('profile_whatsapp_llm_updated', $adminUser['id']);
            Response::success(['message' => 'Proveedor de IA guardado']);
        }

        if ($type === 'whatsapp_regenerar_token') {
            arrancarMotorPara((int)$adminUser['id']);
            $token = WaConfig::regenerarWebhookToken(Engine::db());
            $webhookUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/')
                . '/api/whatsapp/webhook.php?token=' . $token;

            Logger::activity('profile_whatsapp_token_regenerated', $adminUser['id']);
            Response::success([
                'message' => 'Token generado. Cópialo ahora: no se vuelve a mostrar.',
                'webhook_url' => $webhookUrl,
            ]);
        }

        if ($type === 'whatsapp_activar') {
            arrancarMotorPara((int)$adminUser['id']);
            $activar = !empty($input['activo']);

            if ($activar) {
                $cfg = WaConfig::cargar(Engine::db(), true);
                $faltan = [];
                if (!$cfg || empty($cfg['webhook_token_hash'])) $faltan[] = 'generar el token del webhook';
                if (!$cfg || empty($cfg['evolution_url']))      $faltan[] = 'la URL de EvolutionAPI';
                if (!$cfg || empty($cfg['evolution_instancia'])) $faltan[] = 'el nombre de la instancia';
                if (!$cfg || empty($cfg['evolution_apikey']))   $faltan[] = 'la Global API Key de EvolutionAPI';
                if (!$cfg || empty($cfg['llm_proveedor']))      $faltan[] = 'el proveedor de IA';
                if (!$cfg || empty($cfg['llm_modelo']))         $faltan[] = 'el modelo de IA';
                if (!$cfg || empty($cfg['llm_api_key']))        $faltan[] = 'la API key del proveedor de IA';
                if ($faltan) {
                    Response::error('Falta configurar: ' . implode(', ', $faltan) . '.', null, 400);
                }
            }

            WaConfig::guardar(Engine::db(), ['activo' => $activar ? 1 : 0]);

            Logger::activity('profile_whatsapp_' . ($activar ? 'activado' : 'desactivado'), $adminUser['id']);
            Response::success(['message' => $activar ? 'Bot de WhatsApp activado ✅' : 'Bot de WhatsApp desactivado']);
        }

        Response::error('Tipo de configuración no reconocido', null, 400);
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en configuración de perfil');
}
