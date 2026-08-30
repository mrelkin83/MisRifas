<?php
/** Flujo de reserva de boletos: corte por fecha de sorteo, camino feliz y atomicidad. */

section('Reserva — corte por fecha de sorteo (draw_date)');
$rafflePast = fxRaffle(['draw_date' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'tickets' => 5]);
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $rafflePast, 'numeros' => ['01'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Comprador', 'phone' => '3001234567'],
]);
assertHttp(409, $res, 'Reservar en rifa cuya fecha ya pasó se rechaza');
check(isset($res['json']['message']) && stripos($res['json']['message'], 'cerr') !== false,
    'El mensaje indica que las ventas están cerradas', $res['json']['message'] ?? '');
// Limpiar el comprador que el endpoint crea antes de validar la rifa.
onTeardown(function () { testdb()->exec("DELETE FROM users WHERE phone_whatsapp='3001234567' AND name LIKE '__TEST__%'"); });

section('Reserva — camino feliz');
$raffle = fxRaffle(['draw_date' => date('Y-m-d H:i:s', strtotime('+7 days')), 'tickets' => 10]);
$buyerPhone = '3009' . random_int(100000, 999999);
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['01', '02'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Comprador', 'phone' => $buyerPhone],
]);
onTeardown(function () use ($buyerPhone) { testdb()->prepare("DELETE FROM users WHERE phone_whatsapp = ?")->execute([$buyerPhone]); });
$ok = assertHttp(200, $res, 'Reservar números disponibles funciona');
if ($ok) {
    $reserved = testdb()->query("SELECT COUNT(*) FROM tickets WHERE raffle_id = $raffle AND ticket_number IN ('01','02') AND status='reserved'")->fetchColumn();
    check((int)$reserved === 2, 'Los 2 boletos quedan en estado reserved', "reserved=$reserved");
}

section('Reserva — atomicidad ante número ya tomado (todo o nada)');
// '02' ya está reservado arriba; pedir ['02','03'] debe fallar y NO reservar '03'.
$res = httpPost('/api/payments/create-reservation.php', [
    'raffle_id' => $raffle, 'numeros' => ['02', '03'], 'payment_gateway' => 'manual',
    'user' => ['name' => '__TEST__ Comprador', 'phone' => '3009000001'],
]);
onTeardown(function () { testdb()->exec("DELETE FROM users WHERE phone_whatsapp='3009000001'"); });
check($res['code'] !== 200, 'Reservar un número ya tomado se rechaza (no 200)', "HTTP {$res['code']}");
$status03 = testdb()->query("SELECT status FROM tickets WHERE raffle_id = $raffle AND ticket_number='03'")->fetchColumn();
check($status03 === 'available', "El número '03' NO quedó reservado (rollback atómico)", "status03=$status03");
