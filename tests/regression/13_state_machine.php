<?php
/**
 * Máquina de estados de tickets (promt2.md §7, §11, §14).
 *
 * - CONCURRENCIA (obligatoria): dos peticiones EN PARALELO por el mismo
 *   número — una gana (200) y la otra recibe TICKET_NOT_AVAILABLE (409).
 * - Toda transición queda en ticket_events (bitácora).
 * - Transiciones ilegales lanzan InvalidTransition; paid→available exige admin.
 */

section('Máquina de estados — concurrencia, bitácora y guardas');
$db = testdb();

$raffle = fxRaffle(['tickets' => 10, 'draw_date' => fxNextLotteryDate(1) . ' 22:30:00']);
$buyer = fxBuyer();
onTeardown(function () use ($db, $raffle) {
    $db->prepare("DELETE FROM ticket_events WHERE raffle_id = ?")->execute([$raffle]);
    $db->prepare("DELETE FROM numero_reservas WHERE raffle_id = ?")->execute([$raffle]);
});

// ── 1. Dos compradores en paralelo por el número '05' ──
$payload = function (string $phone, string $email) use ($raffle) {
    return json_encode([
        'raffle_id' => $raffle, 'numeros' => ['05'], 'payment_gateway' => 'manual',
        'user' => ['name' => '__TEST__ Concurrente', 'phone' => $phone, 'email' => $email],
    ]);
};
$phones = ['3007770001', '3007770002'];
onTeardown(function () use ($db, $phones) {
    foreach ($phones as $ph) {
        $db->prepare("DELETE FROM users WHERE phone_whatsapp = ? AND name LIKE '__TEST__%'")->execute([$ph]);
    }
});

$mh = curl_multi_init();
$handles = [];
foreach ($phones as $i => $ph) {
    $ch = curl_init(TEST_BASE_URL . '/api/payments/create-reservation.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload($ph, "conc{$i}@test.local"),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}
do {
    $status = curl_multi_exec($mh, $running);
    if ($running) {
        curl_multi_select($mh, 0.05);
    }
} while ($running && $status === CURLM_OK);

$codes = [];
foreach ($handles as $ch) {
    $codes[] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);
sort($codes);

check($codes === [200, 409], 'En paralelo: una reserva gana (200) y la otra 409', implode(',', $codes));
$reservados = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE raffle_id = $raffle AND ticket_number = '05' AND status = 'reserved'")->fetchColumn();
check($reservados === 1, "El número '05' quedó reservado exactamente una vez", "reservados=$reservados");

// ── 2. Bitácora: la reserva dejó su evento ──
$ev = (int)$db->query("SELECT COUNT(*) FROM ticket_events WHERE raffle_id = $raffle AND from_status = 'available' AND to_status = 'reserved'")->fetchColumn();
check($ev === 1, 'ticket_events registró available→reserved (1 evento)', "eventos=$ev");

// ── 3. Guardas de la máquina (directo, vía PHP) ──
$sm = realpath(__DIR__ . '/../../api/services/TicketStateMachine.php');
$dbphp = realpath(__DIR__ . '/../../config/database.php');
$tid = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle AND ticket_number = '07'")->fetchColumn();
$snippet = '<?php require ' . var_export($dbphp = $dbphp ?? '', true) . '; require ' . var_export($sm, true) . ';' . "\n"
    . '$pdo = Database::getInstance()->getConnection();' . "\n"
    // paid directo por el vendedor (venta en efectivo es legal: available→paid)
    . 'TicketStateMachine::transition($pdo, ' . $tid . ', "paid", ["actor" => "vendor", "source" => "dashboard", "reason" => "efectivo (test)", "fields" => ["paid_at" => date("Y-m-d H:i:s")]]);' . "\n"
    // revertir un pagado SIN ser admin debe fallar
    . 'try { TicketStateMachine::transition($pdo, ' . $tid . ', "available", ["actor" => "vendor", "source" => "dashboard", "reason" => "x"]); echo "FALLO-GUARDA|"; } catch (InvalidTransition $e) { echo "GUARDA-OK|"; }' . "\n"
    // transición inexistente (paid→held) debe fallar
    . 'try { TicketStateMachine::transition($pdo, ' . $tid . ', "held", ["actor" => "admin", "source" => "admin", "reason" => "x"]); echo "FALLO-TABLA"; } catch (InvalidTransition $e) { echo "TABLA-OK"; }' . "\n";
$tmp = tempnam(sys_get_temp_dir(), 'sm') . '.php';
file_put_contents($tmp, $snippet);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $outArr, $rc);
@unlink($tmp);
$out = implode('', $outArr);
check($rc === 0 && strpos($out, 'GUARDA-OK|TABLA-OK') !== false, 'paid solo lo revierte admin; transiciones fuera de tabla fallan', "rc=$rc out=" . substr($out, 0, 120));

$estado = $db->query("SELECT status FROM tickets WHERE id = $tid")->fetchColumn();
check($estado === 'paid', "El ticket sigue 'paid' tras los intentos ilegales", "status=$estado");

// paid→available SÍ funciona como admin, invalida y limpia
$snippet = '<?php require ' . var_export($dbphp, true) . '; require ' . var_export($sm, true) . ';' . "\n"
    . '$pdo = Database::getInstance()->getConnection();' . "\n"
    . 'TicketStateMachine::transition($pdo, ' . $tid . ', "available", ["actor" => "admin", "source" => "admin", "actor_id" => 1, "reason" => "reversa administrativa (test)"]); echo "ADMIN-OK";' . "\n";
$tmp = tempnam(sys_get_temp_dir(), 'sm') . '.php';
file_put_contents($tmp, $snippet);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1', $outArr2, $rc2);
@unlink($tmp);
check($rc2 === 0 && strpos(implode('', $outArr2), 'ADMIN-OK') !== false, 'paid→available funciona con actor admin', implode('', $outArr2));
$row = $db->query("SELECT status, paid_at FROM tickets WHERE id = $tid")->fetch(PDO::FETCH_ASSOC);
check($row['status'] === 'available' && $row['paid_at'] === null, 'La reversa limpia paid_at y libera', json_encode($row));
