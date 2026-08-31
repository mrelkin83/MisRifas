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
    'draw_date' => date('Y-m-d', strtotime('+30 days')), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
$created = assertHttp(201, $res, 'El usuario puede crear una rifa');
if ($created) {
    $owner = $db->query("SELECT created_by FROM raffles WHERE name = '__TEST__ Rifa Unify' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check((int)$owner === $vid, 'La rifa queda a nombre de su organizador provisionado', "created_by=$owner vendor=$vid");
}
