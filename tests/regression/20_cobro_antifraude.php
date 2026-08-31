<?php
/**
 * Fase 10 — Cobro al vendedor (§15) y antifraude (§16).
 *
 * - Avisos 7/3/2/1 idempotentes; con commission_enabled=0 no se envía nada.
 * - Mora: sales_blocked=1 (la rifa SIGUE active), comprador rechazado (423),
 *   vendedor sin poder crear rifas (403); pagar reactiva todo.
 * - El sorteo de una rifa en mora NO se bloquea (elegibilidad intacta).
 * - Antifraude: comprobante repetido señalado; reputación del comprador;
 *   archivo re-codificado en storage/ y servido solo por controlador.
 */

section('Cobro y antifraude — avisos, mora, reactivación y señales');
$db = testdb();
$token = fxToken(5);
$tokenAdmin = fxToken(1); // super_admin (mark_paid es solo super_admin)

// Settings: guardar y restaurar.
$prev = [];
foreach (['commission_enabled', 'billing_mode', 'commission_percentage'] as $k) {
    $prev[$k] = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = " . $db->quote($k))->fetchColumn();
}
onTeardown(function () use ($db, $prev) {
    foreach ($prev as $k => $v) {
        $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
    }
});

// Vendor 5 con llaves (por el gate de publicación) — restaurar luego.
$prevCfg = $db->query("SELECT payment_config FROM vendors WHERE id = 5")->fetchColumn();
onTeardown(function () use ($db, $prevCfg) {
    $db->prepare("UPDATE vendors SET payment_config = ? WHERE id = 5")->execute([$prevCfg]);
});
$db->exec("UPDATE vendors SET payment_config = '{\"mode\":\"manual\",\"nequi_phone\":\"3001234567\"}' WHERE id = 5");

// ── 1. commission_enabled=0 → el cron no encola nada ──
$db->exec("UPDATE system_settings SET setting_value='0' WHERE setting_key='commission_enabled'");
$cron = runCron('cron/check_commissions.php');
check($cron['rc'] === 0 && strpos($cron['out'], 'desactivado') !== false, 'Con cobro desactivado el cron no avisa', substr($cron['out'], 0, 80));

// ── 2. Avisos idempotentes (due en 3 días) ──
$db->exec("UPDATE system_settings SET setting_value='1' WHERE setting_key='commission_enabled'");
$raffle = fxRaffle(['tickets' => 10, 'draw_date' => fxNextLotteryDate(1, '+20 days') . ' 22:30:00', 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id=5, status='active', commission_amount=5000, commission_paid=0, commission_due_date=DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE id=?")->execute([$raffle]);

$cron = runCron('cron/check_commissions.php');
check($cron['rc'] === 0, 'El cron de cobro corre', 'rc=' . $cron['rc']);
$avisos = (int)$db->query("SELECT COUNT(DISTINCT subject) FROM message_queue WHERE raffle_id=$raffle AND message_type='payment_reminder'")->fetchColumn();
check($avisos === 1, 'Con 3 días al vencimiento se envía UN aviso', "avisos=$avisos");
$subj = (string)$db->query("SELECT subject FROM message_queue WHERE raffle_id=$raffle AND message_type='payment_reminder' LIMIT 1")->fetchColumn();
check(strpos($subj, '[cobro:t7,t3]') !== false, 'El aviso consume los umbrales 7 y 3 a la vez', $subj);

runCron('cron/check_commissions.php');
$avisos2 = (int)$db->query("SELECT COUNT(DISTINCT subject) FROM message_queue WHERE raffle_id=$raffle AND message_type='payment_reminder'")->fetchColumn();
check($avisos2 === 1, 'Re-correr el cron NO duplica el aviso (idempotente)', "avisos=$avisos2");

// ── 3. Mora: sales_blocked, comprador 423, crear rifa 403, sorteo intacto ──
$db->prepare("UPDATE raffles SET commission_due_date=DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id=?")->execute([$raffle]);
$cron = runCron('cron/check_commissions.php');
$r = $db->query("SELECT status, sales_blocked FROM raffles WHERE id=$raffle")->fetch(PDO::FETCH_ASSOC);
check($r['status'] === 'active' && (int)$r['sales_blocked'] === 1,
    'Mora: sales_blocked=1 y la rifa SIGUE active (el sorteo no se congela)', json_encode($r));
$mora = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$raffle AND subject LIKE '%mora%'")->fetchColumn();
check($mora >= 1, 'El vendedor recibe el aviso de mora', "avisos=$mora");

// Comprador rechazado con mensaje claro (423), no un error genérico.
$buyerPhone = '3009' . random_int(100000, 999999);
onTeardown(function () use ($db, $buyerPhone) {
    $db->prepare("DELETE p FROM payments p JOIN users u ON u.id = p.user_id WHERE u.phone_whatsapp = ? AND u.name LIKE '__TEST__%'")->execute([$buyerPhone]);
    $db->prepare("DELETE FROM users WHERE phone_whatsapp = ? AND name LIKE '__TEST__%'")->execute([$buyerPhone]);
});
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['02'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Mora', 'phone' => $buyerPhone, 'email' => 'mora@test.local'],
]);
check($res['code'] === 423 && ($res['json']['errors'] ?? '') === 'SALES_BLOCKED',
    'Comprar en rifa en mora → 423 SALES_BLOCKED', 'HTTP ' . $res['code']);

// El vendedor no puede crear rifas nuevas.
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa Mora', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => fxNextLotteryDate(1), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
check($res['code'] === 403 && ($res['json']['errors'] ?? '') === 'VENDOR_BILLING_OVERDUE',
    'Con saldo vencido no se crean rifas (403 VENDOR_BILLING_OVERDUE)', 'HTTP ' . $res['code']);

// El sorteo NO se bloquea: la rifa en mora sigue siendo elegible del cron de sorteos.
$src = file_get_contents(__DIR__ . '/../../cron/process_draws.php');
check(strpos($src, 'sales_blocked') === false, 'process_draws ignora sales_blocked (el sorteo jamás se bloquea por mora)', '');

// Pagar reactiva todo.
$res = httpPost('/api/admin/commissions.php', ['action' => 'mark_paid', 'raffle_id' => $raffle], $tokenAdmin);
assertHttp(200, $res, 'Marcar la comisión pagada funciona');
$r = $db->query("SELECT commission_paid, sales_blocked FROM raffles WHERE id=$raffle")->fetch(PDO::FETCH_ASSOC);
check((int)$r['commission_paid'] === 1 && (int)$r['sales_blocked'] === 0, 'El pago desbloquea las ventas', json_encode($r));
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa PostPago', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => fxNextLotteryDate(1), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
$ok = assertHttp(201, $res, 'Pagado el saldo, vuelve a crear rifas');
if ($ok) {
    $rid = (int)($res['json']['data']['raffle_id'] ?? $res['json']['data']['id'] ?? 0);
    onTeardown(function () use ($db, $rid) {
        if ($rid) {
            $db->prepare("DELETE FROM tickets WHERE raffle_id = ?")->execute([$rid]);
            $db->prepare("DELETE FROM raffles WHERE id = ?")->execute([$rid]);
        }
    });
}

// ── 4. Antifraude §16: comprobante repetido + storage + controlador ──
$db->prepare("UPDATE raffles SET sales_blocked=0 WHERE id=?")->execute([$raffle]);
// Imagen real mínima (GD) para el comprobante:
$im = imagecreatetruecolor(60, 60);
imagestring($im, 5, 5, 20, 'PAGO', imagecolorallocate($im, 255, 255, 255));
ob_start();
imagejpeg($im);
$jpg = ob_get_clean();
imagedestroy($im);
$dataUri = 'data:image/jpeg;base64,' . base64_encode($jpg);

// Reservar 2 boletos con compradores distintos y subir EL MISMO comprobante.
$fase10Buyers = [];
foreach ([['03', '3009111001', 'af1@test.local'], ['04', '3009111002', 'af2@test.local']] as [$num, $ph, $em]) {
    $fase10Buyers[] = $ph;
    $res = httpPost('/api/payments/create-reservation.php', [
        'raffle_id' => $raffle, 'numeros' => [$num], 'payment_gateway' => 'manual',
        'user' => ['name' => '__TEST__ Fraude', 'phone' => $ph, 'email' => $em],
    ]);
    assertHttp(200, $res, "Reserva del boleto $num funciona");
    $resv = $res['json']['data']['reservation_id'];
    $res = httpPost('/api/tickets/confirm-payment.php', [
        'reservation_id' => $resv, 'payment_method' => 'nequi', 'proof' => $dataUri,
    ]);
    assertHttp(200, $res, "Comprobante del boleto $num subido");
}
onTeardown(function () use ($db, &$fase10Buyers) {
    foreach ($fase10Buyers as $ph) {
        $db->prepare("DELETE p FROM payments p JOIN users u ON u.id = p.user_id WHERE u.phone_whatsapp = ? AND u.name LIKE '__TEST__%'")->execute([$ph]);
        $db->prepare("DELETE FROM users WHERE phone_whatsapp = ? AND name LIKE '__TEST__%'")->execute([$ph]);
    }
});

$gws = $db->query("SELECT payment_gateway_response FROM payments WHERE raffle_id=$raffle ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$g1 = json_decode((string)$gws[0], true);
$g2 = json_decode((string)$gws[count($gws) - 1], true);
check(!empty($g1['proof_sha256']) && !empty($g1['proof_file']) && !empty($g1['proof_token']),
    'El comprobante guarda hash, archivo en storage y token', json_encode(array_keys($g1)));
check(!in_array('comprobante_repetido', $g1['flags'] ?? [], true), 'El primer comprobante NO está señalado', json_encode($g1['flags'] ?? []));
check(in_array('comprobante_repetido', $g2['flags'] ?? [], true), 'El comprobante REPETIDO queda señalado (§16.1)', json_encode($g2['flags'] ?? []));

// El archivo vive en storage/ (no público) y el controlador lo sirve por token.
$file = __DIR__ . '/../../storage/comprobantes/' . $g1['proof_file'];
check(is_file($file), 'El archivo re-codificado existe en storage/comprobantes', $g1['proof_file']);
onTeardown(function () use ($g1, $g2) {
    foreach ([$g1, $g2] as $g) {
        @unlink(__DIR__ . '/../../storage/comprobantes/' . ($g['proof_file'] ?? 'x'));
    }
});
$res = httpGet('/storage/comprobantes/' . $g1['proof_file']);
check(in_array($res['code'], [403, 404], true), 'storage/comprobantes NO es servible directo', 'HTTP ' . $res['code']);
$res = httpGet('/api/vendor/proof.php?t=' . $g1['proof_token']);
check($res['code'] === 200 && strpos((string)$res['raw'], "\xFF\xD8") === 0, 'El controlador sirve el JPEG con el token', 'HTTP ' . $res['code']);
$res = httpGet('/api/vendor/proof.php?t=' . str_repeat('0', 48));
check($res['code'] === 404, 'Token inexistente → 404', 'HTTP ' . $res['code']);

// ── 5. §16.3: comprador con 2+ rechazos queda señalado ──
$phoneRep = '3009111003';
$fase10Buyers[] = $phoneRep;
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['06'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Reincidente', 'phone' => $phoneRep, 'email' => 'rep@test.local'],
]);
assertHttp(200, $res, 'Reserva del reincidente funciona');
$uidRep = (int)$db->query("SELECT id FROM users WHERE phone_whatsapp='$phoneRep'")->fetchColumn();
// Simular 2 rechazos recientes.
$db->prepare("INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status, created_at) SELECT ?, ?, id, 1000, 'nequi', CONCAT('TESTREJ', id), 'failed', NOW() FROM tickets WHERE raffle_id=? LIMIT 2")
   ->execute([$uidRep, $raffle, $raffle]);
$res = httpPost('/api/tickets/confirm-payment.php', [
    'reservation_id' => $res['json']['data']['reservation_id'] ?? '', 'payment_method' => 'nequi',
]);
assertHttp(200, $res, 'El reincidente reporta pago');
$g3 = json_decode((string)$db->query("SELECT payment_gateway_response FROM payments WHERE raffle_id=$raffle AND user_id=$uidRep AND transaction_status='pending' ORDER BY id DESC LIMIT 1")->fetchColumn(), true);
check(in_array('comprador_con_rechazos', $g3['flags'] ?? [], true), 'Celular con 2+ rechazos en 30 días queda señalado (§16.3)', json_encode($g3['flags'] ?? []));
