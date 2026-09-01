<?php
/**
 * Cobro de plataforma con Wompi (reactivación automática) + gestor de loterías.
 *
 * - El dueño de la rifa obtiene un link de Web Checkout con firma de
 *   integridad correcta; otro vendedor no (403).
 * - El webhook: evento APPROVED bien firmado → marca pagada y desbloquea
 *   (idéntico al "Marcar pagada" manual, que sigue de contingencia).
 * - Firma inválida → 403 sin tocar nada. Monto que no cuadra → 200 sin
 *   activar. Reenvío del mismo evento → idempotente.
 * - Loterías: eliminar una sin rifas funciona; una con rifas → 409.
 */

section('Cobro Wompi — link firmado, webhook y reactivación automática');
$db = testdb();
$tokenAdmin = fxToken(1);   // super_admin, dueño de la rifa del test
$tokenVendor = fxToken(5);  // vendedor normal ajeno

// Llaves Wompi de prueba (se restauran al final).
$KEYS = [
    'wompi_platform_public_key' => 'pub_test_abc123',
    'wompi_platform_integrity_secret' => 'test_integrity_secret',
    'wompi_platform_events_secret' => 'test_events_secret',
];
$prevKeys = [];
foreach ($KEYS as $k => $v) {
    $prevKeys[$k] = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key = " . $db->quote($k))->fetchColumn();
    $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
}
onTeardown(function () use ($db, $prevKeys) {
    foreach ($prevKeys as $k => $v) {
        $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?")->execute([$v, $k]);
    }
    $db->exec("DELETE FROM message_queue WHERE subject LIKE '%__TEST_WOMPI__%' OR raffle_id IN (SELECT id FROM raffles WHERE name = '__TEST_WOMPI__ Rifa')");
    $db->exec("DELETE FROM tickets WHERE raffle_id IN (SELECT id FROM raffles WHERE name = '__TEST_WOMPI__ Rifa')");
    $db->exec("DELETE FROM raffles WHERE name = '__TEST_WOMPI__ Rifa'");
});

// Rifa del super_admin con cobro pendiente y ventas bloqueadas (en mora).
$db->exec("INSERT INTO raffles (name, description, image_url, city, department, whatsapp_contact, responsible_person,
            ticket_price, total_tickets, digits, draw_date, lottery_id, opportunities, winning_mode,
            created_by, vendor_id, status, created_at)
           VALUES ('__TEST_WOMPI__ Rifa', 'x', '/assets/images/placeholder.svg', 'Bogotá', 'Bogotá D.C.', '3000000000', 'Test',
            1000, 10, 2, DATE_ADD(NOW(), INTERVAL 20 DAY), 1, 1, 'last_2', 1, 1, 'active', NOW())");
$rid = (int)$db->lastInsertId();
$db->prepare("UPDATE raffles SET commission_amount = 5000, commission_paid = 0, sales_blocked = 1,
              commission_due_date = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?")->execute([$rid]);

// 1) Otro vendedor NO obtiene link del cobro ajeno.
$res = httpPost('/api/vendor/pagar-cobro.php', ['raffle_id' => $rid], $tokenVendor);
check($res['code'] === 403, 'Vendedor ajeno → 403', 'HTTP ' . $res['code']);

// 2) El dueño obtiene el link con firma de integridad correcta.
$res = httpPost('/api/vendor/pagar-cobro.php', ['raffle_id' => $rid], $tokenAdmin);
assertHttp(200, $res, 'El dueño obtiene el link de pago');
$url = (string)($res['json']['data']['url'] ?? '');
check(strpos($url, 'https://checkout.wompi.co/p/?') === 0, 'Es un Web Checkout de Wompi', substr($url, 0, 60));
parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
$ref = (string)($q['reference'] ?? '');
check(preg_match('/^MRCOBRO-' . $rid . '-\d+$/', $ref) === 1, 'Referencia parseable MRCOBRO-<rifa>-<ts>', $ref);
check((int)($q['amount-in-cents'] ?? 0) === 500000, 'Monto exacto en centavos (5000 COP)', (string)($q['amount-in-cents'] ?? ''));
$firmaEsperada = hash('sha256', $ref . '500000' . 'COP' . $KEYS['wompi_platform_integrity_secret']);
check(($q['signature:integrity'] ?? '') === $firmaEsperada, 'Firma de integridad correcta', '');

// Helper: evento de Wompi firmado con el events_secret.
$evento = function (string $reference, int $cents, string $status, string $secret) {
    $ts = time();
    $data = ['transaction' => ['id' => 'tx-' . $ts, 'status' => $status, 'amount_in_cents' => $cents, 'reference' => $reference]];
    $props = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
    $cadena = $data['transaction']['id'] . $status . $cents;
    return [
        'event' => 'transaction.updated', 'data' => $data, 'timestamp' => $ts,
        'signature' => ['properties' => $props, 'checksum' => hash('sha256', $cadena . $ts . $secret)],
    ];
};

// 3) Firma inválida → 403 y nada cambia.
$res = httpPost('/api/payments/wompi-billing-webhook.php', $evento($ref, 500000, 'APPROVED', 'secreto-equivocado'), null);
check($res['code'] === 403, 'Webhook con firma inválida → 403', 'HTTP ' . $res['code']);
$fila = $db->query("SELECT commission_paid, sales_blocked FROM raffles WHERE id = $rid")->fetch(PDO::FETCH_ASSOC);
check((int)$fila['commission_paid'] === 0 && (int)$fila['sales_blocked'] === 1, 'Sigue impaga y bloqueada', json_encode($fila));

// 4) Monto que no cuadra → 200 (Wompi no reintenta) pero SIN activar.
$res = httpPost('/api/payments/wompi-billing-webhook.php', $evento($ref, 100, 'APPROVED', $KEYS['wompi_platform_events_secret']), null);
check($res['code'] === 200, 'Monto errado → 200 (sin reintentos de Wompi)', 'HTTP ' . $res['code']);
$fila = $db->query("SELECT commission_paid FROM raffles WHERE id = $rid")->fetch(PDO::FETCH_ASSOC);
check((int)$fila['commission_paid'] === 0, 'Monto errado JAMÁS activa', json_encode($fila));

// 5) Evento APPROVED legítimo → pagada + desbloqueada AUTOMÁTICAMENTE.
$res = httpPost('/api/payments/wompi-billing-webhook.php', $evento($ref, 500000, 'APPROVED', $KEYS['wompi_platform_events_secret']), null);
check($res['code'] === 200, 'Webhook legítimo → 200', 'HTTP ' . $res['code']);
$fila = $db->query("SELECT commission_paid, sales_blocked FROM raffles WHERE id = $rid")->fetch(PDO::FETCH_ASSOC);
check((int)$fila['commission_paid'] === 1 && (int)$fila['sales_blocked'] === 0,
    'Pagada y REACTIVADA automáticamente', json_encode($fila));

// 6) Reenvío del mismo evento → idempotente (sigue pagada, 200).
$res = httpPost('/api/payments/wompi-billing-webhook.php', $evento($ref, 500000, 'APPROVED', $KEYS['wompi_platform_events_secret']), null);
check($res['code'] === 200, 'Reenvío → 200 idempotente', 'HTTP ' . $res['code']);

// ── Gestor de loterías: eliminar ──
section('Loterías — eliminar con guarda de historial');
$res = httpPost('/api/admin/lotteries.php', ['action' => 'crear', 'name' => 'Lotería Borrable [TEST]',
    'day_of_week' => 'sunday', 'draw_time' => '19:00'], $tokenAdmin);
assertHttp(200, $res, 'Crear lotería temporal');
$lid = (int)($res['json']['data']['id'] ?? 0);
onTeardown(function () use ($db, $lid) { $db->exec("DELETE FROM lotteries WHERE id = " . (int)$lid); });

$res = httpPost('/api/admin/lotteries.php', ['action' => 'eliminar', 'id' => $lid], $tokenAdmin);
assertHttp(200, $res, 'Eliminar lotería sin rifas');
$existe = $db->query("SELECT COUNT(*) FROM lotteries WHERE id = $lid")->fetchColumn();
check((int)$existe === 0, 'La lotería quedó eliminada de la BD', "existe=$existe");

$res = httpPost('/api/admin/lotteries.php', ['action' => 'eliminar', 'id' => 1], $tokenAdmin);
check($res['code'] === 409, 'Lotería con rifas → 409 (el historial la referencia)', 'HTTP ' . $res['code']);
$sigue = $db->query("SELECT COUNT(*) FROM lotteries WHERE id = 1")->fetchColumn();
check((int)$sigue === 1, 'La lotería con historial sigue intacta', '');
