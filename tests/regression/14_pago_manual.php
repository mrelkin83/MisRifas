<?php
/**
 * Fase 4 — Métodos de pago manual (promt2.md §5, §6).
 *
 * - Llaves de cobro: guardar/leer saneadas (jamás secretos legados).
 * - Gate de publicación: sin llaves no se publica (422).
 * - Sufijo de centavos: 1 número en talonario ≤100 → sufijo = nº de boleta;
 *   varios números → sufijo [1,999]; el monto siempre lo incluye.
 * - Venta en efectivo: exige nombre+celular, va a paid con bitácora.
 */

section('Pago manual — llaves de cobro, gate, centavos y efectivo');
$db = testdb();
$token = fxToken(5);

// Estado limpio y restauración de payment_config del vendor 5.
$prevCfg = $db->query("SELECT payment_config FROM vendors WHERE id = 5")->fetchColumn();
onTeardown(function () use ($db, $prevCfg) {
    $db->prepare("UPDATE vendors SET payment_config = ? WHERE id = 5")->execute([$prevCfg]);
});
$db->exec("UPDATE vendors SET payment_config = NULL WHERE id = 5");

// ── 1. Gate de publicación: sin llaves → 422 ──
$raffle = fxRaffle(['tickets' => 100, 'draw_date' => fxNextLotteryDate(1) . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id = 5, status = 'draft' WHERE id = ?")->execute([$raffle]);

$res = httpPost('/api/admin/raffles/update_status.php', ['raffle_id' => $raffle, 'status' => 'active'], $token);
check($res['code'] === 422 && ($res['json']['errors'] ?? '') === 'PAYMENT_KEYS_REQUIRED',
    'Publicar sin llaves de cobro se rechaza (422 PAYMENT_KEYS_REQUIRED)', 'HTTP ' . $res['code']);

// ── 2. Guardar llaves y leerlas saneadas ──
$res = httpPost('/api/admin/profile_api.php', [
    'type' => 'payment_keys',
    'nequi_phone' => '300 123 4567',
    'breb_key' => '@rifas-test',
    'accepts_cash' => true,
], $token);
$ok = assertHttp(200, $res, 'Guardar llaves de cobro funciona');
$cfg = $res['json']['data']['payment_config'] ?? [];
check(($cfg['nequi_phone'] ?? '') === '3001234567', 'El celular Nequi queda solo dígitos', $cfg['nequi_phone'] ?? '');
check(($cfg['breb_key'] ?? '') === '@rifas-test', 'La llave Bre-B se guarda tal cual', $cfg['breb_key'] ?? '');
check(!array_key_exists('nequi_key', $cfg) && !array_key_exists('nequi_secret', $cfg),
    'La respuesta jamás expone secretos legados de pasarela', json_encode(array_keys($cfg)));

// ── 3. Con llaves ya se publica ──
$res = httpPost('/api/admin/raffles/update_status.php', ['raffle_id' => $raffle, 'status' => 'active'], $token);
assertHttp(200, $res, 'Con llaves configuradas la rifa se publica');

// ── 4. Sufijo §6: un número en talonario de 100 → sufijo = nº de boleta ──
$buyerPhone = '3009' . random_int(100000, 999999);
onTeardown(function () use ($db, $buyerPhone, $raffle) {
    $db->prepare("DELETE FROM users WHERE phone_whatsapp = ? AND name LIKE '__TEST__%'")->execute([$buyerPhone]);
    $db->prepare("DELETE FROM numero_reservas WHERE raffle_id = ?")->execute([$raffle]);
    $db->prepare("DELETE FROM ticket_events WHERE raffle_id = ?")->execute([$raffle]);
});
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['37'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Centavos', 'phone' => $buyerPhone, 'email' => 'centavos@test.local'],
]);
$ok = assertHttp(200, $res, 'Reserva de un número funciona');
$amount = (float)($res['json']['data']['amount'] ?? 0);
$suffix = (int)($res['json']['data']['payment_suffix'] ?? -1);
check($suffix === 37, 'Sufijo = número de la boleta (37)', "suffix=$suffix");
check($amount === 1000.0 + 37, 'Monto exacto = precio + sufijo (1037)', "amount=$amount");
$methods = $res['json']['data']['payment_methods'] ?? [];
$names = array_column($methods, 'method');
sort($names);
check($names === ['breb', 'nequi'], 'La reserva expone SOLO los métodos configurados', json_encode($names));

// ── 5. Varios números (decisión del dueño 2026-08-31): SIN sufijo — el
// comprador paga el VALOR REAL y el vendedor identifica por la referencia ──
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['40', '41'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Centavos', 'phone' => $buyerPhone, 'email' => 'centavos@test.local'],
]);
$ok = assertHttp(200, $res, 'Reserva múltiple funciona');
$amount = (float)($res['json']['data']['amount'] ?? 0);
$suffix = (int)($res['json']['data']['payment_suffix'] ?? -1);
check($suffix === 0, 'Compra múltiple SIN sufijo (paga el valor real)', "suffix=$suffix");
check($amount === 2000.0, 'Monto múltiple = precio×2 exacto, sin centavos', "amount=$amount");

// ── 6. Venta en efectivo: sin nombre/celular se rechaza; completa va a paid ──
$res = httpPost('/api/vendor/cash_sale.php', [
    'raffle_id' => $raffle, 'ticket_number' => '55', 'buyer_name' => 'X', 'buyer_phone' => '123',
], $token);
check($res['code'] === 422, 'Efectivo sin nombre/celular válidos → 422', 'HTTP ' . $res['code']);

$cashPhone = '3008' . random_int(100000, 999999);
onTeardown(function () use ($db, $cashPhone) {
    $db->prepare("UPDATE tickets SET user_id = NULL WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE phone_whatsapp = '{$cashPhone}') x)")->execute();
    $db->prepare("DELETE FROM users WHERE phone_whatsapp = ?")->execute([$cashPhone]);
});
$res = httpPost('/api/vendor/cash_sale.php', [
    'raffle_id' => $raffle, 'ticket_number' => '55',
    'buyer_name' => '__TEST__ Efectivo', 'buyer_phone' => $cashPhone,
], $token);
$ok = assertHttp(200, $res, 'Venta en efectivo completa funciona');
$row = $db->query("SELECT status, payment_method, buyer_name, user_id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '55'")->fetch(PDO::FETCH_ASSOC);
check(($row['status'] ?? '') === 'paid' && ($row['payment_method'] ?? '') === 'cash',
    "El boleto queda paid con payment_method=cash", json_encode($row));
check(!empty($row['user_id']), 'El comprador de efectivo queda en users (puede ganar y ser notificado)', 'user_id=' . ($row['user_id'] ?? 'NULL'));
$ev = (int)$db->query("SELECT COUNT(*) FROM ticket_events WHERE raffle_id = $raffle AND to_status = 'paid' AND actor = 'vendor' AND reason = 'venta en efectivo'")->fetchColumn();
check($ev === 1, 'La venta en efectivo quedó en la bitácora con el vendedor como actor', "eventos=$ev");

// El comprador desde el enlace público NO puede registrar efectivo (sin token → 401).
$res = httpPost('/api/vendor/cash_sale.php', [
    'raffle_id' => $raffle, 'ticket_number' => '56',
    'buyer_name' => '__TEST__ Intruso', 'buyer_phone' => '3001112233',
]);
check($res['code'] === 401, 'Efectivo sin autenticación de vendedor → 401', 'HTTP ' . $res['code']);
