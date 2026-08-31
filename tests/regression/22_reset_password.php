<?php
/**
 * Restablecer contraseña desde el gestor de usuarios.
 * - SOLO super_admin (vendedor normal → 403).
 * - Genera temporal, la devuelve UNA vez, invalida la sesión anterior y el
 *   usuario puede entrar con ella.
 */

section('Gestor de usuarios — restablecer contraseña');
$db = testdb();
$tokenAdmin = fxToken(1);  // super_admin
$tokenVendor = fxToken(5); // vendedor normal

$buyer = fxBuyer();
$mail = (string)$db->query("SELECT email FROM users WHERE id = {$buyer['id']}")->fetchColumn();

// 1) Vendedor normal NO puede.
$res = httpPost('/api/admin/users/reset_password.php', ['type' => 'buyer', 'id' => $buyer['id']], $tokenVendor);
check($res['code'] === 403, 'Vendedor normal → 403 (solo super_admin)', 'HTTP ' . $res['code']);

// 2) super_admin restablece y recibe la temporal UNA vez.
$res = httpPost('/api/admin/users/reset_password.php', ['type' => 'buyer', 'id' => $buyer['id']], $tokenAdmin);
$ok = assertHttp(200, $res, 'super_admin restablece la contraseña del comprador');
$tmp = (string)($res['json']['data']['password'] ?? '');
check(strpos($tmp, 'MR-') === 0 && strlen($tmp) === 11, 'La temporal es legible (MR-XXXXXXXX)', $tmp);

// 3) El usuario entra con la temporal.
$res = httpPost('/api/auth/login.php', ['email' => $mail, 'password' => $tmp]);
check($res['code'] === 200 && !empty($res['json']['data']['token']),
    'El comprador inicia sesión con la contraseña temporal', 'HTTP ' . $res['code']);

// 4) Datos inválidos y usuario inexistente.
$res = httpPost('/api/admin/users/reset_password.php', ['type' => 'pirata', 'id' => 1], $tokenAdmin);
check($res['code'] === 422, 'type inválido → 422', 'HTTP ' . $res['code']);
$res = httpPost('/api/admin/users/reset_password.php', ['type' => 'buyer', 'id' => 99999999], $tokenAdmin);
check($res['code'] === 404, 'Usuario inexistente → 404', 'HTTP ' . $res['code']);
