<?php
/**
 * Fase 5 — Boleta digital (promt2.md §9).
 *
 * - Todo paid emite ticket_code Crockford no predecible, en la MISMA
 *   transacción (lo hace la máquina de estados).
 * - boleta.php: VÁLIDA con datos enmascarados; código inexistente → NO
 *   ENCONTRADA; código de un paid revertido por admin → ANULADA.
 * - El PNG se sirve por el controlador y storage/ está bloqueado por HTTP.
 */

section('Boleta digital — emisión, página pública, anulación y PNG');
$db = testdb();
$token = fxToken(5);

// Vendor 5 con llaves de cobro (requisito para publicar/vender).
$prevCfg = $db->query("SELECT payment_config FROM vendors WHERE id = 5")->fetchColumn();
onTeardown(function () use ($db, $prevCfg) {
    $db->prepare("UPDATE vendors SET payment_config = ? WHERE id = 5")->execute([$prevCfg]);
});
$db->exec("UPDATE vendors SET payment_config = '{\"mode\":\"manual\",\"nequi_phone\":\"3001234567\"}' WHERE id = 5");

$raffle = fxRaffle(['tickets' => 100, 'draw_date' => fxNextLotteryDate(1) . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id = 5, status = 'active' WHERE id = ?")->execute([$raffle]);
onTeardown(function () use ($db, $raffle) {
    $db->prepare("DELETE FROM ticket_events WHERE raffle_id = ?")->execute([$raffle]);
});

// ── 1. Venta en efectivo → boleta emitida automáticamente ──
$cashPhone = '3007' . random_int(100000, 999999);
onTeardown(function () use ($db, $cashPhone) {
    $db->prepare("UPDATE tickets SET user_id = NULL WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE phone_whatsapp = '{$cashPhone}') x)")->execute();
    $db->prepare("DELETE FROM users WHERE phone_whatsapp = ?")->execute([$cashPhone]);
});
$res = httpPost('/api/vendor/cash_sale.php', [
    'raffle_id' => $raffle, 'ticket_number' => '42',
    'buyer_name' => '__TEST__ Juan Pablo Perez', 'buyer_phone' => $cashPhone,
], $token);
$ok = assertHttp(200, $res, 'Venta en efectivo funciona');

$t = $db->query("SELECT ticket_code, issued_at, buyer_name FROM tickets WHERE raffle_id = $raffle AND ticket_number = '42'")->fetch(PDO::FETCH_ASSOC);
$code = (string)($t['ticket_code'] ?? '');
check(strlen($code) === 12 && preg_match('/^[0-9A-HJKMNP-TV-Z]{12}$/', $code) === 1,
    'ticket_code emitido: 12 caracteres Crockford (sin I,L,O,U)', $code ?: 'NULL');
check(!empty($t['issued_at']), 'issued_at quedó marcado en la emisión', $t['issued_at'] ?? 'NULL');

$fmt = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);

// ── 2. Página pública: VÁLIDA y enmascarada ──
$res = httpGet('/public/boleta.php?c=' . $fmt);
check($res['code'] === 200 && strpos($res['raw'], 'BOLETA VÁLIDA') !== false, 'boleta.php muestra BOLETA VÁLIDA', 'HTTP ' . $res['code']);
check(strpos($res['raw'], '__TEST__ J.') !== false, 'El nombre va enmascarado (primer nombre + inicial)', '');
check(strpos($res['raw'], 'Juan Pablo Perez') === false, 'El nombre completo JAMÁS aparece en la página', '');
check(strpos($res['raw'], $cashPhone) === false, 'El celular completo JAMÁS aparece en la página', '');
check(strpos($res['raw'], substr($cashPhone, 0, 3) . '****' . substr($cashPhone, -3)) !== false, 'El celular aparece enmascarado (300****567)', '');

// ── 3. Código inexistente → NO ENCONTRADA (mismo formato válido) ──
$res = httpGet('/public/boleta.php?c=AAAA-BBBB-CCCC');
check(strpos($res['raw'], 'NO ENCONTRADA') !== false, 'Código inexistente → BOLETA NO ENCONTRADA', '');

// ── 4. PNG servido por el controlador; storage/ bloqueado por HTTP ──
$res = httpGet('/api/tickets/boleta_png.php?c=' . $fmt);
check($res['code'] === 200 && strpos((string)$res['raw'], "\x89PNG") === 0, 'El PNG de la boleta se genera y sirve', 'HTTP ' . $res['code']);
$res = httpGet('/storage/boletas/' . $code . '.png');
check(in_array($res['code'], [403, 404], true), 'storage/ NO es servible directo por HTTP', 'HTTP ' . $res['code']);
onTeardown(function () use ($code) {
    @unlink(__DIR__ . '/../../storage/boletas/' . $code . '.png');
});

// ── 5. Reversa administrativa → código invalidado → ANULADA ──
$tid = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '42'")->fetchColumn();
$smPath = realpath(__DIR__ . '/../../api/services/TicketStateMachine.php');
$dbPath = realpath(__DIR__ . '/../../config/database.php');
$snippet = '<?php require ' . var_export($dbPath, true) . '; require ' . var_export($smPath, true) . ';'
    . '$pdo = Database::getInstance()->getConnection();'
    . 'TicketStateMachine::transition($pdo, ' . $tid . ', "available", ["actor" => "admin", "source" => "admin", "actor_id" => 1, "reason" => "anulación (test)"]); echo "OK";';
$tmp = tempnam(sys_get_temp_dir(), 'bl') . '.php';
file_put_contents($tmp, $snippet);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
@unlink($tmp);
check($rc === 0 && strpos(implode('', $o), 'OK') !== false, 'La reversa administrativa corre', implode('', $o));

$nuevo = $db->query("SELECT ticket_code FROM tickets WHERE id = $tid")->fetchColumn();
check($nuevo === null, 'El código quedó invalidado en el ticket (no se reutiliza)', var_export($nuevo, true));
$res = httpGet('/public/boleta.php?c=' . $fmt);
check(strpos($res['raw'], 'BOLETA ANULADA') !== false, 'boleta.php muestra ANULADA para el código invalidado', '');
