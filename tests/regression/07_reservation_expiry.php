<?php
/** El cron de expiración libera boletos cuya reserva ya venció. */

section('Expiración — el cron libera boletos con reserva vencida');
$db = testdb();
$raffle = fxRaffle(['tickets' => 5]);
$buyer  = fxBuyer();
$past = date('Y-m-d H:i:s', strtotime('-30 min'));

// Boleto '01' reservado pero VENCIDO (reserved_until y expires_at en el pasado).
$db->prepare("UPDATE tickets SET status='reserved', user_id=?, reserved_at=?, reserved_until=? WHERE raffle_id=? AND ticket_number='01'")
   ->execute([$buyer['id'], $past, $past, $raffle]);
$rid = 'TEST-' . bin2hex(random_bytes(6));
$db->prepare("INSERT INTO numero_reservas (raffle_id, numero, estado, user_id, reservation_id, reserved_at, expires_at) VALUES (?, '01', 'RESERVADO', ?, ?, ?, ?)")
   ->execute([$raffle, $buyer['id'], $rid, $past, $past]);

// Estado previo
$before = $db->query("SELECT status FROM tickets WHERE raffle_id=$raffle AND ticket_number='01'")->fetchColumn();
check($before === 'reserved', 'Precondición: el boleto está reserved', "before=$before");

$cron = runCron('cron/expire-reservations.php');
check($cron['rc'] === 0, 'El cron de expiración corre sin error', 'rc=' . $cron['rc'] . ' ' . substr($cron['out'], 0, 120));

$after = $db->query("SELECT status, user_id FROM tickets WHERE raffle_id=$raffle AND ticket_number='01'")->fetch(PDO::FETCH_ASSOC);
check($after['status'] === 'available', 'El boleto vencido vuelve a available', 'after=' . $after['status']);
check($after['user_id'] === null, 'Se limpia el user_id del boleto liberado', 'user_id=' . var_export($after['user_id'], true));
$nrEstado = $db->query("SELECT estado FROM numero_reservas WHERE reservation_id=" . $db->quote($rid))->fetchColumn();
check($nrEstado !== 'RESERVADO', 'La reserva ya no está RESERVADO', 'estado=' . var_export($nrEstado, true));
