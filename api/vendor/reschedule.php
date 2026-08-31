<?php

declare(strict_types=1);

/**
 * API: Reprogramar sorteo (modo manual, promt2.md §12.2-§12.3)
 *
 * GET  /api/vendor/reschedule.php?raffle_id=N → fechas válidas + historial
 * POST /api/vendor/reschedule.php { raffle_id, new_draw_date: 'Y-m-d' }
 *
 * GUARDAS (no opcionales):
 * - Solo sobre rifas en 'pending_reschedule' — estado al que ÚNICAMENTE llega
 *   el sistema tras verificar que el ticket ganador no estaba pagado. Nunca
 *   por declaración del vendedor.
 * - Una rifa con ganador registrado JAMÁS se reprograma: intento = excepción
 *   + incidente de seguridad en bitácora.
 * - Tope de 3 reprogramaciones (draw_rescheduled_count <= 3).
 * - La nueva fecha: posterior a la anterior, futura, y un día real de la
 *   MISMA lotería (no se permite cambiar de lotería).
 * - Todos los tickets paid se conservan; compradores notificados con número,
 *   nueva fecha y motivo.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/services/DomainExceptions.php';
require_once __DIR__ . '/../../api/services/MessageBuilderService.php';
require_once __DIR__ . '/../../api/services/BoletaImage.php';

try {
    $vendor = Auth::requireVendor();
    $vendorId = (int)$vendor['id'];
    $db = Database::getInstance()->getConnection();

    $raffleId = (int)($_SERVER['REQUEST_METHOD'] === 'GET'
        ? ($_GET['raffle_id'] ?? 0)
        : ((json_decode(file_get_contents('php://input'), true) ?: [])['raffle_id'] ?? 0));

    $stmt = $db->prepare("
        SELECT r.*, l.name AS lottery_name, l.day_of_week, l.draw_time
        FROM raffles r JOIN lotteries l ON l.id = r.lottery_id
        WHERE r.id = ? AND COALESCE(r.vendor_id, r.created_by) = ?
    ");
    $stmt->execute([$raffleId, $vendorId]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$raffle) {
        Response::error('No tienes permiso sobre esta rifa', null, 403);
    }

    // GUARDA: con ganador registrado no hay reprogramación — nunca.
    $stmt = $db->prepare('SELECT id FROM raffle_winners WHERE raffle_id = ?');
    $stmt->execute([$raffleId]);
    if ($stmt->fetchColumn()) {
        Logger::warning('INCIDENTE SEGURIDAD: intento de reprogramar rifa CON ganador', [
            'raffle_id' => $raffleId, 'vendor_id' => $vendorId, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        throw new RescheduleNotAllowed($raffleId, 'la rifa ya tiene un ganador registrado');
    }

    // GUARDA: solo el estado que fija el sistema habilita la opción.
    if ($raffle['status'] !== 'pending_reschedule') {
        Response::error('Esta rifa no está pendiente de reprogramación (estado: ' . $raffle['status'] . ')', null, 409);
    }
    if ((int)$raffle['draw_rescheduled_count'] >= 3) {
        Response::error('La rifa agotó sus 3 reprogramaciones', null, 409);
    }

    // Próximas 4 fechas válidas de la MISMA lotería, posteriores al sorteo anterior.
    $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
    $targetDow = $dayMap[$raffle['day_of_week']] ?? 6;
    $fechas = [];
    $t = max(strtotime('tomorrow'), strtotime((string)$raffle['draw_date'] . ' +1 day'));
    while (count($fechas) < 4) {
        if ((int)date('w', $t) === $targetDow) {
            $fechas[] = date('Y-m-d', $t);
        }
        $t = strtotime('+1 day', $t);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $hist = $db->prepare('SELECT attempt, draw_date, winning_number, ticket_status, outcome, rescheduled_to FROM raffle_draws WHERE raffle_id = ? ORDER BY attempt');
        $hist->execute([$raffleId]);
        Response::success([
            'raffle' => ['id' => $raffleId, 'name' => $raffle['name'], 'lottery' => $raffle['lottery_name']],
            'fechas_validas' => $fechas,
            'reprogramaciones_usadas' => (int)$raffle['draw_rescheduled_count'],
            'reprogramaciones_restantes' => 3 - (int)$raffle['draw_rescheduled_count'],
            'historial' => $hist->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $newDate = (string)($input['new_draw_date'] ?? '');
    if (!in_array($newDate, $fechas, true)) {
        Response::error('La fecha debe ser uno de los próximos sorteos de la ' . $raffle['lottery_name'] . ': ' . implode(', ', $fechas), 'INVALID_DATE', 422);
    }
    $newDrawDate = $newDate . ' ' . substr((string)$raffle['draw_time'], 0, 8);

    $db->prepare("
        UPDATE raffles
        SET draw_date = ?, cutoff_at = DATE_SUB(?, INTERVAL 2 DAY),
            draw_rescheduled_count = draw_rescheduled_count + 1,
            status = 'active'
        WHERE id = ? AND status = 'pending_reschedule'
    ")->execute([$newDrawDate, $newDrawDate, $raffleId]);

    // Historial público: el intento que originó esta reprogramación.
    $db->prepare("
        UPDATE raffle_draws SET rescheduled_to = ?
        WHERE raffle_id = ? AND rescheduled_to IS NULL
        ORDER BY attempt DESC LIMIT 1
    ")->execute([$newDrawDate, $raffleId]);

    Logger::activity('raffle_rescheduled', $vendorId, [
        'raffle_id' => $raffleId, 'new_draw_date' => $newDrawDate,
        'attempt' => (int)$raffle['draw_rescheduled_count'] + 1,
    ]);

    // Boletas: la página es dinámica, pero el PNG cacheado mostraba la fecha vieja.
    $codes = $db->prepare('SELECT ticket_code FROM tickets WHERE raffle_id = ? AND ticket_code IS NOT NULL');
    $codes->execute([$raffleId]);
    foreach ($codes->fetchAll(PDO::FETCH_COLUMN) as $code) {
        BoletaImage::invalidateCache((string)$code);
    }

    // §12.2: notificar a TODOS los compradores con boleto pagado (número,
    // nueva fecha, motivo) por email + WhatsApp, agrupado por comprador.
    $stmt = $db->prepare("
        SELECT t.ticket_number, t.user_id, u.name, u.phone_whatsapp, u.email
        FROM tickets t LEFT JOIN users u ON u.id = t.user_id
        WHERE t.raffle_id = ? AND t.status = 'paid'
    ");
    $stmt->execute([$raffleId]);
    $porComprador = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tk) {
        $uid = (int)$tk['user_id'];
        $porComprador[$uid]['name'] = $tk['name'];
        $porComprador[$uid]['phone'] = $tk['phone_whatsapp'];
        $porComprador[$uid]['email'] = $tk['email'];
        $porComprador[$uid]['tickets'][] = $tk['ticket_number'];
    }
    $lastStmt = $db->prepare('SELECT winning_number FROM raffle_draws WHERE raffle_id = ? ORDER BY attempt DESC LIMIT 1');
    $lastStmt->execute([$raffleId]);
    $ultimoNumero = (string)($lastStmt->fetchColumn() ?: '—');

    $ins = $db->prepare("
        INSERT INTO message_queue
            (raffle_id, vendor_id, recipient_user_id, recipient_phone, recipient_email,
             channel, message_type, subject, body_text, status, scheduled_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'no_winner', ?, ?, 'pending', NOW(), NOW())
    ");
    foreach ($porComprador as $uid => $c) {
        $msg = MessageBuilderService::buildResorteoMessage(
            ['name' => $raffle['name'], 'draw_date' => $raffle['draw_date'], 'image_url' => $raffle['image_url'] ?? ''],
            $c['tickets'],
            ['name' => $c['name']],
            ['name' => $raffle['lottery_name']],
            $ultimoNumero,
            $newDrawDate
        );
        if (!empty($c['email']) && filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
            $ins->execute([$raffleId, $vendorId, $uid, null, $c['email'], 'email', $msg['subject'], $msg['body_text']]);
        }
        if (!empty($c['phone'])) {
            $ins->execute([$raffleId, $vendorId, $uid, $c['phone'], null, 'whatsapp', $msg['subject'], $msg['body_text']]);
        }
    }

    Response::success([
        'new_draw_date' => $newDrawDate,
        'reprogramaciones_usadas' => (int)$raffle['draw_rescheduled_count'] + 1,
    ], 'Sorteo reprogramado para el ' . date('d/m/Y', strtotime($newDrawDate)) . '. Los boletos pagados se conservan y los compradores fueron notificados.');
} catch (RescheduleNotAllowed $e) {
    Response::error($e->getMessage(), 'RESCHEDULE_NOT_ALLOWED', 409);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al reprogramar');
}
