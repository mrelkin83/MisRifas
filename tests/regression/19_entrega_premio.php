<?php
/**
 * Fase 9 — Entrega del premio con dos actores (promt2.md §13).
 *
 * - El vendedor no reporta entrega si el ganador no ha aceptado (409).
 * - Al aceptar, el vendedor recibe aviso con datos de contacto.
 * - Reportar entrega genera delivery_token DISTINTO al de aceptación y un
 *   mensaje NUEVO al ganador.
 * - El ganador confirma → delivery_confirmed (token invalidado) — solo eso
 *   va en verde. El token usado no sirve dos veces.
 * - La disputa marca disputed, exige motivo y avisa a vendedor y admin.
 */

section('Entrega del premio — doble token, confirmación y disputa');
$db = testdb();
$token = fxToken(5);

$raffle = fxRaffle(['tickets' => 10, 'draw_date' => fxNextLotteryDate(1) . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id = 5, status = 'completed' WHERE id = ?")->execute([$raffle]);
$buyer = fxBuyer();
$tid = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '05'")->fetchColumn();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE id=?")->execute([$buyer['id'], $tid]);

$acceptToken = bin2hex(random_bytes(24));
$db->prepare("
    INSERT INTO raffle_winners (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description, acceptance_token)
    VALUES (?, ?, ?, '05', '05', 'test', ?)
")->execute([$raffle, $tid, $buyer['id'], $acceptToken]);
$wid = (int)$db->lastInsertId();

// ── 1. Sin aceptación del ganador, el vendedor no reporta entrega ──
$res = httpPost('/api/vendor/delivery.php', ['raffle_id' => $raffle], $token);
check($res['code'] === 409, 'Reportar entrega sin aceptación del ganador → 409', 'HTTP ' . $res['code']);

// ── 2. El ganador acepta → aviso al vendedor con sus datos ──
$res = httpPost('/api/winners/accept.php', ['token' => $acceptToken, 'action' => 'accept']);
$ok = assertHttp(200, $res, 'El ganador acepta su premio');
$aviso = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id = $raffle AND subject LIKE '%aceptó su premio%'")->fetchColumn();
check($aviso >= 1, 'El vendedor recibe aviso con los datos del ganador', "avisos=$aviso");

// ── 3. Reportar entrega: EVIDENCIA obligatoria + token DISTINTO + mensaje NUEVO ──
// Sin foto → 422 (la evidencia del vendedor es obligatoria).
$res = httpPost('/api/vendor/delivery.php', ['raffle_id' => $raffle], $token);
check($res['code'] === 422 && ($res['json']['errors'] ?? '') === 'EVIDENCE_REQUIRED',
    'Reportar entrega SIN foto de evidencia → 422 EVIDENCE_REQUIRED', 'HTTP ' . $res['code']);

// Foto de evidencia real (GD) → 200.
$im = imagecreatetruecolor(80, 60);
imagestring($im, 5, 8, 20, 'ENTREGA', imagecolorallocate($im, 255, 255, 255));
ob_start();
imagejpeg($im);
$evidencia = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
imagedestroy($im);
$res = httpPost('/api/vendor/delivery.php', ['raffle_id' => $raffle, 'photo' => $evidencia], $token);
$ok = assertHttp(200, $res, 'Reportar entrega CON evidencia funciona tras la aceptación');
$w = $db->query("SELECT delivery_status, delivery_token, acceptance_token, delivery_vendor_photo_path FROM raffle_winners WHERE id = $wid")->fetch(PDO::FETCH_ASSOC);
check($w['delivery_status'] === 'delivery_reported', 'Estado delivery_reported', $w['delivery_status']);
check(!empty($w['delivery_vendor_photo_path']) && is_file(__DIR__ . '/../../storage/entregas/' . $w['delivery_vendor_photo_path']),
    'La evidencia del vendedor queda re-codificada en storage/entregas', $w['delivery_vendor_photo_path'] ?? 'NULL');
onTeardown(function () use ($w) { @unlink(__DIR__ . '/../../storage/entregas/' . ($w['delivery_vendor_photo_path'] ?? 'x')); });
check(!empty($w['delivery_token']) && $w['delivery_token'] !== $w['acceptance_token'],
    'delivery_token existe y es DISTINTO al acceptance_token', '');
$msgGanador = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id = $raffle AND subject LIKE '%Recibiste tu premio%'")->fetchColumn();
check($msgGanador >= 1, 'El ganador recibe el mensaje NUEVO de confirmación de entrega', "msgs=$msgGanador");

// Doble reporte → 409.
$res = httpPost('/api/vendor/delivery.php', ['raffle_id' => $raffle], $token);
check($res['code'] === 409, 'Reportar dos veces → 409', 'HTTP ' . $res['code']);

// ── 4. El GANADOR confirma con su token ──
$dToken = (string)$w['delivery_token'];
$res = httpGet('/api/winners/delivery.php?t=' . $dToken);
assertHttp(200, $res, 'La página de confirmación carga con el token');
check(strpos((string)($res['json']['data']['vendor_photo'] ?? ''), 'data:image/jpeg') === 0,
    'El ganador VE la evidencia del vendedor antes de confirmar', '');
$res = httpPost('/api/winners/delivery.php', ['token' => $dToken, 'action' => 'confirm']);
$ok = assertHttp(200, $res, 'El ganador confirma la entrega');
$w2 = $db->query("SELECT delivery_status, delivery_token, delivery_confirmed_at FROM raffle_winners WHERE id = $wid")->fetch(PDO::FETCH_ASSOC);
check($w2['delivery_status'] === 'delivery_confirmed' && !empty($w2['delivery_confirmed_at']),
    'delivery_confirmed con timestamp', json_encode($w2));
check($w2['delivery_token'] === null, 'El token se invalida al usarse', var_export($w2['delivery_token'], true));

// Token usado no sirve dos veces.
$res = httpPost('/api/winners/delivery.php', ['token' => $dToken, 'action' => 'confirm']);
check($res['code'] === 404, 'El token usado ya no funciona (404)', 'HTTP ' . $res['code']);

// El hall expone delivery_status.
$res = httpGet('/api/raffles/winners.php');
$fila = null;
foreach (($res['json']['data'] ?? $res['json'] ?? []) as $x) {
    if (is_array($x) && (int)($x['raffle_id'] ?? 0) === $raffle) { $fila = $x; break; }
}
check($fila !== null && ($fila['delivery_status'] ?? '') === 'delivery_confirmed',
    'El hall de ganadores expone la entrega confirmada', json_encode($fila['delivery_status'] ?? null));

// ── 5. Disputa (nuevo ganador de otra rifa) ──
$raffle2 = fxRaffle(['tickets' => 10, 'draw_date' => fxNextLotteryDate(1) . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id = 5, status = 'completed' WHERE id = ?")->execute([$raffle2]);
$tid2 = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle2 AND ticket_number = '07'")->fetchColumn();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE id=?")->execute([$buyer['id'], $tid2]);
$db->prepare("
    INSERT INTO raffle_winners (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description, acceptance_status, delivery_status, delivery_token)
    VALUES (?, ?, ?, '07', '07', 'test', 'accepted', 'delivery_reported', ?)
")->execute([$raffle2, $tid2, $buyer['id'], $d2 = bin2hex(random_bytes(32))]);
$wid2 = (int)$db->lastInsertId();

// Sin motivo → 422.
$res = httpPost('/api/winners/delivery.php', ['token' => $d2, 'action' => 'dispute', 'reason' => 'x']);
check($res['code'] === 422, 'Disputa sin motivo suficiente → 422', 'HTTP ' . $res['code']);

$res = httpPost('/api/winners/delivery.php', ['token' => $d2, 'action' => 'dispute', 'reason' => 'Quedamos en encontrarnos y nunca llegó']);
$ok = assertHttp(200, $res, 'La disputa se registra');
$w3 = $db->query("SELECT delivery_status, dispute_reason, delivery_token FROM raffle_winners WHERE id = $wid2")->fetch(PDO::FETCH_ASSOC);
check($w3['delivery_status'] === 'disputed' && !empty($w3['dispute_reason']) && $w3['delivery_token'] === null,
    'disputed con motivo y token invalidado', json_encode($w3));
$avisos = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id = $raffle2 AND subject LIKE '%Disputa de entrega%'")->fetchColumn();
check($avisos >= 2, 'Vendedor Y admin notificados de la disputa', "avisos=$avisos");

// ── 6. Perfil público del organizador (§13.5) ──
$slug = (string)$db->query("SELECT slug FROM vendors WHERE id = 5")->fetchColumn();
$res = httpGet('/public/organizador.php?slug=' . $slug);
check($res['code'] === 200 && strpos($res['raw'], 'Entregas confirmadas') !== false && strpos($res['raw'], 'Disputas abiertas') !== false,
    'El perfil público muestra la reputación (entregas y disputas)', 'HTTP ' . $res['code']);
