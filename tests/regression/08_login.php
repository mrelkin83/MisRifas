<?php
/** Login: credenciales válidas emiten token con rol; inválidas → 401. */

section('Login — credenciales válidas e inválidas');
$db = testdb();

// Guardar/restaurar el token del vendedor de prueba (login lo rota).
$prev = $db->query("SELECT auth_token, auth_token_expires FROM vendors WHERE id=5")->fetch(PDO::FETCH_ASSOC);
onTeardown(function () use ($db, $prev) {
    $db->prepare("UPDATE vendors SET auth_token=?, auth_token_expires=? WHERE id=5")
       ->execute([$prev['auth_token'] ?? null, $prev['auth_token_expires'] ?? null]);
});

// Requiere que exista la cuenta de prueba local con esta contraseña.
$hasVendor = (int)$db->query("SELECT COUNT(*) FROM vendors WHERE email='vendedor@test.com'")->fetchColumn();
if (!$hasVendor) {
    check(false, "Cuenta de prueba vendedor@test.com no existe (créala con demo1234)", 'saltado');
    return;
}

$res = httpPost('/api/auth/login.php', ['email' => 'vendedor@test.com', 'password' => 'demo1234']);
$ok = assertHttp(200, $res, 'Login con credenciales válidas');
check($ok && !empty($res['json']['data']['token']), 'Devuelve un token', $res['json']['data'] ?? null);
check($ok && ($res['json']['data']['user']['role'] ?? '') === 'vendor', 'El usuario tiene rol vendor', $res['json']['data']['user']['role'] ?? '');

$res = httpPost('/api/auth/login.php', ['email' => 'vendedor@test.com', 'password' => 'contraseña-incorrecta']);
check($res['code'] === 401, 'Login con contraseña incorrecta → 401', "HTTP {$res['code']}");
check(empty($res['json']['data']['token']), 'No emite token con credenciales inválidas', $res['json'] ?? null);
