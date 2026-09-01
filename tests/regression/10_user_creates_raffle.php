<?php
/**
 * Unificación: cualquier usuario (comprador) puede crear rifas.
 * Al iniciar sesión se auto-provisiona una identidad de organizador (vendors)
 * y con ese token puede crear una rifa vía /api/raffles/create.php.
 */

section('Cualquier usuario crea rifas — provisión de organizador + creación');
$db = testdb();

$email = 'unifytest_' . bin2hex(random_bytes(4)) . '@misrifas.test';
$phone = '39' . random_int(10000000, 99999999);
$hash = password_hash('demo1234', PASSWORD_DEFAULT);
$uuid = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
// email_verified_at: cuenta "existente" (pre-OTP); el vendor provisionado
// hereda la verificación y puede crear rifas.
$db->prepare("INSERT INTO users (unique_id,name,phone_whatsapp,email,password_hash,role,active,email_verified_at) VALUES (?, '__TEST__ Comprador', ?, ?, ?, 'buyer', 1, NOW())")
   ->execute([$uuid, $phone, $email, $hash]);

// Limpieza: usuario, vendor provisionado y cualquier rifa __TEST__ suya.
onTeardown(function () use ($db, $email) {
    $vid = $db->query("SELECT id FROM vendors WHERE email = " . $db->quote($email))->fetchColumn();
    if ($vid) {
        $db->prepare("DELETE FROM tickets WHERE raffle_id IN (SELECT id FROM raffles WHERE created_by = ?)")->execute([$vid]);
        $db->prepare("DELETE FROM raffles WHERE created_by = ?")->execute([$vid]);
        $db->prepare("DELETE FROM vendors WHERE id = ?")->execute([$vid]);
    }
    $db->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
});

// 1) Login del comprador → debe salir como organizador (vendor) con token.
$res = httpPost('/api/auth/login.php', ['email' => $email, 'password' => 'demo1234']);
$ok = assertHttp(200, $res, 'Login del comprador funciona');
$token = $res['json']['data']['token'] ?? '';
check(($res['json']['data']['user']['role'] ?? '') === 'vendor', 'Inicia sesión como organizador (role vendor)', $res['json']['data']['user']['role'] ?? '');
$vid = (int)$db->query("SELECT id FROM vendors WHERE email = " . $db->quote($email))->fetchColumn();
check($vid > 0, 'Se provisionó su identidad de organizador (fila vendors)', "vendor_id=$vid");

// 2) Con ese token crea una rifa.
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa Unify', 'description' => 'prueba', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => fxNextLotteryDate(1), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
$created = assertHttp(201, $res, 'El usuario puede crear una rifa');
if ($created) {
    $owner = $db->query("SELECT created_by FROM raffles WHERE name = '__TEST__ Rifa Unify' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check((int)$owner === $vid, 'La rifa queda a nombre de su organizador provisionado', "created_by=$owner vendor=$vid");
}

// 2b) La imagen tal como la devuelve el Uploader (SIN barra inicial) se
//     acepta y se guarda normalizada — antes el validador anti-SSRF la
//     rechazaba con "image_url invalida" y no se podía crear la rifa.
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa ImgSinBarra', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => fxNextLotteryDate(1), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2',
    'image_url' => 'assets/uploads/raffles/img_prueba.jpg',
    'image_urls' => ['assets/uploads/raffles/img_prueba2.jpg'],
], $token);
assertHttp(201, $res, 'Imagen sin barra inicial (formato del Uploader) se acepta');
$img = $db->query("SELECT image_url FROM raffles WHERE name = '__TEST__ Rifa ImgSinBarra' ORDER BY id DESC LIMIT 1")->fetchColumn();
check($img === '/assets/uploads/raffles/img_prueba.jpg', 'Se guarda normalizada con barra inicial', "db=$img");

// 2c) Edición COMPLETA: estructura editable sin ventas (regenera talonario),
//     bloqueada con ventas; ubicación y galería actualizables.
$ridImg = (int)$db->query("SELECT id FROM raffles WHERE name = '__TEST__ Rifa ImgSinBarra' ORDER BY id DESC LIMIT 1")->fetchColumn();
$res = httpPost('/api/admin/raffles/update.php', [
    'id' => $ridImg, 'digits' => 3, 'opportunities' => 4, 'winning_mode' => 'first_3',
    'ticket_price' => 2000, 'department' => 'Bogotá D.C.', 'city' => 'Bogotá',
    'image_urls' => ['assets/uploads/raffles/img_prueba2.jpg', '/assets/uploads/raffles/img_prueba3.jpg'],
], $token);
assertHttp(200, $res, 'Editar estructura sin ventas (cifras 3, 4 oportunidades)');
$f = $db->query("SELECT digits, opportunities, winning_mode, total_tickets, ticket_price, department FROM raffles WHERE id=$ridImg")->fetch(PDO::FETCH_ASSOC);
check((int)$f['digits'] === 3 && (int)$f['opportunities'] === 4 && $f['winning_mode'] === 'first_3'
    && (int)$f['total_tickets'] === 250 && (float)$f['ticket_price'] === 2000.0 && $f['department'] === 'Bogotá D.C.',
    'Estructura persistida y total recalculado (250)', json_encode($f));
$nt = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE raffle_id=$ridImg")->fetchColumn();
$op = $db->query("SELECT opportunities FROM tickets WHERE raffle_id=$ridImg LIMIT 1")->fetchColumn();
check($nt === 250 && count(json_decode((string)$op, true)) === 4, 'Talonario REGENERADO: 250 boletos de 4 números', "n=$nt");
$gal = (int)$db->query("SELECT COUNT(*) FROM raffle_images WHERE raffle_id=$ridImg")->fetchColumn();
check($gal === 2, 'Galería reemplazada (2 fotos)', "gal=$gal");

// Con un boleto vendido, la estructura queda BLOQUEADA.
$db->exec("UPDATE tickets SET status='paid' WHERE raffle_id=$ridImg LIMIT 1");
$res = httpPost('/api/admin/raffles/update.php', ['id' => $ridImg, 'digits' => 2, 'opportunities' => 1], $token);
check($res['code'] === 409 && ($res['json']['errors'] ?? '') === 'STRUCTURE_LOCKED',
    'Con ventas, cambiar estructura → 409 STRUCTURE_LOCKED', 'HTTP ' . $res['code']);
// …pero el contenido no estructural sigue editable.
$res = httpPost('/api/admin/raffles/update.php', ['id' => $ridImg, 'description' => 'texto editado con ventas'], $token);
assertHttp(200, $res, 'Descripción editable aunque haya ventas');

// Una URL externa SIGUE rechazada (la guarda anti-SSRF no se relajó).
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa ImgExterna', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => fxNextLotteryDate(1), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2',
    'image_url' => 'https://evil.example/x.jpg',
], $token);
check($res['code'] === 400, 'URL externa sigue rechazada (anti-SSRF)', 'HTTP ' . $res['code']);

// 3) El backend rechaza una fecha que NO cae el día que juega la lotería
//    (antes solo lo validaba el navegador → rifas huérfanas vía API).
$badDate = date('Y-m-d', strtotime(fxNextLotteryDate(1) . ' +1 day'));
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa DiaMalo', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => $badDate, 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
check($res['code'] === 422 && ($res['json']['errors'] ?? '') === 'DRAW_DAY_MISMATCH',
    'Fecha en día que la lotería no juega → 422 DRAW_DAY_MISMATCH', 'HTTP ' . $res['code']);
