<?php
/**
 * Webhook entrante de Evolution API, uno por vendor.
 * URL a registrar en Evolution: .../api/whatsapp/webhook.php?token=<64 hex>
 *
 * El token IDENTIFICA y AUTENTICA a la vez: es un secreto de 32 bytes
 * (bin2hex(random_bytes(32))) generado una sola vez por vendor (ver
 * Core\WaConfig::regenerarWebhookToken()), guardado hasheado (sha256) en
 * wa_config.webhook_token_hash. No hace falta un ?vendor=123 aparte -
 * complicaria la URL sin sumar seguridad (el token ya es la prueba de
 * identidad, y no es adivinable como si lo fuera un ID secuencial).
 *
 * No se usa Core\WaConfig::resolverPorToken(): esa funcion solo tiene
 * rama para el modo "una base de datos por negocio" (consulta la tabla
 * wa_instancias en una BD maestra) - no tiene rama para el modo
 * column-scoped que usa MisRifas. Resolver el vendor aqui, con SQL
 * directo, es la pieza de integracion especifica de esta app.
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/MisRifasDb.php';
require_once __DIR__ . '/MisRifasTenant.php';
require_once __DIR__ . '/MisRifasSecret.php';
require_once __DIR__ . '/MisRifasStorage.php';
require_once __DIR__ . '/RaffleDomainAdapter.php';

use ElkinLinan\WhatsappAiEngine\Engine;
use ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient;
use ElkinLinan\WhatsappAiEngine\Core\AiOrchestrator;
use ElkinLinan\WhatsappAiEngine\Core\AuditLogger;
use ElkinLinan\WhatsappAiEngine\Core\ConversationManager;
use ElkinLinan\WhatsappAiEngine\Core\HumanHandoff;
use ElkinLinan\WhatsappAiEngine\Core\RateLimiter;
use ElkinLinan\WhatsappAiEngine\Core\WaConfig;
use ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos;
use ElkinLinan\WhatsappAiEngine\Defecto\SinUrl;
use ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido;

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, ?array $cuerpo = null): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo ?? ($codigo < 300 ? ['ok' => true] : ['ok' => false]), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['error' => 'method not allowed']);
}

$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    // 404 seco, sin pistas: no confirmar ni negar que el formato es el problema.
    responder(404);
}
$tokenHash = hash('sha256', $token);

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT vendor_id FROM wa_config WHERE webhook_token_hash = ? AND activo = 1 LIMIT 1');
$stmt->execute([$tokenHash]);
$fila = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fila) {
    responder(404);
}
$vendorId = (int)$fila['vendor_id'];

$stmtVendor = $pdo->prepare('SELECT business_name FROM vendors WHERE id = ? AND status = ?');
$stmtVendor->execute([$vendorId, 'active']);
$vendorName = (string)($stmtVendor->fetchColumn() ?: '');
if ($vendorName === '') {
    // Vendor suspendido/eliminado pero el token sigue en wa_config: no atender.
    responder(404);
}

Engine::arrancar([
    'db' => new MisRifasDb($pdo),
    'dominio' => new RaffleDomainAdapter($vendorId),
    'archivo' => new MisRifasStorage($vendorId),
    'secreto' => new MisRifasSecret(),
    'negocio' => new MisRifasTenant($vendorId, $vendorName),
    'formato' => new PesosColombianos(),
    'funcion' => new TodoPermitido(),
    'config' => new SinUrl(),
]);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    responder(400, ['error' => 'invalid json']);
}

$canal = EvolutionClient::desdeConfig(Engine::db());
if (!$canal) {
    // El vendor no ha terminado de configurar su instancia Evolution.
    // 200 para que Evolution no reintente indefinidamente un evento que
    // de todas formas no vamos a poder procesar.
    responder(200, ['ok' => true, 'nota' => 'vendor sin canal configurado']);
}

$mensaje = $canal->normalizarWebhook($payload);
if ($mensaje === null) {
    // Evento a ignorar (eco propio, grupo, CONNECTION_UPDATE, etc).
    responder(200);
}

$log = new AuditLogger(Engine::db());
$convManager = new ConversationManager(Engine::db());
$conv = $convManager->obtenerOCrear($mensaje['telefono'], $mensaje['nombre'] ?? '');

// guardarMensaje() devuelve 0 si el message_id_externo ya existia
// (Evolution reintentando el mismo evento) - idempotencia real, no un
// "best effort".
$guardado = $convManager->guardarMensaje((int)$conv['id'], 'entrante', [
    'message_id' => $mensaje['message_id'] ?? null,
    'tipo' => $mensaje['tipo'],
    'contenido' => $mensaje['texto'] ?? '',
]);
if ($guardado === 0) {
    responder(200, ['ok' => true, 'duplicado' => true]);
}

$convManager->tocar((int)$conv['id']);

if (!HumanHandoff::iaPuedeResponder($conv)) {
    // Un humano ya esta atendiendo o la IA esta pausada para este cliente:
    // el mensaje queda guardado (arriba) pero la IA no contesta.
    responder(200, ['ok' => true, 'nota' => 'ia inactiva en esta conversacion']);
}

// El motor ya trae RateLimiter::comprobar() (techo de mensajes por
// conversacion en una ventana corta) pero nunca se llamaba desde aqui -
// sin esto, cualquiera que le escriba al numero de un vendor puede hacer
// que el webhook dispare llamadas a la IA sin limite, corriendo la
// factura del proveedor de IA de ese vendor (denial-of-wallet).
$cfgVendor = WaConfig::cargar(Engine::db());
$limite = (new RateLimiter(Engine::db(), $log))->comprobar($conv, $cfgVendor);
if (!$limite['permitido']) {
    if ($limite['avisar']) {
        $canal->enviarTexto($mensaje['telefono'], $limite['mensaje']);
    }
    responder(200, ['ok' => true, 'nota' => 'limite de mensajes alcanzado']);
}

// Audio/imagen necesitan transcripcion/vision (Media/*) antes de poder
// pasar por AiOrchestrator::procesar(), que solo recibe texto plano - eso
// queda para cuando el flujo de texto este probado en produccion, no es
// parte de este Paso 4.
$texto = trim((string)($mensaje['texto'] ?? ''));
if ($mensaje['tipo'] !== 'texto' || $texto === '') {
    $canal->enviarTexto($mensaje['telefono'], 'Por ahora solo puedo leer mensajes de texto. Cuentame que rifa buscas 🙂');
    responder(200);
}

try {
    $orchestrator = new AiOrchestrator(Engine::db(), $canal, $log);
    $respuesta = $orchestrator->procesar($conv, $texto);
    if ($respuesta !== '') {
        $canal->enviarTexto($mensaje['telefono'], $respuesta);
    }
} catch (\Throwable $e) {
    error_log('[WhatsApp][webhook] vendor=' . $vendorId . ' ' . $e->getMessage());
    $canal->enviarTexto(
        $mensaje['telefono'],
        'Tuve un problema respondiendo tu mensaje. Ya le avise a alguien del equipo para que te ayude.'
    );
}

responder(200);
