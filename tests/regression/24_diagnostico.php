<?php
/**
 * Auto-diagnóstico del sistema (anti "el asistente asume que todo funciona").
 * - Solo super_admin (403 para vendedor normal).
 * - Los checks son VERIFICACIONES reales, con estructura estable y estados
 *   válidos; el estado del OTP por WhatsApp refleja la BD, no un texto fijo.
 */

section('Diagnóstico del sistema — checks reales, no afirmaciones');
$db = testdb();
$tokenAdmin = fxToken(1);
$tokenVendor = fxToken(5);

require_once __DIR__ . '/../../api/services/SystemStatus.php';

// 1) Gate de rol.
$res = httpGet('/api/admin/system_status.php', $tokenVendor);
check($res['code'] === 403, 'Vendedor normal → 403', 'HTTP ' . $res['code']);

// 2) Estructura del endpoint.
$res = httpGet('/api/admin/system_status.php', $tokenAdmin);
assertHttp(200, $res, 'super_admin obtiene el diagnóstico');
$checks = $res['json']['data']['checks'] ?? [];
$keys = array_column($checks, 'key');
$esperadas = ['identidad', 'smtp', 'otp_email', 'otp_whatsapp', 'wa_engine', 'sms', 'storage', 'cron'];
check(array_diff($esperadas, $keys) === [], 'Cubre los 8 subsistemas (incluida la identidad administrable)', implode(',', $keys));
$estadosValidos = true;
foreach ($checks as $c) {
    if (!in_array($c['estado'], ['ok', 'warn', 'fail'], true) || $c['detalle'] === '') {
        $estadosValidos = false;
    }
}
check($estadosValidos, 'Todo check trae estado válido y detalle no vacío', '');

// 3) El estado del OTP WhatsApp sale de la BD (verifica, no asume).
$original = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'otp_whatsapp_number'")->fetchColumn();
onTeardown(function () use ($db, $original) {
    $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'otp_whatsapp_number'")->execute([$original]);
});

$db->exec("UPDATE system_settings SET setting_value = '' WHERE setting_key = 'otp_whatsapp_number'");
$porKey = fn($cs, $k) => current(array_filter($cs, fn($c) => $c['key'] === $k));
$c = $porKey(SystemStatus::checks($db), 'otp_whatsapp');
check($c['estado'] === 'warn' && $c['arreglo'] !== '', 'Sin número → warn con instrucción de arreglo', $c['estado']);

$db->exec("UPDATE system_settings SET setting_value = '573001112233' WHERE setting_key = 'otp_whatsapp_number'");
$c = $porKey(SystemStatus::checks($db), 'otp_whatsapp');
check($c['estado'] === 'ok' && strpos($c['detalle'], '573001112233') !== false, 'Con número → ok y lo muestra', $c['estado']);

// 4) Identidad administrable: el check refleja lo configurado en la BD
// (platform_name), no una marca quemada en el código.
$nombreBD = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'platform_name'")->fetchColumn();
$c = $porKey(SystemStatus::checks($db), 'identidad');
check($nombreBD !== '' && strpos($c['detalle'], $nombreBD) !== false,
    'La identidad muestra el nombre configurado en la BD', $c['detalle']);
