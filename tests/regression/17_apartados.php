<?php
/**
 * Fase 7 — Apartados del vendedor, el "fiado" (promt2.md §8).
 *
 * - Apartar exige nombre+celular; solo el vendedor dueño; nunca tras el corte.
 * - Un held bloquea al comprador público (409) y se ve ocupado.
 * - mark_paid → paid con boleta y fila de users; release → available.
 * - El cron libera los held al pasar cutoff_at y avisa al vendedor.
 * - Invariante 2.4: un held NO gana (process_draws solo mira paid).
 */

section('Apartados (fiado) — reglas, cartera, corte y guardas');
$db = testdb();
$token = fxToken(5);

$raffle = fxRaffle(['tickets' => 10, 'draw_date' => fxNextLotteryDate(1, '+30 days') . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id = 5, status = 'active', cutoff_at = DATE_SUB(draw_date, INTERVAL 2 DAY) WHERE id = ?")->execute([$raffle]);
onTeardown(function () use ($db, $raffle) {
    $db->prepare("UPDATE tickets SET user_id = NULL WHERE raffle_id = ?")->execute([$raffle]);
    $db->prepare("DELETE FROM ticket_events WHERE raffle_id = ?")->execute([$raffle]);
    $db->prepare("DELETE FROM message_queue WHERE raffle_id = ?")->execute([$raffle]);
    $db->exec("DELETE FROM users WHERE phone_whatsapp = '3006660001' AND name LIKE '__TEST__%'");
});

// ── 1. Sin nombre/celular se rechaza ──
$res = httpPost('/api/vendor/holds.php', ['action' => 'hold', 'raffle_id' => $raffle, 'ticket_number' => '03', 'holder_name' => 'X', 'holder_phone' => '12'], $token);
check($res['code'] === 422, 'Apartar sin nombre/celular válidos → 422', 'HTTP ' . $res['code']);

// Sin autenticación (comprador desde enlace público) → 401.
$res = httpPost('/api/vendor/holds.php', ['action' => 'hold', 'raffle_id' => $raffle, 'ticket_number' => '03', 'holder_name' => '__TEST__ Amigo Fiado', 'holder_phone' => '3006660001']);
check($res['code'] === 401, 'El comprador público no puede apartar (401)', 'HTTP ' . $res['code']);

// ── 2. Apartar bien ──
$res = httpPost('/api/vendor/holds.php', ['action' => 'hold', 'raffle_id' => $raffle, 'ticket_number' => '03', 'holder_name' => '__TEST__ Amigo Fiado', 'holder_phone' => '3006660001', 'note' => 'paga el viernes'], $token);
$ok = assertHttp(200, $res, 'Apartar con nombre+celular funciona');
$t = $db->query("SELECT status, holder_name, held_by_vendor_id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '03'")->fetch(PDO::FETCH_ASSOC);
check($t['status'] === 'held' && (int)$t['held_by_vendor_id'] === 5, 'El boleto queda held a nombre del vendedor', json_encode($t));

// El comprador público NO puede reservar un held.
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['03'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Comprador', 'phone' => '3006669999', 'email' => 'fiadopub@test.local'],
]);
check($res['code'] === 409, 'Un held bloquea la compra pública (409)', 'HTTP ' . $res['code']);
$db->exec("DELETE FROM users WHERE phone_whatsapp = '3006669999' AND name LIKE '__TEST__%'");

// La cartera lo muestra.
$res = httpGet('/api/vendor/holds.php?raffle_id=' . $raffle, $token);
$ok = assertHttp(200, $res, 'La cartera responde');
check((int)($res['json']['data']['cantidad'] ?? 0) === 1 && (float)($res['json']['data']['total_apartado'] ?? 0) === 1000.0,
    'Cartera: 1 apartado por $1.000', json_encode($res['json']['data'] ?? []));

// ── 3. mark_paid: held → paid con boleta y users ──
$tid = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '03'")->fetchColumn();
$res = httpPost('/api/vendor/holds.php', ['action' => 'mark_paid', 'ticket_id' => $tid, 'payment_method' => 'cash'], $token);
$ok = assertHttp(200, $res, 'Cobrar el apartado funciona');
$t = $db->query("SELECT status, payment_method, ticket_code, user_id FROM tickets WHERE id = $tid")->fetch(PDO::FETCH_ASSOC);
check($t['status'] === 'paid' && $t['payment_method'] === 'cash' && !empty($t['ticket_code']) && !empty($t['user_id']),
    'held→paid con boleta emitida, método y fila de users', json_encode($t));

// ── 4. release: held → available ──
$res = httpPost('/api/vendor/holds.php', ['action' => 'hold', 'raffle_id' => $raffle, 'ticket_number' => '04', 'holder_name' => '__TEST__ Amigo Fiado', 'holder_phone' => '3006660001'], $token);
assertHttp(200, $res, 'Segundo apartado funciona');
$tid4 = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '04'")->fetchColumn();
$res = httpPost('/api/vendor/holds.php', ['action' => 'release', 'ticket_id' => $tid4], $token);
assertHttp(200, $res, 'Liberar el apartado funciona');
$st = $db->query("SELECT status FROM tickets WHERE id = $tid4")->fetchColumn();
check($st === 'available', 'El número liberado vuelve a la venta', "status=$st");

// ── 5. Corte: el cron libera y avisa ──
$res = httpPost('/api/vendor/holds.php', ['action' => 'hold', 'raffle_id' => $raffle, 'ticket_number' => '05', 'holder_name' => '__TEST__ Amigo Fiado', 'holder_phone' => '3006660001'], $token);
assertHttp(200, $res, 'Tercer apartado funciona');
$db->prepare("UPDATE raffles SET cutoff_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?")->execute([$raffle]);
$cron = runCron('cron/expire-reservations.php');
check($cron['rc'] === 0, 'El cron de expiración corre', 'rc=' . $cron['rc']);
$st = $db->query("SELECT status FROM tickets WHERE raffle_id = $raffle AND ticket_number = '05'")->fetchColumn();
check($st === 'available', 'Al pasar cutoff_at el held se libera', "status=$st");
$aviso = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id = $raffle AND subject LIKE 'Se liberaron%'")->fetchColumn();
check($aviso === 1, 'El vendedor recibe la lista de lo liberado (cola email)', "avisos=$aviso");
$ev = $db->query("SELECT reason FROM ticket_events WHERE raffle_id = $raffle AND to_status = 'available' ORDER BY id DESC LIMIT 1")->fetchColumn();
check(strpos((string)$ev, 'corte de apartados') !== false, 'La liberación por corte queda en la bitácora', (string)$ev);

// Tras el corte ya no se puede apartar.
$res = httpPost('/api/vendor/holds.php', ['action' => 'hold', 'raffle_id' => $raffle, 'ticket_number' => '06', 'holder_name' => '__TEST__ Amigo Fiado', 'holder_phone' => '3006660001'], $token);
check($res['code'] === 409, 'Después del corte no se aparta (409)', 'HTTP ' . $res['code']);

// ── 6. Invariante 2.4: el sorteo solo mira paid (un held jamás entra) ──
$src = file_get_contents(__DIR__ . '/../../cron/process_draws.php');
check(strpos($src, "AND t.status = 'paid'") !== false, "process_draws busca ganador SOLO entre status='paid'", '');
