<?php
/** Autorización: scoping de aprobación de pago (IDOR) y gates super_admin. */

$db = testdb();
$vendorToken = fxToken(5);   // rol 'vendor'

section('Aprobación de pago — un vendedor NO puede tocar boletos de otro (IDOR)');
// Rifa de OTRO dueño (created_by = 1, super_admin).
$otherRaffle = fxRaffle(['created_by' => 1, 'tickets' => 3]);
$otherTicket = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $otherRaffle LIMIT 1")->fetchColumn();
$res = httpPost('/api/admin/payments.php', ['action' => 'approve', 'ticket_id' => $otherTicket], $vendorToken);
assertHttp(403, $res, 'Aprobar un boleto de una rifa ajena se rechaza con 403');

section('Aprobación de pago — sobre rifa propia pasa el dueño (falla luego por falta de pago)');
$ownRaffle = fxRaffle(['created_by' => 5, 'tickets' => 3]);
$ownTicket = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $ownRaffle LIMIT 1")->fetchColumn();
$res = httpPost('/api/admin/payments.php', ['action' => 'approve', 'ticket_id' => $ownTicket], $vendorToken);
check($res['code'] !== 403, 'Sobre rifa propia NO da 403 (supera el chequeo de dueño)', "HTTP {$res['code']}");
check($res['code'] === 400, 'Falla por "sin pago pendiente" (400), no por permisos', "HTTP {$res['code']}: " . ($res['json']['message'] ?? ''));

section('Gate super_admin — resultado de lotería manual');
$res = httpPost('/api/admin/lottery-results/set.php', ['lottery_id' => 1, 'draw_date' => '2020-01-01', 'winning_number' => '1234']);
check($res['code'] === 401, 'Sin token → 401', "HTTP {$res['code']}");
$res = httpPost('/api/admin/lottery-results/set.php', ['lottery_id' => 1, 'draw_date' => '2020-01-01', 'winning_number' => '1234'], $vendorToken);
check($res['code'] === 403, 'Con token de vendedor → 403 (solo super_admin)', "HTTP {$res['code']}");

section('Gate super_admin — router WhatsApp IA');
$res = httpGet('/api/whatsapp/admin/index.php?ep=config-get', $vendorToken);
check($res['code'] === 403, 'WhatsApp admin con token de vendedor → 403', "HTTP {$res['code']}");
