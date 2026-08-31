<?php
/**
 * API: WhatsApp autoservicio del vendedor (para notificaciones de rifas)
 * GET  /api/vendor/whatsapp.php?action=estado
 * POST /api/vendor/whatsapp.php  { action: 'qr' | 'desconectar' }
 *
 * Modo gestionado: la plataforma define WA_EVOLUTION_URL/WA_EVOLUTION_APIKEY
 * en el .env (el vendedor NUNCA las ve). Aquí solo se auto-provisiona su
 * instancia (misrifas-v{id}) y se le muestra el QR: escanea y listo. Si el
 * vendedor configuró su propio servidor Evolution en el perfil, ese manda.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../whatsapp/MisRifasDb.php';
require_once __DIR__ . '/../whatsapp/MisRifasTenant.php';
require_once __DIR__ . '/../whatsapp/MisRifasSecret.php';
require_once __DIR__ . '/../whatsapp/MisRifasStorage.php';
require_once __DIR__ . '/../whatsapp/RaffleDomainAdapter.php';

use ElkinLinan\WhatsappAiEngine\Engine;
use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Defecto\ConfigDeEntorno;
use ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos;
use ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido;

try {
    $vendor = Auth::requireVendor();
    $vendorId = (int)$vendor['id'];

    Engine::reiniciar();
    Engine::arrancar([
        'db' => new MisRifasDb(),
        'dominio' => new RaffleDomainAdapter($vendorId),
        'archivo' => new MisRifasStorage($vendorId),
        'secreto' => new MisRifasSecret(),
        'negocio' => new MisRifasTenant($vendorId, (string)($vendor['business_name'] ?? 'MisRifas')),
        'formato' => new PesosColombianos(),
        'funcion' => new TodoPermitido(),
        // ConfigDeEntorno: credenciales gestionadas de la plataforma vía .env,
        // invisibles al vendedor.
        'config' => new ConfigDeEntorno(),
    ]);
    $db = Engine::db();

    $gestionado = Engine::config()->canalUrlPorDefecto() !== '';
    $cfg = WaConfig::cargar($db);
    $tieneUrlPropia = !empty($cfg['evolution_url']);

    $action = $_SERVER['REQUEST_METHOD'] === 'GET'
        ? (string)($_GET['action'] ?? 'estado')
        : (string)((json_decode(file_get_contents('php://input'), true) ?: [])['action'] ?? '');

    if ($action === 'estado') {
        if (!$gestionado && !$tieneUrlPropia) {
            Response::success([
                'gestionado' => false,
                'estado' => 'no_disponible',
                'mensaje' => 'El motor de WhatsApp de la plataforma aún no está habilitado.',
            ]);
        }
        $cli = EvolutionClient::desdeConfig($db);
        if (!$cli) {
            Response::success(['gestionado' => $gestionado, 'estado' => 'sin_vincular', 'numero' => null]);
        }
        $e = $cli->estado();
        Response::success([
            'gestionado' => $gestionado,
            'estado' => $e['estado'] ?? 'desconectado',
            'numero' => $e['numero'] ?? null,
            'mensaje' => $e['mensaje'] ?? '',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }

    if ($action === 'qr') {
        if (!$gestionado && !$tieneUrlPropia) {
            Response::error('El motor de WhatsApp de la plataforma aún no está habilitado. Pronto podrás vincular tu número.', 'CHANNEL_UNAVAILABLE', 409);
        }
        // Auto-provisión: instancia propia del vendedor, credenciales de la
        // plataforma por defecto (el vendedor no configura NI VE nada técnico).
        // El nombre se genera con datos únicos del vendedor (nombre + documento)
        // y el prefijo mr{id} garantiza que jamás colisione con otra instancia.
        if (empty($cfg['evolution_instancia'])) {
            $row = Database::getInstance()->getConnection()
                ->prepare("SELECT business_name, document_number, phone FROM vendors WHERE id = ?");
            $row->execute([$vendorId]);
            $v = $row->fetch(PDO::FETCH_ASSOC) ?: [];
            $slug = strtolower(trim((string)($v['business_name'] ?? '')));
            $slug = strtr($slug, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
            $slug = substr($slug, 0, 24);
            $doc = preg_replace('/\D+/', '', (string)($v['document_number'] ?? ''));
            if ($doc === '') {
                $doc = preg_replace('/\D+/', '', (string)($v['phone'] ?? ''));
            }
            $instancia = rtrim('mr' . $vendorId . '-' . $slug . ($doc !== '' ? '-' . $doc : ''), '-');
            WaConfig::guardar($db, [
                'activo' => 1,
                'evolution_instancia' => $instancia,
                'numero_whatsapp' => (string)($vendor['phone'] ?? ''),
            ]);
            WaConfig::cargar($db, true);
        }
        $cli = EvolutionClient::desdeConfig($db);
        if (!$cli) {
            Response::error('No se pudo preparar el canal de WhatsApp', null, 500);
        }
        $r = $cli->conectar();
        WaConfig::guardar($db, ['estado_conexion' => ($r['ok'] ?? false) ? 'qr' : 'error']);
        Logger::activity('wa_link_qr', $vendorId, ['ok' => $r['ok'] ?? false]);
        Response::success([
            'qr' => $r['qr'] ?? null,
            'error' => $r['error'] ?? '',
        ], ($r['ok'] ?? false) ? 'Escanea el código con tu WhatsApp' : 'No se pudo generar el QR');
    }

    if ($action === 'desconectar') {
        $cli = EvolutionClient::desdeConfig($db);
        if ($cli) {
            $cli->desconectar();
        }
        WaConfig::guardar($db, ['estado_conexion' => 'desconectado']);
        Logger::activity('wa_unlink', $vendorId, []);
        Response::success([], 'WhatsApp desvinculado');
    }

    Response::error('Acción inválida', null, 400);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en el canal de WhatsApp');
}
