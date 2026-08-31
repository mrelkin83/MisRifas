<?php
/**
 * Determinación de ganador end-to-end (cron/process_draws.php).
 *
 * Es el flujo de integridad más crítico: dado un resultado de lotería
 * verificado, el boleto PAGADO cuya oportunidad coincide (last_2) debe quedar
 * registrado como ganador y la rifa marcada 'completed'.
 *
 * Aislamiento: se usa una lotería DEDICADA (id 2) — el cron solo procesará
 * rifas de esa lotería con resultado de CURDATE() verificado, y ninguna otra
 * rifa (real o de otros grupos, que usan lotería 1) usa la 2 con fecha pasada.
 */

section('Determinación de ganador — end-to-end (process_draws)');
$db = testdb();
$LOT = 2; // lotería dedicada para aislar este test del resto de fixtures

// Precondición: nadie más elegible por la lotería 2 ni resultado previo hoy.
$otras = (int)$db->query("SELECT COUNT(*) FROM raffles WHERE status='active' AND draw_date<=NOW() AND lottery_id=$LOT")->fetchColumn();
$resHoy = (int)$db->query("SELECT COUNT(*) FROM lottery_results WHERE lottery_id=$LOT AND draw_date=CURDATE()")->fetchColumn();
if ($otras > 0 || $resHoy > 0) {
    check(false, 'Entorno aislado (lotería 2 sin otras rifas elegibles / resultado previo)', "otras=$otras resHoy=$resHoy — saltado");
    return;
}

// Rifa elegible: lotería 2, fecha de sorteo HOY temprano (ya pasó), last_2.
$raffle = fxRaffle(['tickets' => 10, 'draw_date' => date('Y-m-d') . ' 00:00:01', 'created_by' => 5, 'lottery_id' => $LOT]);
$db->prepare("UPDATE raffles SET vendor_id = 5 WHERE id = ?")->execute([$raffle]);

// Boleto '05' vendido (paid) a un comprador → su oportunidad es '05'.
$buyer = fxBuyer();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE raffle_id=? AND ticket_number='05'")
   ->execute([$buyer['id'], $raffle]);

// Segundo participante que NO gana (boleto '07'): debe recibir el mensaje de
// agradecimiento/resultado cuando se determine el ganador.
$participant = fxBuyer();
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW() WHERE raffle_id=? AND ticket_number='07'")
   ->execute([$participant['id'], $raffle]);

// Resultado de lotería verificado de HOY: número '7705' → last_2 = '05'.
$db->prepare("INSERT INTO lottery_results (lottery_id, draw_date, winning_number, verified) VALUES ($LOT, CURDATE(), '7705', 1)")->execute();
onTeardown(function () use ($db, $LOT) { $db->exec("DELETE FROM lottery_results WHERE lottery_id=$LOT AND draw_date=CURDATE() AND winning_number='7705'"); });
onTeardown(function () use ($db, $raffle) { $db->prepare("DELETE FROM message_queue WHERE raffle_id = ?")->execute([$raffle]); });

// Ejecutar el cron real.
$cron = runCron('cron/process_draws.php');
check($cron['rc'] === 0, 'process_draws corre sin error', 'rc=' . $cron['rc'] . ' ' . substr($cron['out'], 0, 160));

// El boleto '05' (paid, oportunidad 05) debe ser el ganador.
$winner = $db->query("SELECT rw.ticket_id, rw.user_id, rw.matched_opportunity, t.ticket_number
                      FROM raffle_winners rw JOIN tickets t ON rw.ticket_id=t.id
                      WHERE rw.raffle_id = $raffle")->fetch(PDO::FETCH_ASSOC);
check($winner !== false, 'Se registró un ganador para la rifa', $winner);
if ($winner) {
    check($winner['ticket_number'] === '05', "El ganador es el boleto correcto ('05')", 'ticket=' . $winner['ticket_number']);
    check((int)$winner['user_id'] === $buyer['id'], 'El ganador es el comprador correcto', 'user_id=' . $winner['user_id']);
    check($winner['matched_opportunity'] === '05', "matched_opportunity = '05'", $winner['matched_opportunity']);
}
$status = $db->query("SELECT status FROM raffles WHERE id=$raffle")->fetchColumn();
check($status === 'completed', "La rifa queda 'completed'", "status=$status");

// Un boleto NO vendido con la misma oportunidad no habría podido ganar:
// verificamos que el ganador vino de un boleto pagado (integridad).
$winnerStatus = $db->query("SELECT t.status FROM raffle_winners rw JOIN tickets t ON rw.ticket_id=t.id WHERE rw.raffle_id=$raffle")->fetchColumn();
check($winnerStatus === 'paid', 'El ganador provino de un boleto pagado', "status=$winnerStatus");

// ── Notificaciones encoladas (canal email por defecto + whatsapp) ──
// Ganador: felicitación por AMBOS canales.
$winEmail = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$raffle AND recipient_user_id={$buyer['id']} AND message_type='winner' AND channel='email'")->fetchColumn();
$winWa    = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$raffle AND recipient_user_id={$buyer['id']} AND message_type='winner' AND channel='whatsapp'")->fetchColumn();
check($winEmail === 1, 'Felicitación al ganador encolada por EMAIL', "email=$winEmail");
check($winWa === 1, 'Felicitación al ganador encolada por WhatsApp', "wa=$winWa");

// Participante que no ganó: agradecimiento/resultado por ambos canales.
$parEmail = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$raffle AND recipient_user_id={$participant['id']} AND message_type='no_winner' AND channel='email'")->fetchColumn();
$parWa    = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$raffle AND recipient_user_id={$participant['id']} AND message_type='no_winner' AND channel='whatsapp'")->fetchColumn();
check($parEmail === 1, 'Agradecimiento al participante encolado por EMAIL', "email=$parEmail");
check($parWa === 1, 'Agradecimiento al participante encolado por WhatsApp', "wa=$parWa");

// El email del participante lleva el resultado en HTML (para el motor SMTP).
$parHtml = $db->query("SELECT body_html FROM message_queue WHERE raffle_id=$raffle AND recipient_user_id={$participant['id']} AND channel='email'")->fetchColumn();
check(is_string($parHtml) && str_contains($parHtml, '05'), 'El email del participante incluye el número ganador en HTML', substr((string)$parHtml, 0, 60));

// El ganador NO debe recibir además el mensaje de "no ganaste".
$winNoWin = (int)$db->query("SELECT COUNT(*) FROM message_queue WHERE raffle_id=$raffle AND recipient_user_id={$buyer['id']} AND message_type='no_winner'")->fetchColumn();
check($winNoWin === 0, 'El ganador no recibe el mensaje de participante', "filas=$winNoWin");
