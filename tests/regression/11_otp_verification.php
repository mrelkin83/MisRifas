<?php
/**
 * Verificación OTP de registro (v4.0).
 *
 * Un registro NUEVO nace sin verificar: no puede crear rifas (403) hasta
 * validar un código por email (o WhatsApp inverso, probado vía OtpInbound).
 * Las cuentas previas a la migración quedaron verificadas (grandfather) —
 * eso lo cubren implícitamente los demás grupos (fixtures verificados).
 */

section('Verificación OTP — registro nuevo, gate de creación y código email');
$db = testdb();

$email = 'otptest_' . bin2hex(random_bytes(4)) . '@misrifas.test';
$phone = '38' . random_int(10000000, 99999999);

onTeardown(function () use ($db, $email) {
    $vid = $db->query("SELECT id FROM vendors WHERE email = " . $db->quote($email))->fetchColumn();
    if ($vid) {
        $db->prepare("DELETE FROM verification_codes WHERE account_type='vendor' AND account_id = ?")->execute([$vid]);
        $db->prepare("DELETE FROM vendors WHERE id = ?")->execute([$vid]);
    }
    $db->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
});

// 1) Registro nuevo → nace sin verificar y lo dice en la respuesta.
$res = httpPost('/api/auth/register.php', [
    'name' => '__TEST__ Otp Nuevo', 'email' => $email, 'phone' => $phone,
    'password' => 'demo1234', 'role' => 'vendor',
]);
$ok = assertHttp(200, $res, 'El registro nuevo funciona');
$token = $res['json']['data']['token'] ?? '';
check(($res['json']['data']['user']['verified'] ?? null) === false, 'La cuenta nace SIN verificar', $res['json']['data']['user']['verified'] ?? 'null');
check(($res['json']['data']['requires_verification'] ?? null) === true, 'La respuesta pide verificación', '');

// 2) Sin verificar NO puede crear rifas (gate del servidor).
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa OTP', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => date('Y-m-d', strtotime('+30 days')), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
check($res['code'] === 403, 'Sin verificar no puede crear rifas (403)', 'HTTP ' . $res['code']);

// 3) Pide código por email (el envío puede fallar sin SMTP local; el código
//    queda en BD igual).
$res = httpPost('/api/auth/otp/start.php', ['channel' => 'email'], $token);
$ok = assertHttp(200, $res, 'start.php (email) responde');
$vid = (int)$db->query("SELECT id FROM vendors WHERE email = " . $db->quote($email))->fetchColumn();
$code = $db->query("SELECT code FROM verification_codes WHERE account_type='vendor' AND account_id=$vid AND channel='email' AND verified_at IS NULL")->fetchColumn();
check(is_string($code) && preg_match('/^VERIFY-[A-Z0-9]{5}$/', $code), 'Se generó un código VERIFY-XXXXX', $code ?: 'sin código');

// 4) Un código equivocado se rechaza.
$res = httpPost('/api/auth/otp/verify.php', ['code' => 'VERIFY-XXXXX'], $token);
check($res['code'] === 422, 'Código equivocado → 422', 'HTTP ' . $res['code']);

// 5) El código correcto verifica la cuenta.
$res = httpPost('/api/auth/otp/verify.php', ['code' => $code], $token);
$ok = assertHttp(200, $res, 'El código correcto verifica');
check(($res['json']['data']['verified'] ?? false) === true, 'La cuenta queda verificada', '');
$verified = $db->query("SELECT email_verified_at FROM vendors WHERE id=$vid")->fetchColumn();
check(!empty($verified), 'vendors.email_verified_at quedó marcado', $verified ?: 'NULL');

// 6) Verificada, YA puede crear rifas.
$res = httpPost('/api/raffles/create.php', [
    'name' => '__TEST__ Rifa OTP', 'description' => 'x', 'department' => 'Cundinamarca',
    'city' => 'Bogota', 'scope' => 'municipal', 'whatsapp_contact' => '3007778899',
    'responsible_person' => 'Test', 'ticket_price' => 1000, 'total_tickets' => 100,
    'draw_date' => date('Y-m-d', strtotime('+30 days')), 'lottery_id' => 1, 'digits' => 2,
    'opportunities' => 1, 'winning_mode' => 'last_2', 'image_url' => '/assets/images/placeholder.svg',
], $token);
$created = assertHttp(201, $res, 'Verificada, la cuenta ya crea rifas');
if ($created) {
    $rid = (int)($res['json']['data']['raffle_id'] ?? $res['json']['data']['id'] ?? 0);
    onTeardown(function () use ($db, $rid) {
        if ($rid) {
            $db->prepare("DELETE FROM tickets WHERE raffle_id = ?")->execute([$rid]);
            $db->prepare("DELETE FROM raffles WHERE id = ?")->execute([$rid]);
        }
    });
}

// 7) OTP inverso por WhatsApp (OtpInbound directo, sin Evolution): el
//    remitente equivocado NO verifica; el remitente correcto SÍ.
$db->prepare("UPDATE vendors SET email_verified_at = NULL, phone_verified_at = NULL WHERE id = ?")->execute([$vid]);
$db->prepare("INSERT INTO verification_codes (account_type, account_id, channel, code, expires_at) VALUES ('vendor', ?, 'whatsapp', 'VERIFY-TESTX', DATE_ADD(NOW(), INTERVAL 10 MINUTE))")->execute([$vid]);

// Archivo temporal (escapeshellarg en Windows daña las comillas de un -r).
$inboundPath = realpath(__DIR__ . '/../../api/whatsapp/OtpInbound.php');
$snippet = '<?php require ' . var_export($inboundPath, true) . ";\n"
    . '$r1 = OtpInbound::procesar(["texto" => "VERIFY-TESTX", "telefono" => "573110000000"], null);' . "\n"
    . '$r2 = OtpInbound::procesar(["texto" => "VERIFY-TESTX", "telefono" => "57' . $phone . '"], null);' . "\n"
    . 'echo ($r1 ? "1" : "0") . ($r2 ? "1" : "0");' . "\n";
$tmpFile = tempnam(sys_get_temp_dir(), 'otp') . '.php';
file_put_contents($tmpFile, $snippet);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmpFile) . ' 2>&1', $outArr, $rc);
@unlink($tmpFile);
$out = implode('', $outArr);
check($rc === 0 && substr(trim($out), -2) === '11', 'OtpInbound procesa ambos mensajes como OTP', "rc=$rc out=$out");
$phoneVerified = $db->query("SELECT phone_verified_at FROM vendors WHERE id=$vid")->fetchColumn();
check(!empty($phoneVerified), 'El remitente correcto verifica por WhatsApp', $phoneVerified ?: 'NULL');
