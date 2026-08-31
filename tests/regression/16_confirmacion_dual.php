<?php
/**
 * Fase 6 — Confirmación dual del vendedor (promt2.md §10).
 *
 * - El rechazo desde el panel exige motivo de la lista corta (422 sin él).
 * - Vía WhatsApp (PaymentInbound): un remitente que no es el vendedor NO
 *   confirma; el vendedor confirma con "SI <id>" y el reintento del mismo
 *   comando no produce segunda transición (explica el estado);
 *   "NO <id>" sin motivo pide el motivo; "NO <id> 1" rechaza.
 * - Ambas vías dejan bitácora con su source (whatsapp | dashboard).
 */

section('Confirmación dual — motivo obligatorio y comandos WhatsApp');
$db = testdb();
$vendorToken = fxToken(5);

$vendorPhone = (string)$db->query("SELECT phone FROM vendors WHERE id = 5")->fetchColumn();
if (preg_replace('/\D+/', '', $vendorPhone) === '') {
    // El vendedor de prueba necesita celular para la verificación de remitente.
    $db->exec("UPDATE vendors SET phone = '3005550005' WHERE id = 5");
    $vendorPhone = '3005550005';
    onTeardown(function () use ($db) { $db->exec("UPDATE vendors SET phone = NULL WHERE id = 5"); });
}

$raffle = fxRaffle(['tickets' => 10, 'draw_date' => fxNextLotteryDate(1) . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id = 5, status = 'active' WHERE id = ?")->execute([$raffle]);
$buyer = fxBuyer();
onTeardown(function () use ($db, $raffle) {
    $db->prepare("DELETE FROM ticket_events WHERE raffle_id = ?")->execute([$raffle]);
});

// ── 1. Panel: rechazar sin motivo → 422 ──
$p1 = fxPendingPayment($raffle, '03', $buyer['id']);
$res = httpPost('/api/admin/payments.php', ['action' => 'reject', 'ticket_id' => $p1['ticket_id']], $vendorToken);
check($res['code'] === 422, 'Rechazar sin motivo → 422', 'HTTP ' . $res['code']);

// Con motivo funciona y la bitácora lo registra.
$res = httpPost('/api/admin/payments.php', ['action' => 'reject', 'ticket_id' => $p1['ticket_id'], 'reason' => 'monto'], $vendorToken);
assertHttp(200, $res, 'Rechazar con motivo funciona');
$ev = $db->query("SELECT reason, source FROM ticket_events WHERE ticket_id = {$p1['ticket_id']} AND to_status = 'available' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check(strpos((string)$ev['reason'], 'monto no coincide') !== false && $ev['source'] === 'dashboard',
    'La bitácora guarda motivo y source=dashboard', json_encode($ev));

// ── 2. WhatsApp: helper para invocar PaymentInbound en subproceso ──
$inbound = realpath(__DIR__ . '/../../api/whatsapp/PaymentInbound.php');
$runInbound = function (string $texto, string $telefono) use ($inbound): string {
    $snippet = '<?php require ' . var_export($inbound, true) . ';' . "\n"
        . '$r = PaymentInbound::procesar(["texto" => ' . var_export($texto, true) . ', "telefono" => ' . var_export($telefono, true) . '], null, 5);' . "\n"
        . 'echo $r ? "HANDLED" : "IGNORED";' . "\n";
    $tmp = tempnam(sys_get_temp_dir(), 'pi') . '.php';
    file_put_contents($tmp, $snippet);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    @unlink($tmp);
    return "rc=$rc " . implode('', $o);
};

$p2 = fxPendingPayment($raffle, '04', $buyer['id']);
$tid = (int)$p2['ticket_id'];

// Texto que no es comando → ignorado (sigue al motor conversacional).
$out = $runInbound('Hola, ¿cómo va la rifa?', '57' . $vendorPhone);
check(strpos($out, 'IGNORED') !== false, 'Un mensaje normal NO lo atiende el interceptor de pagos', $out);

// Remitente equivocado → atendido pero SIN transición.
$out = $runInbound("SI {$tid}", '573119998877');
check(strpos($out, 'HANDLED') !== false, 'Comando de un tercero se atiende (y se niega)', $out);
$st = $db->query("SELECT status FROM tickets WHERE id = $tid")->fetchColumn();
check(in_array($st, ['reserved', 'pending_review'], true), 'Un tercero NO confirma: el boleto sigue en revisión', "status=$st");

// El VENDEDOR confirma.
$out = $runInbound("SI {$tid}", '57' . $vendorPhone);
check(strpos($out, 'HANDLED') !== false, 'El vendedor confirma por WhatsApp', $out);
$st = $db->query("SELECT status FROM tickets WHERE id = $tid")->fetchColumn();
check($st === 'paid', 'El boleto queda paid (vía WhatsApp)', "status=$st");
$ev = $db->query("SELECT source FROM ticket_events WHERE ticket_id = $tid AND to_status = 'paid' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($ev === 'whatsapp', 'La bitácora registra source=whatsapp', "source=$ev");

// Reintento del MISMO comando → sin segunda transición (idempotencia por estado).
$antes = (int)$db->query("SELECT COUNT(*) FROM ticket_events WHERE ticket_id = $tid AND to_status = 'paid'")->fetchColumn();
$runInbound("SI {$tid}", '57' . $vendorPhone);
$despues = (int)$db->query("SELECT COUNT(*) FROM ticket_events WHERE ticket_id = $tid AND to_status = 'paid'")->fetchColumn();
check($antes === 1 && $despues === 1, 'El reintento no produce una segunda transición', "antes=$antes despues=$despues");

// ── 3. WhatsApp: rechazo requiere motivo; con "NO <id> 1" libera ──
$p3 = fxPendingPayment($raffle, '05', $buyer['id']);
$tid3 = (int)$p3['ticket_id'];
$runInbound("NO {$tid3}", '57' . $vendorPhone); // pide motivo, no transiciona
$st = $db->query("SELECT status FROM tickets WHERE id = $tid3")->fetchColumn();
check(in_array($st, ['reserved', 'pending_review'], true), '"NO <id>" sin motivo NO transiciona (pide el motivo)', "status=$st");
$runInbound("NO {$tid3} 1", '57' . $vendorPhone);
$st = $db->query("SELECT status FROM tickets WHERE id = $tid3")->fetchColumn();
check($st === 'available', '"NO <id> 1" rechaza y libera el número', "status=$st");
$ev = $db->query("SELECT reason FROM ticket_events WHERE ticket_id = $tid3 AND to_status = 'available' ORDER BY id DESC LIMIT 1")->fetchColumn();
check(strpos((string)$ev, 'plata no llegó') !== false, 'El motivo del rechazo por WhatsApp queda en la bitácora', (string)$ev);
