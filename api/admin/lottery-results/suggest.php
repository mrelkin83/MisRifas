<?php
/**
 * API: Sugerir el número ganador (solo super_admin).
 * GET /api/admin/lottery-results/suggest.php?lottery_id=&draw_date=YYYY-MM-DD
 *
 * SUGERENCIA, no autoridad. Nunca marca nada verificado ni guarda: solo
 * propone un número para que el super_admin lo REVISE y confirme en la
 * tarjeta manual. Dos caminos, ambos grounded en datos reales (jamás se
 * inventa un número, misma integridad por la que se quitó el fallback md5):
 *   1) scraper determinista (colombia.com / página oficial).
 *   2) si el scraper no da y hay LLM configurado: se le pasa el TEXTO de la
 *      página al modelo para que EXTRAIGA el número (no lo saca de memoria).
 * Si ninguno da un número válido, responde sin sugerencia.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';
require_once __DIR__ . '/../../../api/services/LotteryScraperService.php';
require_once __DIR__ . '/../../../api/services/ColombiaComScraper.php';

// Gate: solo super_admin (por token, igual que set.php y pending.php).
$authUser = Auth::requireSuperAdmin();

$respond = function (array $data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

$lotteryId = (int)($_GET['lottery_id'] ?? 0);
$drawDate  = trim((string)($_GET['draw_date'] ?? ''));
if (!$lotteryId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $drawDate)) {
    $respond(['success' => false, 'message' => 'Parámetros inválidos (lottery_id, draw_date=YYYY-MM-DD)'], 400);
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT name FROM lotteries WHERE id = ?');
    $stmt->execute([$lotteryId]);
    $lotteryName = (string)($stmt->fetchColumn() ?: '');
    if ($lotteryName === '') {
        $respond(['success' => false, 'message' => 'Lotería no encontrada'], 404);
    }

    // 1) Scraper determinista — la fuente automática de siempre.
    $num = LotteryScraperService::fetchResult($lotteryName, $drawDate);
    if (is_string($num) && preg_match('/^\d{2,6}$/', $num)) {
        $respond([
            'success' => true,
            'number'  => $num,
            'method'  => 'automatico',
            'source'  => 'colombia.com',
            'note'    => 'Obtenido automáticamente. Verifícalo contra la fuente oficial antes de guardar.',
        ]);
    }

    // 2) Camino IA: solo si hay un proveedor LLM configurado (módulo WhatsApp IA).
    //    Se le da el TEXTO real de la página; extrae, no inventa.
    $vendorId = (int)($authUser['id'] ?? 0);
    $llm = cargarLlmDelSuperAdmin($pdo, $vendorId);
    if (!$llm) {
        $respond([
            'success' => false,
            'message' => 'No se obtuvo automáticamente y no hay un proveedor de IA configurado. Ingrésalo manualmente.',
        ]);
    }

    $pagina = ColombiaComScraper::pageTextFor($lotteryName);
    if (!$pagina) {
        $respond([
            'success' => false,
            'message' => 'No se pudo leer la página de la lotería para que la IA la revise. Ingrésalo manualmente.',
        ]);
    }

    $prompt = "Eres un extractor de datos. Del TEXTO de una página de resultados de lotería, "
        . "devuelve ÚNICAMENTE el número ganador (entre 2 y 6 dígitos) de la lotería «{$lotteryName}» "
        . "para el sorteo del {$drawDate}. Si el número para esa lotería y esa fecha no aparece de forma "
        . "clara en el texto, responde exactamente NONE. No expliques nada, no inventes: responde solo el "
        . "número o NONE.\n\n--- TEXTO DE LA PÁGINA ---\n" . $pagina['text'];

    $c = $llm->chat([
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'max_tokens'  => 32,
        'temperature' => 0,
    ]);

    $texto = trim((string)($c['texto'] ?? $c['content'] ?? $c['text'] ?? ''));
    if (!($c['ok'] ?? false) || $texto === '') {
        $respond([
            'success' => false,
            'message' => 'La IA no pudo leer un número. Ingrésalo manualmente.',
        ]);
    }

    // Extraer el primer bloque de 2-6 dígitos de la respuesta; validar.
    if (stripos($texto, 'NONE') !== false || !preg_match('/\b(\d{2,6})\b/', $texto, $m)) {
        $respond([
            'success' => false,
            'message' => 'La IA no encontró el número para esa lotería y fecha en la página. Ingrésalo manualmente.',
        ]);
    }

    $respond([
        'success' => true,
        'number'  => $m[1],
        'method'  => 'ia',
        'source'  => $pagina['url'],
        'note'    => 'Sugerido por IA leyendo la página. NO está verificado: revísalo contra la fuente oficial antes de guardar.',
    ]);

} catch (Throwable $e) {
    error_log('lottery-results/suggest error: ' . $e->getMessage());
    $respond(['success' => false, 'message' => 'Error al generar la sugerencia'], 500);
}

/**
 * Arranca el motor scoped al super_admin y devuelve su LLM principal, o null
 * si no hay proveedor configurado. Reutiliza la config del módulo WhatsApp IA.
 */
function cargarLlmDelSuperAdmin(PDO $pdo, int $vendorId)
{
    if (!$vendorId) return null;
    require_once __DIR__ . '/../../../vendor/autoload.php';
    require_once __DIR__ . '/../../whatsapp/MisRifasDb.php';
    require_once __DIR__ . '/../../whatsapp/MisRifasTenant.php';
    require_once __DIR__ . '/../../whatsapp/MisRifasSecret.php';
    require_once __DIR__ . '/../../whatsapp/MisRifasStorage.php';
    require_once __DIR__ . '/../../whatsapp/RaffleDomainAdapter.php';
    require_once __DIR__ . '/../../../config/brand.php';

    try {
        \ElkinLinan\WhatsappAiEngine\Engine::arrancar([
            'db'      => new MisRifasDb($pdo),
            'dominio' => new RaffleDomainAdapter($vendorId),
            'archivo' => new MisRifasStorage($vendorId),
            'secreto' => new MisRifasSecret(),
            'negocio' => new MisRifasTenant($vendorId, plataforma('nombre')),
            'formato' => new \ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos(),
            'funcion' => new \ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido(),
            'config'  => new \ElkinLinan\WhatsappAiEngine\Defecto\SinUrl(),
        ]);
        $db  = \ElkinLinan\WhatsappAiEngine\Engine::db();
        $cfg = \ElkinLinan\WhatsappAiEngine\Core\WaConfig::cargar($db, true);
        $prov   = (string)($cfg['llm_proveedor'] ?? '');
        $modelo = (string)($cfg['llm_modelo'] ?? '');
        $clave  = \ElkinLinan\WhatsappAiEngine\Core\WaConfig::secreto($cfg, 'llm_api_key');
        if ($prov === '' || $modelo === '' || !$clave) return null;
        return \ElkinLinan\WhatsappAiEngine\Providers\LlmProviderManager::crear($prov, $clave, $modelo);
    } catch (\Throwable $e) {
        error_log('lottery suggest LLM bootstrap: ' . $e->getMessage());
        return null;
    }
}
