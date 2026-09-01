<?php
/**
 * API: Configuración y estado del scraper de resultados (solo super_admin).
 *
 * GET  /api/admin/scraper.php
 *      → interruptor, última corrida, sorteos pendientes, loterías con su
 *        slug efectivo/override y últimos resultados guardados (estado VIVO).
 * POST /api/admin/scraper.php {action:'guardar', enabled, sources:{id:slug}}
 * POST /api/admin/scraper.php {action:'probar', lottery_id}   → scrape en vivo
 * POST /api/admin/scraper.php {action:'ejecutar'}             → corre pendientes ya
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/services/ScraperRunner.php';
require_once __DIR__ . '/../../api/services/ColombiaComScraper.php';

try {
    $admin = Auth::requireRole('super_admin');
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $lastRaw = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'scraper_last_run'")->fetchColumn();
        $loterias = [];
        foreach ($db->query("SELECT id, name, active, api_source, day_of_week, draw_time FROM lotteries ORDER BY name") as $l) {
            $loterias[] = [
                'id' => (int)$l['id'],
                'name' => $l['name'],
                'active' => (bool)$l['active'],
                'slug_auto' => ColombiaComScraper::slugPara($l['name']),
                'api_source' => (string)($l['api_source'] ?? ''),
                'day_of_week' => (string)$l['day_of_week'],
                'draw_time' => substr((string)$l['draw_time'], 0, 5),
            ];
        }
        $recientes = $db->query("
            SELECT lr.draw_date, lr.winning_number, lr.scrape_source, lr.scraped_at, lr.verified, l.name AS lottery_name
            FROM lottery_results lr JOIN lotteries l ON l.id = lr.lottery_id
            ORDER BY lr.draw_date DESC, lr.scraped_at DESC LIMIT 8
        ")->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'enabled' => ScraperRunner::habilitado($db),
            'last_run' => $lastRaw !== '' ? (json_decode($lastRaw, true) ?: null) : null,
            'pendientes' => ScraperRunner::pendientes($db),
            'loterias' => $loterias,
            'recientes' => $recientes,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = (string)($input['action'] ?? '');

        if ($action === 'guardar') {
            $enabled = !empty($input['enabled']) ? '1' : '0';
            $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'scraper_enabled'")
               ->execute([$enabled]);
            $sources = is_array($input['sources'] ?? null) ? $input['sources'] : [];
            $upd = $db->prepare("UPDATE lotteries SET api_source = ? WHERE id = ?");
            foreach ($sources as $id => $slug) {
                $slug = trim((string)$slug);
                // Solo un slug simple (segmento de URL en colombia.com/loterias/…)
                if ($slug !== '' && !preg_match('/^[a-z0-9][a-z0-9\-]{1,120}$/', $slug)) {
                    Response::error("Slug inválido para la lotería $id: usa solo minúsculas, números y guiones.", null, 422);
                }
                $upd->execute([$slug === '' ? null : $slug, (int)$id]);
            }
            Logger::activity('scraper_config_updated', (int)$admin['id'], ['enabled' => $enabled, 'sources' => count($sources)]);
            Response::success(['message' => 'Configuración del scraper guardada']);
        }

        if ($action === 'probar') {
            $id = (int)($input['lottery_id'] ?? 0);
            $stmt = $db->prepare("SELECT name, api_source FROM lotteries WHERE id = ?");
            $stmt->execute([$id]);
            $l = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$l) {
                Response::error('Lotería no encontrada', null, 404);
            }
            $slug = trim((string)($input['slug'] ?? '')) ?: trim((string)($l['api_source'] ?? ''));
            if ($slug !== '' && !preg_match('/^[a-z0-9][a-z0-9\-]{1,120}$/', $slug)) {
                Response::error('Slug inválido: usa solo minúsculas, números y guiones.', null, 422);
            }
            $efectivo = $slug !== '' ? $slug : ColombiaComScraper::slugPara($l['name']);
            // Prueba EN VIVO contra colombia.com (nada simulado): el último
            // resultado publicado para esa lotería. NO guarda nada.
            $numero = ColombiaComScraper::fetchResult($l['name'], date('Y-m-d'), $slug);
            Response::success([
                'lottery' => $l['name'],
                'slug' => $efectivo,
                'url' => 'https://www.colombia.com/loterias/' . $efectivo,
                'number' => $numero,
                'message' => $numero
                    ? "Último número publicado: {$numero} (leído en vivo; verifica la fecha en la fuente)"
                    : 'La página no devolvió un número: revisa el slug o si la lotería ya publicó resultado.',
            ]);
        }

        if ($action === 'ejecutar') {
            if (!ScraperRunner::habilitado($db)) {
                Response::error('El scraper está APAGADO: enciéndelo antes de ejecutar.', null, 409);
            }
            $r = ScraperRunner::correr($db);
            Logger::activity('scraper_run_manual', (int)$admin['id'], ['saved' => $r['saved'], 'pending' => $r['pending']]);
            Response::success($r + ['message' => "Corrida terminada: {$r['saved']} guardado(s) de {$r['pending']} pendiente(s)"]);
        }

        Response::error('Acción no válida', null, 422);
    }

    Response::error('Método no permitido', null, 405);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en la configuración del scraper');
}
