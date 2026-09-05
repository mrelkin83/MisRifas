<?php
/**
 * Router del panel WhatsApp IA (admin de MisRifas).
 * GET/POST /api/whatsapp/admin/index.php?ep=<endpoint>
 *
 * Portado desde el api.php de ControlBarMax, pero cableado al motor de MisRifas
 * (Engine::arrancar con RaffleDomainAdapter + MisRifas*), y autenticado por la
 * SESIÓN de super_admin. SOLO super_admin — los vendedores no acceden todavía.
 * El motor opera scoped al vendor del super_admin (su propia wa_config).
 */

header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/',
    'secure' => (getenv('APP_ENV') ?: 'development') === 'production',
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../MisRifasDb.php';
require_once __DIR__ . '/../MisRifasTenant.php';
require_once __DIR__ . '/../MisRifasSecret.php';
require_once __DIR__ . '/../MisRifasStorage.php';
require_once __DIR__ . '/../RaffleDomainAdapter.php';
require_once __DIR__ . '/../AgenteMisRifas.php';

use ElkinLinan\WhatsappAiEngine\Engine;
use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Core\AgentManager;
use ElkinLinan\WhatsappAiEngine\Core\AuditLogger;
use ElkinLinan\WhatsappAiEngine\Core\ConversationManager;
use ElkinLinan\WhatsappAiEngine\Core\Http;
use ElkinLinan\WhatsappAiEngine\Core\RateLimiter;
use ElkinLinan\WhatsappAiEngine\Core\Scope;
use ElkinLinan\WhatsappAiEngine\Core\ToolEngine;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Providers\LlmProviderManager;
use ElkinLinan\WhatsappAiEngine\Providers\ModelDiscoveryService;
use ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos;
use ElkinLinan\WhatsappAiEngine\Defecto\ConfigDeEntorno;
use ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido;

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Gate: solo super_admin.
if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
    jsonResponse(['success' => false, 'error' => 'Acceso restringido'], 403);
}
$vendorId = (int)($_SESSION['user_id'] ?? 0);
if (!$vendorId) {
    jsonResponse(['success' => false, 'error' => 'Sesión inválida'], 401);
}

$pdo = Database::getInstance()->getConnection();
$stmtVendor = $pdo->prepare('SELECT business_name FROM vendors WHERE id = ? AND status = ?');
$stmtVendor->execute([$vendorId, 'active']);
require_once __DIR__ . '/../../../config/brand.php';
$vendorName = (string)($stmtVendor->fetchColumn() ?: plataforma('nombre'));

Engine::arrancar([
    'db' => new MisRifasDb($pdo),
    'dominio' => new RaffleDomainAdapter($vendorId),
    'archivo' => new MisRifasStorage($vendorId),
    'secreto' => new MisRifasSecret(),
    'negocio' => new MisRifasTenant($vendorId, $vendorName),
    'formato' => new PesosColombianos(),
    'funcion' => new TodoPermitido(),
    'config' => new ConfigDeEntorno(),
]);

$db  = Engine::db();
$log = new AuditLogger($db);
$ep  = (string)($_GET['ep'] ?? '');
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// En SaaS se restringe apuntar a la red interna; MisRifas self-hosted controla
// su propio servidor, así que solo se bloquea lo obviamente peligroso.
/** Credenciales del Evolution DE LA PLATAFORMA (.env). */
function waPlataformaCreds(): array {
    $url = rtrim((string)(getenv('WA_EVOLUTION_URL') ?: ''), '/');
    $key = (string)(getenv('WA_EVOLUTION_APIKEY') ?: '');
    if ($url === '' || $key === '') {
        jsonResponse(['success' => false, 'error' => 'Falta WA_EVOLUTION_URL/APIKEY en el .env del servidor'], 400);
    }
    return [$url, $key];
}

function waInstanciaValida(array $input): string {
    $n = (string)($input['name'] ?? '');
    if (!preg_match('/^misrifas-v[1-5]$/', $n)) {
        jsonResponse(['success' => false, 'error' => 'Instancia inválida (misrifas-v1..v5)'], 422);
    }
    return $n;
}

/** Copia el webhook registrado en cualquier instancia hermana a $destino. */
function waCopiarWebhook(string $url, string $key, string $destino): bool {
    for ($n = 1; $n <= 5; $n++) {
        $nom = 'misrifas-v' . $n;
        if ($nom === $destino) continue;
        $w = \ElkinLinan\WhatsappAiEngine\Core\Http::json('GET', $url . '/webhook/find/' . $nom, ['apikey: ' . $key], null, 6);
        $wu = $w['json']['url'] ?? null;
        if ($wu) {
            \ElkinLinan\WhatsappAiEngine\Core\Http::json('POST', $url . '/webhook/set/' . rawurlencode($destino), ['apikey: ' . $key],
                ['webhook' => ['url' => $wu, 'enabled' => true, 'byEvents' => false, 'base64' => true,
                               'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']]], 10);
            return true;
        }
    }
    return false;
}

function waUrlOk(string $url): bool {
    if ($url === '') return true;
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    return stripos($host, '169.254.169.254') === false; // metadata cloud
}

try {
    switch ($ep) {

        case 'config-get': {
            $cfg = WaConfig::paraFrontend($db);
            $cfg['proveedores'] = LlmProviderManager::PROVEEDORES;
            $wcfg = Engine::config();
            $cfg['evolution_gestionado'] = $wcfg->canalUrlPorDefecto() !== '';
            $cfg['tts_gestionado']    = $wcfg->ttsUrlPorDefecto() !== '';
            $cfg['vision_gestionado'] = $wcfg->visionUrlPorDefecto() !== '';
            $cfg['stt_gestionado']    = $wcfg->sttUrlPorDefecto() !== '';
            jsonResponse(['success' => true, 'config' => $cfg]);
        }

        case 'config-save': {
            $permitidos = ['activo','evolution_url','evolution_instancia','evolution_apikey','numero_whatsapp',
                'llm_proveedor','llm_modelo','llm_api_key','llm_fallback_proveedor','llm_fallback_modelo',
                'llm_fallback_api_key','llm_max_tokens','llm_temperatura',
                'stt_proveedor','stt_api_key','stt_modelo','stt_url',
                'tts_proveedor','tts_api_key','tts_voice_id','tts_modelo','tts_modo','tts_url',
                'vision_proveedor','vision_api_key','vision_modelo','vision_url',
                'pago_modo','wompi_ambiente','wompi_public_key','wompi_private_key',
                'wompi_events_secret','wompi_integrity_secret','pago_expira_minutos','pago_datos_transferencia',
                'handoff_numero','retencion_media_dias','limite_mensajes','limite_ventana_minutos','horario_atencion'];
            $campos = [];
            foreach ($permitidos as $k) if (array_key_exists($k, $input)) $campos[$k] = $input[$k];
            foreach (['evolution_url','stt_url','tts_url','vision_url'] as $cu) {
                if (isset($campos[$cu]) && !waUrlOk((string)$campos[$cu])) {
                    jsonResponse(['success' => false, 'error' => 'La dirección de «' . $cu . '» no está permitida.'], 400);
                }
            }
            if (isset($campos['horario_atencion']) && is_array($campos['horario_atencion'])) {
                $campos['horario_atencion'] = json_encode($campos['horario_atencion'], JSON_UNESCAPED_UNICODE);
            }
            WaConfig::guardar($db, $campos);
            $log->log('config', 'Configuración del motor actualizada', ['campos' => array_keys($campos)]);
            jsonResponse(['success' => true, 'config' => WaConfig::paraFrontend($db)]);
        }

        case 'webhook-url': {
            $token = WaConfig::regenerarWebhookToken($db);
            $base = rtrim((string)(getenv('APP_URL') ?: ''), '/');
            jsonResponse(['success' => true,
                'url' => $base . BASE_PATH . '/api/whatsapp/webhook.php?token=' . $token,
                'aviso' => 'Copia esta URL ahora: por seguridad no se vuelve a mostrar.']);
        }

        case 'conexion-probar-url': {
            $url = rtrim(trim((string)($input['url'] ?? '')), '/');
            if (!preg_match('#^https?://#', $url)) jsonResponse(['ok' => false, 'error' => 'La URL debe empezar por http:// o https://']);
            if (!waUrlOk($url)) jsonResponse(['ok' => false, 'error' => 'Esa dirección no está permitida.']);
            $r = Http::json('GET', $url . '/', [], null, 8);
            if (($r['status'] ?? 0) === 0) jsonResponse(['ok' => false, 'error' => 'No responde nadie en esa dirección.']);
            $esEvo = stripos((string)($r['body'] ?? ''), 'evolution') !== false;
            jsonResponse(['ok' => $esEvo, 'version' => $r['json']['version'] ?? '', 'error' => $esEvo ? '' : 'Responde algo, pero no parece Evolution API']);
        }

        case 'conexion-qr': {
            $cli = EvolutionClient::desdeConfig($db);
            if (!$cli) jsonResponse(['success' => false, 'error' => 'Falta configurar Evolution API'], 400);
            $faltan = method_exists($cli, 'requisitosFaltantes') ? $cli->requisitosFaltantes() : [];
            if ($faltan) jsonResponse(['success' => false, 'error' => 'Falta: ' . implode(', ', $faltan)], 400);
            $r = $cli->conectar();
            WaConfig::guardar($db, ['estado_conexion' => ($r['ok'] ?? false) ? 'qr' : 'error']);
            jsonResponse(['success' => $r['ok'] ?? false, 'qr' => $r['qr'] ?? null, 'error' => $r['error'] ?? '']);
        }

        case 'conexion-estado': {
            $cli = EvolutionClient::desdeConfig($db);
            if (!$cli) jsonResponse(['success' => true, 'estado' => 'desconectado', 'mensaje' => 'Sin configurar']);
            $e = $cli->estado();
            WaConfig::guardar($db, array_filter([
                'estado_conexion' => $e['estado'] ?? null,
                'ultima_conexion' => ($e['estado'] ?? '') === 'conectado' ? date('Y-m-d H:i:s') : null,
            ]));
            jsonResponse(['success' => true] + $e);
        }

        case 'conexion-webhook-registrar': {
            $cli = EvolutionClient::desdeConfig($db);
            if (!$cli) jsonResponse(['success' => false, 'error' => 'Falta configurar Evolution API'], 400);
            $url = trim((string)($input['url'] ?? ''));
            $r = $cli->registrarWebhook($url);
            jsonResponse(['success' => $r['ok'] ?? false, 'error' => $r['error'] ?? '']);
        }

        // ── Instancias de la PLATAFORMA (hasta 5: misrifas-v1..v5) ──
        // Varias vinculadas a la vez; UNA activa para enviar (evolution_
        // instancia). El webhook se registra en cada una: los códigos OTP y
        // mensajes entran desde cualquiera. Si WhatsApp tumba una sesión,
        // se cambia la activa sin tocar nada más.
        case 'instancias': {
            [$url, $key] = waPlataformaCreds();
            $r = Http::json('GET', $url . '/instance/fetchInstances', ['apikey: ' . $key], null, 10);
            $lista = [];
            foreach ((array)($r['json'] ?? []) as $i) {
                $n = (string)($i['name'] ?? '');
                if (!preg_match('/^misrifas-v[1-5]$/', $n)) continue;
                $w = Http::json('GET', $url . '/webhook/find/' . rawurlencode($n), ['apikey: ' . $key], null, 6);
                $lista[] = [
                    'name' => $n,
                    'estado' => (string)($i['connectionStatus'] ?? '?'),
                    'numero' => isset($i['ownerJid']) ? explode('@', (string)$i['ownerJid'])[0] : null,
                    'webhook' => !empty($w['json']['url']),
                ];
            }
            usort($lista, fn($a, $b) => strcmp($a['name'], $b['name']));
            $cfg = WaConfig::cargar($db);
            $rot = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'wa_rotacion'")->fetchColumn();
            jsonResponse(['success' => true, 'instancias' => $lista,
                'activa' => (string)($cfg['evolution_instancia'] ?? ''), 'max' => 5,
                'rotacion' => (string)$rot === '1']);
        }

        case 'instancias-rotacion': {
            $on = !empty($input['enabled']) ? '1' : '0';
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'wa_rotacion'")->execute([$on]);
            $log->log('config', 'Rotación de instancias WhatsApp: ' . ($on === '1' ? 'ACTIVADA' : 'desactivada'));
            jsonResponse(['success' => true, 'rotacion' => $on === '1']);
        }

        case 'instancia-crear': {
            [$url, $key] = waPlataformaCreds();
            $r = Http::json('GET', $url . '/instance/fetchInstances', ['apikey: ' . $key], null, 10);
            $usados = [];
            foreach ((array)($r['json'] ?? []) as $i) {
                if (preg_match('/^misrifas-v([1-5])$/', (string)($i['name'] ?? ''), $m)) $usados[(int)$m[1]] = true;
            }
            $slot = 0;
            for ($n = 1; $n <= 5; $n++) { if (empty($usados[$n])) { $slot = $n; break; } }
            if (!$slot) jsonResponse(['success' => false, 'error' => 'Ya existen las 5 instancias (misrifas-v1..v5).'], 409);
            $nombre = 'misrifas-v' . $slot;
            $c = Http::json('POST', $url . '/instance/create', ['apikey: ' . $key],
                ['instanceName' => $nombre, 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS'], 30);
            if (!($c['ok'] ?? false)) jsonResponse(['success' => false, 'error' => 'Evolution no pudo crear la instancia: ' . ($c['error'] ?? '')], 502);
            waCopiarWebhook($url, $key, $nombre); // hereda el webhook de las hermanas
            $log->log('config', 'Instancia WhatsApp creada: ' . $nombre);
            jsonResponse(['success' => true, 'name' => $nombre, 'qr' => $c['json']['qrcode']['base64'] ?? null]);
        }

        case 'instancia-qr': {
            [$url, $key] = waPlataformaCreds();
            $nombre = waInstanciaValida($input);
            $r = Http::json('GET', $url . '/instance/connect/' . rawurlencode($nombre), ['apikey: ' . $key], null, 30);
            $qr = $r['json']['base64'] ?? ($r['json']['qrcode']['base64'] ?? null);
            $code = $r['json']['code'] ?? null;
            if (!$qr && $code) $qr = $code; // el front genera imagen solo si es data:
            jsonResponse(['success' => (bool)($qr || ($r['ok'] ?? false)), 'qr' => $qr,
                'error' => $qr ? '' : 'La instancia no entregó QR (¿ya está conectada?)']);
        }

        case 'instancia-usar': {
            [$url, $key] = waPlataformaCreds();
            $nombre = waInstanciaValida($input);
            WaConfig::guardar($db, ['evolution_instancia' => $nombre]);
            waCopiarWebhook($url, $key, $nombre);
            $log->log('config', 'Instancia WhatsApp ACTIVA: ' . $nombre);
            jsonResponse(['success' => true, 'activa' => $nombre]);
        }

        case 'instancia-desvincular': {
            // Logout SIN delete: el teléfono queda desvinculado pero la
            // instancia sigue creada, lista para re-escanear el QR.
            [$url, $key] = waPlataformaCreds();
            $nombre = waInstanciaValida($input);
            $r = Http::json('DELETE', $url . '/instance/logout/' . rawurlencode($nombre), ['apikey: ' . $key], null, 15);
            if (!($r['ok'] ?? false)) {
                jsonResponse(['success' => false, 'error' => 'Evolution no pudo desvincular (¿ya estaba desconectada?)'], 502);
            }
            $cfg = WaConfig::cargar($db);
            if (($cfg['evolution_instancia'] ?? '') === $nombre) {
                WaConfig::guardar($db, ['estado_conexion' => 'desconectado']);
            }
            $log->log('config', 'Instancia WhatsApp desvinculada (sin eliminar): ' . $nombre);
            jsonResponse(['success' => true]);
        }

        case 'instancia-eliminar': {
            [$url, $key] = waPlataformaCreds();
            $nombre = waInstanciaValida($input);
            $cfg = WaConfig::cargar($db);
            if (($cfg['evolution_instancia'] ?? '') === $nombre) {
                jsonResponse(['success' => false, 'error' => 'Esa instancia es la ACTIVA: activa otra antes de eliminarla.'], 409);
            }
            Http::json('DELETE', $url . '/instance/logout/' . rawurlencode($nombre), ['apikey: ' . $key], null, 15);
            Http::json('DELETE', $url . '/instance/delete/' . rawurlencode($nombre), ['apikey: ' . $key], null, 15);
            $log->log('config', 'Instancia WhatsApp eliminada: ' . $nombre);
            jsonResponse(['success' => true]);
        }

        case 'conexion-desconectar': {
            $cli = EvolutionClient::desdeConfig($db);
            if ($cli) $cli->desconectar();
            WaConfig::guardar($db, ['estado_conexion' => 'desconectado']);
            $log->log('config', 'WhatsApp desvinculado');
            jsonResponse(['success' => true]);
        }

        case 'agente-get': {
            $am = new AgentManager($db);
            // Normaliza identidad y herramientas al dominio de rifas: el motor
            // trae defaults de comercio genérico (carta, pedidos, cocina) que
            // no son la misión de esta plataforma.
            $agente = AgenteMisRifas::normalizar($am);
            $catalogo = [];
            try {
                $te = new ToolEngine($db, new RaffleDomainAdapter($vendorId), $log);
                $catalogo = AgenteMisRifas::catalogoPanel($te);
            } catch (Throwable $e) { /* catálogo opcional */ }
            jsonResponse(['success' => true, 'agente' => $agente, 'herramientas_disponibles' => $catalogo]);
        }

        case 'agente-save': {
            $am = new AgentManager($db);
            // Las herramientas siempre quedan acotadas al dominio de rifas:
            // null ("todas las del motor") re-abriría cocina y garantías.
            $lista = isset($input['herramientas']) && is_array($input['herramientas']) ? $input['herramientas'] : null;
            $input['herramientas'] = json_encode(AgenteMisRifas::acotarHerramientas($lista), JSON_UNESCAPED_UNICODE);
            $am->guardar($input);
            $log->log('config', 'Agente actualizado');
            jsonResponse(['success' => true, 'agente' => $am->activo()]);
        }

        case 'llm-probar': {
            $cfg = WaConfig::cargar($db, true);
            $prov = $input['proveedor'] ?? ($cfg['llm_proveedor'] ?? '');
            $clave = trim((string)($input['api_key'] ?? '')) ?: WaConfig::secreto($cfg, 'llm_api_key');
            $modelo = $input['modelo'] ?? ($cfg['llm_modelo'] ?? 'x');
            $a = LlmProviderManager::crear((string)$prov, $clave, (string)$modelo);
            if (!$a) jsonResponse(['success' => false, 'error' => 'Proveedor no soportado o sin API Key'], 400);
            $res = array_merge(['success' => true, 'proveedor' => $a->nombre()], $a->validarCredenciales());
            if (($res['ok'] ?? false) && trim((string)$modelo) !== '' && $modelo !== 'x') {
                $c = $a->chat(['messages' => [['role' => 'user', 'content' => 'Responde solo: ok']], 'max_tokens' => 1024, 'temperature' => null]);
                if (!($c['ok'] ?? false)) { $res['ok'] = false; $res['error'] = 'La clave sirve, pero el modelo «' . $modelo . '» no responde: ' . ($c['error'] ?? ''); }
                else { $res['modelo_ok'] = true; }
            }
            jsonResponse($res);
        }

        case 'llm-modelos': {
            $mds = new ModelDiscoveryService($db, $log);
            jsonResponse(['success' => true,
                'modelos' => $mds->listar((string)($_GET['proveedor'] ?? '')),
                'nuevos' => method_exists($mds, 'nuevosSinRevisar') ? $mds->nuevosSinRevisar() : []]);
        }

        case 'llm-sincronizar-modelos': {
            $mds = new ModelDiscoveryService($db, $log);
            $prov = (string)($input['proveedor'] ?? '');
            $clave = trim((string)($input['api_key'] ?? '')) ?: WaConfig::secreto(WaConfig::cargar($db, true), 'llm_api_key');
            $r = $mds->sincronizar($prov, $clave);
            jsonResponse(['success' => true] + (is_array($r) ? $r : []));
        }

        case 'limites-estado': {
            jsonResponse(['success' => true,
                'techo' => RateLimiter::techoDelPlan(),
                'usadas' => (new ConversationManager($db))->conversacionesDelMes()]);
        }

        case 'dashboard': {
            // Métricas básicas del canal. Best-effort: si el motor no expone un
            // método, se devuelve 0 en vez de romper la pantalla.
            $cm = new ConversationManager($db);
            $usadas = method_exists($cm, 'conversacionesDelMes') ? $cm->conversacionesDelMes() : 0;
            $cfg = WaConfig::paraFrontend($db);
            jsonResponse(['success' => true, 'dashboard' => [
                'estado_conexion' => $cfg['estado_conexion'] ?? 'desconectado',
                'activo' => !empty($cfg['activo']),
                'conversaciones_mes' => $usadas,
                'techo_plan' => RateLimiter::techoDelPlan(),
            ]]);
        }

        // Bandeja de conversaciones y bitácora. Se listan desde las tablas wa_*
        // directamente (best-effort) para que las pestañas rendericen; las
        // acciones de atención humana (tomar/pausar/responder) son de una etapa
        // posterior.
        case 'conversaciones': {
            $lista = $db->fetchAll(
                "SELECT id, telefono, nombre_contacto, estado, ultimo_mensaje_at
                 FROM wa_conversaciones WHERE 1=1" . Scope::y() .
                " ORDER BY ultimo_mensaje_at DESC LIMIT 50",
                Scope::mas());
            jsonResponse(['success' => true, 'conversaciones' => $lista]);
        }

        case 'eventos': {
            $lista = $db->fetchAll(
                "SELECT tipo, descripcion, payload, created_at
                 FROM wa_eventos WHERE 1=1" . Scope::y() .
                " ORDER BY created_at DESC LIMIT 100",
                Scope::mas());
            jsonResponse(['success' => true, 'eventos' => $lista]);
        }

        case 'tts-voces':
            jsonResponse(['success' => true, 'voces' => []]);

        // Voz/visión: la pantalla guarda y carga su configuración vía
        // config-get/config-save; el listado de modelos y las pruebas en vivo
        // aún no están cableados a este router. Se responde con honestidad en
        // vez de un 404 que la pantalla muestra como error críptico.
        case 'stt-modelos':
        case 'vision-modelos':
            jsonResponse(['success' => true, 'modelos' => [],
                'nota' => 'El listado automático aún no está disponible aquí: escribe el modelo a mano.']);

        case 'stt-probar':
        case 'tts-probar':
        case 'vision-probar':
            jsonResponse(['success' => true, 'ok' => false,
                'error' => 'La prueba en vivo aún no está disponible en este panel. Guarda la configuración y pruébalo enviando un audio o una foto al WhatsApp vinculado.']);

        case 'llm-modelos-revisados':
            jsonResponse(['success' => true]);

        default:
            jsonResponse(['success' => false, 'error' => 'Endpoint no encontrado: ' . $ep], 404);
    }
} catch (Throwable $e) {
    error_log('WA admin router error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Error interno del motor'], 500);
}
