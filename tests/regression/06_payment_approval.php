<?php
/** Aprobación/rechazo de pago manual por el dueño de la rifa. */

$db = testdb();
$vendorToken = fxToken(5);
$raffle = fxRaffle(['created_by' => 5, 'tickets' => 5]);
$buyer  = fxBuyer();

section('Pago — aprobar marca el boleto como pagado');
$p1 = fxPendingPayment($raffle, '01', $buyer['id']);
$res = httpPost('/api/admin/payments.php', ['action' => 'approve', 'ticket_id' => $p1['ticket_id']], $vendorToken);
assertHttp(200, $res, 'Aprobar un pago pendiente propio funciona');
$tStatus = $db->query("SELECT status FROM tickets WHERE id={$p1['ticket_id']}")->fetchColumn();
$pStatus = $db->query("SELECT transaction_status FROM payments WHERE id={$p1['payment_id']}")->fetchColumn();
check($tStatus === 'paid', 'El boleto queda en estado paid', "ticket=$tStatus");
check($pStatus === 'completed', 'El pago queda en estado completed', "payment=$pStatus");

section('Pago — rechazar libera el boleto');
$p2 = fxPendingPayment($raffle, '02', $buyer['id']);
$res = httpPost('/api/admin/payments.php', ['action' => 'reject', 'ticket_id' => $p2['ticket_id']], $vendorToken);
assertHttp(200, $res, 'Rechazar un pago pendiente propio funciona');
$tStatus = $db->query("SELECT status FROM tickets WHERE id={$p2['ticket_id']}")->fetchColumn();
$pStatus = $db->query("SELECT transaction_status FROM payments WHERE id={$p2['payment_id']}")->fetchColumn();
check($tStatus === 'available', 'El boleto vuelve a available', "ticket=$tStatus");
check($pStatus === 'failed', 'El pago queda en estado failed', "payment=$pStatus");
