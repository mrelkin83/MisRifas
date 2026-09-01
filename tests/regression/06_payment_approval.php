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

// Al aprobar, el COMPRADOR recibe correo con el enlace de su boleta digital
// ("el correo va siempre"): antes solo salía la boleta por WhatsApp y quien
// no lo tenía no recibía nada.
$mq = $db->prepare("SELECT subject, body_text, body_html FROM message_queue
                    WHERE message_type = 'payment_confirmed' AND channel = 'email' AND raffle_id = ?
                    ORDER BY id DESC LIMIT 1");
$mq->execute([$raffle]);
$correo = $mq->fetch(PDO::FETCH_ASSOC);
onTeardown(function () use ($db, $raffle) {
    $db->prepare("DELETE FROM message_queue WHERE raffle_id = ? AND message_type = 'payment_confirmed'")->execute([$raffle]);
});
check((bool)$correo, 'Se encola el correo de pago confirmado al comprador', '');
check($correo && strpos($correo['body_text'], '/public/boleta.php?c=') !== false,
    'El correo trae el enlace de la boleta digital', substr($correo['body_text'] ?? '', 0, 100));

section('Pago — rechazar libera el boleto');
$p2 = fxPendingPayment($raffle, '02', $buyer['id']);
// §10.2: el rechazo lleva motivo obligatorio de la lista corta.
$res = httpPost('/api/admin/payments.php', ['action' => 'reject', 'ticket_id' => $p2['ticket_id'], 'reason' => 'no_llego'], $vendorToken);
assertHttp(200, $res, 'Rechazar un pago pendiente propio funciona');
$tStatus = $db->query("SELECT status FROM tickets WHERE id={$p2['ticket_id']}")->fetchColumn();
$pStatus = $db->query("SELECT transaction_status FROM payments WHERE id={$p2['payment_id']}")->fetchColumn();
check($tStatus === 'available', 'El boleto vuelve a available', "ticket=$tStatus");
check($pStatus === 'failed', 'El pago queda en estado failed', "payment=$pStatus");
