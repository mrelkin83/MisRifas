<?php
/**
 * Fase 8 — Sorteo y reprogramación (promt2.md §12).
 *
 * - Todo intento queda en raffle_draws (transparencia pública).
 * - Modo AUTO: el sistema reagenda (fecha siguiente de la MISMA lotería),
 *   recalcula cutoff y conserva los paid.
 * - Modo MANUAL: la rifa pasa a pending_reschedule; el vendedor elige fecha
 *   válida; fecha inválida se rechaza; al confirmar vuelve a active y los
 *   compradores pagados quedan notificados.
 * - GUARDA: una rifa con ganador JAMÁS se reprograma (incidente).
 * - TOPE: al 4º desenlace sin ganador → cancelled + lista de devoluciones.
 */

section('Reprogramación — registro público, modos, guardas y tope');
$db = testdb();
$LOT = 3; // lotería dedicada para aislar este grupo
$token = fxToken(5);

$otras = (int)$db->query("SELECT COUNT(*) FROM raffles WHERE status='active' AND draw_date<=NOW() AND lottery_id=$LOT")->fetchColumn();
$resHoy = (int)$db->query("SELECT COUNT(*) FROM lottery_results WHERE lottery_id=$LOT AND draw_date=CURDATE()")->fetchColumn();
if ($otras > 0 || $resHoy > 0) {
    check(false, 'Entorno aislado (lotería 3 libre)', "otras=$otras resHoy=$resHoy — saltado");
    return;
}

// Resultado verificado de HOY: '7799' → last_2 = '99' (no existe en talonarios de 10).
$db->exec("INSERT INTO lottery_results (lottery_id, draw_date, winning_number, verified) VALUES ($LOT, CURDATE(), '7799', 1)");
onTeardown(function () use ($db, $LOT) { $db->exec("DELETE FROM lottery_results WHERE lottery_id=$LOT AND draw_date=CURDATE()"); });

$prevMode = $db->query("SELECT setting_value FROM system_settings WHERE setting_key='reschedule_mode'")->fetchColumn();
onTeardown(function () use ($db, $prevMode) {
    $db->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='reschedule_mode'")->execute([$prevMode]);
});

$mkEligible = function () use ($db, $LOT) {
    $r = fxRaffle(['tickets' => 10, 'draw_date' => date('Y-m-d') . ' 00:00:01', 'created_by' => 5, 'lottery_id' => $LOT]);
    $db->prepare("UPDATE raffles SET vendor_id=5, status='active', cutoff_at=DATE_SUB(draw_date, INTERVAL 2 DAY) WHERE id=?")->execute([$r]);
    return $r;
};

// ── 1. Modo AUTO: not_sold → reagendado con registro público ──
$db->exec("UPDATE system_settings SET setting_value='auto' WHERE setting_key='reschedule_mode'");
$rAuto = $mkEligible();
$buyer = fxBuyer();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE raffle_id=? AND ticket_number='05'")->execute([$buyer['id'], $rAuto]);

$cron = runCron('cron/process_draws.php');
check($cron['rc'] === 0, 'process_draws corre (auto)', 'rc=' . $cron['rc'] . ' ' . substr($cron['out'], 0, 120));

$draw = $db->query("SELECT attempt, winning_number, outcome, ticket_status, rescheduled_to FROM raffle_draws WHERE raffle_id=$rAuto")->fetch(PDO::FETCH_ASSOC);
check($draw && $draw['outcome'] === 'not_sold' && (int)$draw['attempt'] === 1, 'raffle_draws registró el intento (not_sold, attempt 1)', json_encode($draw));
check(!empty($draw['rescheduled_to']), 'El intento registra la nueva fecha', $draw['rescheduled_to'] ?? 'NULL');
$r = $db->query("SELECT status, draw_rescheduled_count, draw_date, cutoff_at FROM raffles WHERE id=$rAuto")->fetch(PDO::FETCH_ASSOC);
check($r['status'] === 'active' && (int)$r['draw_rescheduled_count'] === 1, 'AUTO: la rifa sigue active con contador+1', json_encode($r));
check(strtotime($r['draw_date']) > time(), 'La nueva fecha es futura', $r['draw_date']);
check(date('Y-m-d', strtotime($r['cutoff_at'])) === date('Y-m-d', strtotime($r['draw_date'] . ' -2 days')), 'cutoff_at recalculado (sorteo - 2 días)', $r['cutoff_at']);
$paid = $db->query("SELECT status FROM tickets WHERE raffle_id=$rAuto AND ticket_number='05'")->fetchColumn();
check($paid === 'paid', 'Los boletos pagados se conservan tal cual', "status=$paid");

// ── 2. Modo MANUAL: pending_reschedule + flujo del vendedor ──
$db->exec("UPDATE system_settings SET setting_value='manual' WHERE setting_key='reschedule_mode'");
$rMan = $mkEligible();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE raffle_id=? AND ticket_number='05'")->execute([$buyer['id'], $rMan]);

$cron = runCron('cron/process_draws.php');
check($cron['rc'] === 0, 'process_draws corre (manual)', 'rc=' . $cron['rc']);
$st = $db->query("SELECT status FROM raffles WHERE id=$rMan")->fetchColumn();
check($st === 'pending_reschedule', 'MANUAL: la rifa queda pending_reschedule', "status=$st");
$avisoV = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$rMan AND subject LIKE '%necesita reprogramación%'")->fetchColumn();
check($avisoV >= 1, 'El vendedor recibe el aviso para reprogramar', "avisos=$avisoV");
$avisoEvento = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$rMan AND recipient_user_id={$buyer['id']} AND subject LIKE '%nadie ganó%'")->fetchColumn();
check($avisoEvento >= 1, 'El comprador pagado se entera del EVENTO de inmediato (sin esperar nueva fecha)', "avisos=$avisoEvento");

// GET: fechas válidas e historial.
$res = httpGet('/api/vendor/reschedule.php?raffle_id=' . $rMan, $token);
$ok = assertHttp(200, $res, 'GET reschedule responde');
$fechas = $res['json']['data']['fechas_validas'] ?? [];
check(count($fechas) === 4, 'Ofrece 4 fechas válidas de la misma lotería', json_encode($fechas));

// Fecha inválida (día equivocado) → 422.
$mala = date('Y-m-d', strtotime($fechas[0] . ' +1 day'));
$res = httpPost('/api/vendor/reschedule.php', ['raffle_id' => $rMan, 'new_draw_date' => $mala], $token);
check($res['code'] === 422, 'Fecha en día que la lotería no juega → 422', 'HTTP ' . $res['code']);

// Reprogramar bien.
$res = httpPost('/api/vendor/reschedule.php', ['raffle_id' => $rMan, 'new_draw_date' => $fechas[0]], $token);
$ok = assertHttp(200, $res, 'Reprogramación manual funciona');
$r = $db->query("SELECT status, draw_rescheduled_count FROM raffles WHERE id=$rMan")->fetch(PDO::FETCH_ASSOC);
check($r['status'] === 'active' && (int)$r['draw_rescheduled_count'] === 1, 'Vuelve a active con contador+1', json_encode($r));
$reprog = $db->query("SELECT rescheduled_to FROM raffle_draws WHERE raffle_id=$rMan AND attempt=1")->fetchColumn();
check(!empty($reprog), 'El historial público registra a dónde se reprogramó', (string)$reprog);
$notif = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$rMan AND message_type='no_winner' AND recipient_user_id={$buyer['id']}")->fetchColumn();
check($notif >= 1, 'El comprador pagado queda notificado de la nueva fecha', "notifs=$notif");

// Ya activa: segundo intento de reprogramar → 409.
$res = httpPost('/api/vendor/reschedule.php', ['raffle_id' => $rMan, 'new_draw_date' => $fechas[1]], $token);
check($res['code'] === 409, 'No se reprograma una rifa que no está pending_reschedule (409)', 'HTTP ' . $res['code']);

// ── 2b. Concertado es el DEFAULT + cambio de LOTERÍA al reprogramar ──
// Valor desconocido en reschedule_mode → se comporta como manual (concertado).
$db->exec("UPDATE system_settings SET setting_value='' WHERE setting_key='reschedule_mode'");
$rCross = $mkEligible();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE raffle_id=? AND ticket_number='05'")->execute([$buyer['id'], $rCross]);
$cron = runCron('cron/process_draws.php');
check($cron['rc'] === 0, 'process_draws corre (default)', 'rc=' . $cron['rc']);
$st = $db->query("SELECT status FROM raffles WHERE id=$rCross")->fetchColumn();
check($st === 'pending_reschedule', 'DEFAULT: sin modo explícito la reprogramación es concertada', "status=$st");

$res = httpGet('/api/vendor/reschedule.php?raffle_id=' . $rCross, $token);
assertHttp(200, $res, 'GET reschedule (cross) responde');
$opciones = $res['json']['data']['opciones'] ?? [];
check(count($opciones) > 4, 'Ofrece sorteos de TODAS las loterías activas (28 días)', 'opciones=' . count($opciones));
$otra = null;
foreach ($opciones as $o) {
    if ((int)$o['lottery_id'] !== $LOT) { $otra = $o; break; }
}
check($otra !== null, 'Hay opciones de OTRA lotería', json_encode($otra));

// Par inválido (fecha de una lotería con el id de otra) → 422.
$res = httpPost('/api/vendor/reschedule.php', ['raffle_id' => $rCross, 'new_draw_date' => date('Y-m-d', strtotime($otra['date'] . ' +1 day')), 'lottery_id' => $otra['lottery_id']], $token);
check($res['code'] === 422, 'Fecha que no corresponde a un sorteo real de esa lotería → 422', 'HTTP ' . $res['code']);

$res = httpPost('/api/vendor/reschedule.php', ['raffle_id' => $rCross, 'new_draw_date' => $otra['date'], 'lottery_id' => $otra['lottery_id']], $token);
assertHttp(200, $res, 'Reprogramar CAMBIANDO de lotería funciona');
$r = $db->query("SELECT status, lottery_id, draw_date FROM raffles WHERE id=$rCross")->fetch(PDO::FETCH_ASSOC);
check($r['status'] === 'active' && (int)$r['lottery_id'] === (int)$otra['lottery_id'], 'La rifa quedó active en la NUEVA lotería', json_encode($r));
check(substr((string)$r['draw_date'], 0, 10) === $otra['date'], 'La fecha es el sorteo real de la nueva lotería', $r['draw_date']);
$lotIntento = (int)$db->query("SELECT lottery_id FROM raffle_draws WHERE raffle_id=$rCross AND attempt=1")->fetchColumn();
check($lotIntento === $LOT, 'El historial conserva la lotería con la que JUGÓ cada intento', "lottery_id=$lotIntento");

// ── 3. GUARDA: rifa CON ganador jamás se reprograma ──
$rWin = fxRaffle(['tickets' => 10, 'draw_date' => date('Y-m-d') . ' 00:00:01', 'created_by' => 5, 'lottery_id' => 1]);
$db->prepare("UPDATE raffles SET vendor_id=5, status='pending_reschedule' WHERE id=?")->execute([$rWin]);
$tidW = (int)$db->query("SELECT id FROM tickets WHERE raffle_id=$rWin AND ticket_number='05'")->fetchColumn();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE id=?")->execute([$buyer['id'], $tidW]);
$db->prepare("INSERT INTO raffle_winners (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description) VALUES (?,?,?,?,?,?)")
   ->execute([$rWin, $tidW, $buyer['id'], '05', '05', 'test']);
$res = httpPost('/api/vendor/reschedule.php', ['raffle_id' => $rWin, 'new_draw_date' => $fechas[0]], $token);
check($res['code'] === 409 && ($res['json']['errors'] ?? '') === 'RESCHEDULE_NOT_ALLOWED',
    'Con ganador registrado: 409 RESCHEDULE_NOT_ALLOWED (aunque el estado dijera otra cosa)', 'HTTP ' . $res['code'] . ' ' . ($res['json']['errors'] ?? ''));

// ── 4. TOPE: al 4º desenlace sin ganador → cancelled + devoluciones ──
$db->exec("UPDATE system_settings SET setting_value='auto' WHERE setting_key='reschedule_mode'");
$rCap = $mkEligible();
$db->prepare("UPDATE raffles SET draw_rescheduled_count=3 WHERE id=?")->execute([$rCap]);
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE raffle_id=? AND ticket_number='05'")->execute([$buyer['id'], $rCap]);

$cron = runCron('cron/process_draws.php');
check($cron['rc'] === 0, 'process_draws corre (tope)', 'rc=' . $cron['rc']);
$st = $db->query("SELECT status FROM raffles WHERE id=$rCap")->fetchColumn();
check($st === 'cancelled', 'Al 4º desenlace sin ganador la rifa queda cancelled', "status=$st");
$dev = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$rCap AND subject LIKE '%lista de devoluciones%'")->fetchColumn();
check($dev >= 1, 'El vendedor recibe la lista de devoluciones', "avisos=$dev");
$avComp = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$rCap AND recipient_user_id={$buyer['id']} AND subject LIKE '%cancelada%'")->fetchColumn();
check($avComp >= 1, 'El comprador pagado sabe que la rifa se canceló', "avisos=$avComp");
$paid = $db->query("SELECT status FROM tickets WHERE raffle_id=$rCap AND ticket_number='05'")->fetchColumn();
check($paid === 'paid', 'La cancelación NO toca los boletos pagados (el vendedor devuelve)', "status=$paid");
