<?php
/**
 * API: Update Raffle
 * POST /api/admin/raffles/update.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/utils/Logger.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';
require_once __DIR__ . '/../../../api/utils/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        Response::error('ID de rifa requerido');
    }

    $raffleId = intval($input['id']);

    // Check permissions
    if ($adminUser['role'] !== 'super_admin') {
        $stmt = $db->prepare("SELECT id FROM raffles WHERE id = ? AND created_by = ?");
        $stmt->execute([$raffleId, $adminUser['id']]);
        if (!$stmt->fetch()) {
            Response::error('No tienes permisos para editar esta rifa', null, 403);
        }
    }

    $actual = $db->prepare('SELECT * FROM raffles WHERE id = ?');
    $actual->execute([$raffleId]);
    $rifa = $actual->fetch(PDO::FETCH_ASSOC);
    if (!$rifa) {
        Response::error('Rifa no encontrada', null, 404);
    }

    // ¿Ya hay boletos comprometidos? Manda sobre qué se puede tocar: la
    // ESTRUCTURA (cifras, oportunidades, modo de ganar, precio) solo cambia
    // mientras nadie haya reservado ni pagado — cambiar las reglas con
    // boletos vendidos sería moverle el piso al comprador.
    $comp = $db->prepare("SELECT COUNT(*) FROM tickets WHERE raffle_id = ? AND status <> 'available'");
    $comp->execute([$raffleId]);
    $hayVentas = (int)$comp->fetchColumn() > 0;

    $fields = [];
    $params = [];

    // Sanitizado igual que api/raffles/create.php - faltaba aqui, y ese hueco
    // era un XSS almacenado: name/description se renderizan sin escapar via
    // innerHTML tanto en el dashboard del super_admin como en el storefront
    // publico (raffle.php).
    if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = Validator::sanitize($input['name']); }
    if (isset($input['status'])) { $fields[] = 'status = ?'; $params[] = $input['status']; }
    if (isset($input['whatsapp_contact'])) { $fields[] = 'whatsapp_contact = ?'; $params[] = Validator::sanitize($input['whatsapp_contact']); }
    if (isset($input['responsible_person'])) { $fields[] = 'responsible_person = ?'; $params[] = Validator::sanitize($input['responsible_person']); }
    if (isset($input['description'])) { $fields[] = 'description = ?'; $params[] = Validator::sanitize($input['description']); }
    if (isset($input['city'])) { $fields[] = 'city = ?'; $params[] = Validator::sanitize($input['city']); }
    if (isset($input['department'])) { $fields[] = 'department = ?'; $params[] = Validator::sanitize($input['department']); }

    // Imagen principal + galería: misma guarda anti-SSRF y normalización que create.
    if (isset($input['image_url'])) {
        $img = trim((string)$input['image_url']);
        if ($img !== '' && $img[0] !== '/') { $img = '/' . $img; }
        if ($img !== '' && !Validator::esRutaLocalSegura($img)) {
            Response::error('image_url invalida: solo se permiten rutas locales', null, 400);
        }
        $fields[] = 'image_url = ?';
        $params[] = $img !== '' ? $img : '/assets/images/placeholder.svg';
    }

    // Fecha/lotería: siempre editables (reprogramar es legítimo), pero la
    // fecha debe caer el día que juega la lotería elegida.
    $nuevaFecha = isset($input['draw_date']) ? str_replace('T', ' ', (string)$input['draw_date']) : null;
    $nuevaLoteria = isset($input['lottery_id']) ? (int)$input['lottery_id'] : null;
    if ($nuevaFecha !== null || $nuevaLoteria !== null) {
        $lotId = $nuevaLoteria ?? (int)$rifa['lottery_id'];
        $fecha = $nuevaFecha ?? (string)$rifa['draw_date'];
        $lot = $db->prepare('SELECT name, day_of_week FROM lotteries WHERE id = ?');
        $lot->execute([$lotId]);
        $l = $lot->fetch(PDO::FETCH_ASSOC);
        if (!$l) {
            Response::error('Lotería inválida', null, 422);
        }
        if (strtolower(date('l', strtotime($fecha))) !== $l['day_of_week']) {
            Response::error('La ' . $l['name'] . ' no juega ese día: elige una fecha que caiga el día correcto.', 'DRAW_DAY_MISMATCH', 422);
        }
        if ($nuevaFecha !== null) {
            $fields[] = 'draw_date = ?';
            $params[] = $fecha;
            $fields[] = 'cutoff_at = ?';
            $params[] = date('Y-m-d H:i:s', strtotime($fecha . ' -2 days'));
        }
        if ($nuevaLoteria !== null) { $fields[] = 'lottery_id = ?'; $params[] = $lotId; }
    }

    // ── Estructura (precio, cifras, oportunidades, modo) ──
    $regenerar = false;
    $digits = (int)$rifa['digits'];
    $opps = (int)$rifa['opportunities'];
    $estructurales = [];
    if (isset($input['ticket_price']) && (float)$input['ticket_price'] !== (float)$rifa['ticket_price']) {
        $estructurales[] = 'precio';
    }
    if (isset($input['digits']) && (int)$input['digits'] !== $digits) { $estructurales[] = 'cifras'; }
    if (isset($input['opportunities']) && (int)$input['opportunities'] !== $opps) { $estructurales[] = 'oportunidades'; }
    if (isset($input['winning_mode']) && (string)$input['winning_mode'] !== (string)$rifa['winning_mode']) { $estructurales[] = 'modo de ganar'; }
    if ($estructurales && $hayVentas) {
        Response::error('Con boletos reservados o vendidos no se puede cambiar: ' . implode(', ', $estructurales)
            . '. Eso cambiaría las reglas a quienes ya compraron.', 'STRUCTURE_LOCKED', 409);
    }
    if ($estructurales) {
        if (isset($input['ticket_price'])) { $fields[] = 'ticket_price = ?'; $params[] = floatval($input['ticket_price']); }
        if (isset($input['digits'])) {
            $digits = (int)$input['digits'];
            if (!in_array($digits, [2, 3, 4], true)) { Response::error('Cifras inválidas (2-4)', null, 422); }
        }
        if (isset($input['opportunities'])) {
            $opps = (int)$input['opportunities'];
            if (!in_array($opps, [1, 2, 4, 5], true)) { Response::error('Oportunidades inválidas (1,2,4,5)', null, 422); }
        }
        if (isset($input['winning_mode'])) {
            $modosPorCifras = [2 => ['last_2', 'first_2'], 3 => ['last_3', 'first_3'], 4 => ['last_4']];
            if (!in_array((string)$input['winning_mode'], $modosPorCifras[$digits], true)) {
                Response::error('El modo de ganar no corresponde a las cifras elegidas', null, 422);
            }
            $fields[] = 'winning_mode = ?';
            $params[] = (string)$input['winning_mode'];
        }
        if (in_array('cifras', $estructurales, true) || in_array('oportunidades', $estructurales, true)) {
            // El talonario se recalcula del lado del servidor y se REGENERAN
            // los boletos (todos estaban 'available': nada se pierde).
            $totalNuevo = (int)floor(pow(10, $digits) / $opps);
            $fields[] = 'digits = ?';
            $params[] = $digits;
            $fields[] = 'opportunities = ?';
            $params[] = $opps;
            $fields[] = 'total_tickets = ?';
            $params[] = $totalNuevo;
            $regenerar = true;
        }
    }

    if (empty($fields) && empty($input['image_urls'])) {
        Response::error('No hay datos para actualizar');
    }

    if ($fields) {
        $fields[] = 'updated_at = NOW()';
        $params[] = $raffleId;
        $sql = "UPDATE raffles SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    if ($regenerar) {
        require_once __DIR__ . '/../../../api/repositories/BaseRepository.php';
        require_once __DIR__ . '/../../../api/repositories/TicketRepository.php';
        $db->prepare('DELETE FROM tickets WHERE raffle_id = ?')->execute([$raffleId]);
        (new TicketRepository())->generateTickets($raffleId, (int)floor(pow(10, $digits) / $opps), $digits, $opps);
    }

    // Galería: si viene image_urls, REEMPLAZA la galería (misma guarda que create).
    if (isset($input['image_urls']) && is_array($input['image_urls'])) {
        $safe = [];
        foreach ($input['image_urls'] as $u) {
            $u = trim((string)$u);
            if ($u !== '' && $u[0] !== '/') { $u = '/' . $u; }
            if ($u !== '' && Validator::esRutaLocalSegura($u)) { $safe[] = $u; }
        }
        $db->prepare('DELETE FROM raffle_images WHERE raffle_id = ?')->execute([$raffleId]);
        $ins = $db->prepare('INSERT INTO raffle_images (raffle_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($safe as $i => $u) {
            $ins->execute([$raffleId, $u, $i === 0 ? 1 : 0, $i]);
        }
    }

    // El cobro de la plataforma sigue al precio/talonario nuevo, pero SOLO si
    // aún no está pagado y el modo es porcentaje (la tarifa plana no depende).
    if (!$hayVentas && (int)$rifa['commission_paid'] === 0) {
        $cfg = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('commission_enabled','billing_mode','commission_percentage')") as $r) {
            $cfg[$r['setting_key']] = $r['setting_value'];
        }
        if (($cfg['commission_enabled'] ?? '0') === '1' && ($cfg['billing_mode'] ?? 'commission') === 'commission') {
            $db->prepare('UPDATE raffles SET commission_amount = (ticket_price * total_tickets) * (? / 100) WHERE id = ?')
               ->execute([(float)($cfg['commission_percentage'] ?? 0), $raffleId]);
        }
    }

    Logger::activity('raffle_updated', $adminUser['id'], ['raffle_id' => $raffleId,
        'regenerados' => $regenerar, 'estructura' => $estructurales]);

    Response::success(['message' => 'Rifa actualizada correctamente' . ($regenerar ? ' (boletos regenerados)' : ''),
        'regenerated' => $regenerar]);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al actualizar la rifa');
}
