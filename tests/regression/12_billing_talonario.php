<?php
/**
 * Modalidad de cobro por talonario (v4.0).
 *
 * Con billing_mode='talonario', crear una rifa fija commission_amount a la
 * tarifa plana (talonario_fee), sin importar precio ni total de boletos.
 * Con billing_mode='commission' se mantiene el porcentaje histórico.
 */

section('Cobro por talonario — tarifa plana vs comisión porcentual');
$db = testdb();

// Guardar settings actuales y restaurarlos SIEMPRE.
$prev = [];
foreach (['commission_enabled', 'billing_mode', 'talonario_fee', 'commission_percentage'] as $k) {
    $prev[$k] = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = " . $db->quote($k))->fetchColumn();
}
onTeardown(function () use ($db, $prev) {
    foreach ($prev as $k => $v) {
        $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
    }
});

$set = function (array $kv) use ($db) {
    foreach ($kv as $k => $v) {
        $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
    }
};

$token = fxToken(5);
$mkRaffle = function () use ($token) {
    return httpPost('/api/raffles/create.php', [
        'name' => '__TEST__ Rifa Billing', 'description' => 'x', 'department' => 'Cundinamarca',
        'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
        'responsible_person' => 'Test', 'ticket_price' => 10000, 'total_tickets' => 100,
        'draw_date' => fxNextLotteryDate(1), 'lottery_id' => 1, 'digits' => 2,
        'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
    ], $token);
};
$cleanup = function (int $rid) use ($db) {
    $db->prepare("DELETE FROM tickets WHERE raffle_id = ?")->execute([$rid]);
    $db->prepare("DELETE FROM raffles WHERE id = ?")->execute([$rid]);
};

// 1) Modo talonario: tarifa plana.
$set(['commission_enabled' => '1', 'billing_mode' => 'talonario', 'talonario_fee' => '7500']);
$res = $mkRaffle();
$ok = assertHttp(201, $res, 'Crea rifa en modo talonario');
if ($ok) {
    $rid = (int)($res['json']['data']['raffle_id'] ?? $res['json']['data']['id'] ?? 0);
    $amount = $db->query("SELECT commission_amount FROM raffles WHERE id = $rid")->fetchColumn();
    check((float)$amount === 7500.0, 'commission_amount = tarifa plana (7500), no % de $1.000.000', "amount=$amount");
    $cleanup($rid);
}

// 2) Modo comisión: porcentaje sobre el total (10000 × 100 × 5% = 50000).
$set(['billing_mode' => 'commission', 'commission_percentage' => '5']);
$res = $mkRaffle();
$ok = assertHttp(201, $res, 'Crea rifa en modo comisión');
if ($ok) {
    $rid = (int)($res['json']['data']['raffle_id'] ?? $res['json']['data']['id'] ?? 0);
    $amount = $db->query("SELECT commission_amount FROM raffles WHERE id = $rid")->fetchColumn();
    check((float)$amount === 50000.0, 'commission_amount = 5% del valor total (50000)', "amount=$amount");
    $cleanup($rid);
}

// 3) Cobro desactivado: sin comisión.
$set(['commission_enabled' => '0']);
$res = $mkRaffle();
$ok = assertHttp(201, $res, 'Crea rifa con cobro desactivado');
if ($ok) {
    $rid = (int)($res['json']['data']['raffle_id'] ?? $res['json']['data']['id'] ?? 0);
    $amount = $db->query("SELECT COALESCE(commission_amount, 0) FROM raffles WHERE id = $rid")->fetchColumn();
    check((float)$amount === 0.0, 'Sin cobro: commission_amount = 0', "amount=$amount");
    $cleanup($rid);
}
