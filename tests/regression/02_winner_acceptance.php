<?php
/** Aceptación del premio por el ganador (enlace tokenizado, público). */

section('Aceptación de premio — detalle + aceptar + idempotencia + token inválido');

$db = testdb();
$raffle = fxRaffle(['tickets' => 5]);
$buyer  = fxBuyer();
$ticketId = (int)$db->query("SELECT id FROM tickets WHERE raffle_id = $raffle LIMIT 1")->fetchColumn();
$token = bin2hex(random_bytes(24));

$db->prepare("INSERT INTO raffle_winners
    (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description, acceptance_status, acceptance_token)
    VALUES (?,?,?, '1234','34','__TEST__ Premio','pending', ?)")
   ->execute([$raffle, $ticketId, $buyer['id'], $token]);
// La limpieza de raffle_winners ya la cubre el teardown de la rifa.

// 1) GET detalle
$res = httpGet('/api/winners/accept.php?t=' . $token);
$ok = assertHttp(200, $res, 'GET con token válido devuelve el premio');
check(($res['json']['winner']['status'] ?? '') === 'pending', 'Estado inicial = pending', $res['json']['winner']['status'] ?? '');

// 2) POST aceptar
$res = httpPost('/api/winners/accept.php', ['token' => $token, 'action' => 'accept']);
assertHttp(200, $res, 'POST accept funciona');
check(($res['json']['winner']['status'] ?? '') === 'accepted', 'Estado pasa a accepted', $res['json']['winner']['status'] ?? '');
$dbStatus = $db->query("SELECT acceptance_status FROM raffle_winners WHERE acceptance_token = " . $db->quote($token))->fetchColumn();
check($dbStatus === 'accepted', 'Persistido en BD como accepted', "db=$dbStatus");

// 3) Idempotencia: un segundo intento (decline) NO cambia el estado ya decidido
$res = httpPost('/api/winners/accept.php', ['token' => $token, 'action' => 'decline']);
check($res['code'] === 200 && ($res['json']['already'] ?? false) === true, 'Segunda decisión es idempotente (already=true)', $res['json']);
$dbStatus = $db->query("SELECT acceptance_status FROM raffle_winners WHERE acceptance_token = " . $db->quote($token))->fetchColumn();
check($dbStatus === 'accepted', 'El estado sigue siendo accepted (no se pudo revertir)', "db=$dbStatus");

// 4) Token inválido
$res = httpGet('/api/winners/accept.php?t=deadbeef');
check($res['code'] === 400 || $res['code'] === 404, 'Token inválido/inexistente se rechaza (400/404)', "HTTP {$res['code']}");
