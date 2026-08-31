<?php
/**
 * Configuración SMTP desde el panel (el formulario decía "Error al guardar").
 * - update.php comparaba el rol contra 'superadmin' (SIN guion bajo, un rol
 *   inexistente) → 403 para todo el mundo; y las claves mailing_* ni estaban
 *   en la lista blanca. Este test fija el contrato real:
 * - super_admin guarda mailing_* clave por clave (como hace el formulario).
 * - Vendedor normal → 403 (son ajustes globales).
 * - El GET del admin NUNCA devuelve secretos (mailing_smtp_pass, brevo_api_key).
 */

section('Configuración SMTP — guardado por panel y secretos redactados');
$db = testdb();
$tokenAdmin = fxToken(1);
$tokenVendor = fxToken(5);

// Estado previo para restaurar.
$prevHost = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key='mailing_smtp_host'")->fetchColumn();
$prevPass = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key='mailing_smtp_pass'")->fetchColumn();
onTeardown(function () use ($db, $prevHost, $prevPass) {
    $db->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='mailing_smtp_host'")->execute([$prevHost]);
    $db->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='mailing_smtp_pass'")->execute([$prevPass]);
});

// 1) super_admin guarda una clave mailing_* (el flujo real del formulario).
$res = httpPost('/api/admin/settings/update.php', ['key' => 'mailing_smtp_host', 'value' => 'smtp.test.local'], $tokenAdmin);
assertHttp(200, $res, 'super_admin guarda mailing_smtp_host');
$val = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key='mailing_smtp_host'")->fetchColumn();
check($val === 'smtp.test.local', 'El valor quedó persistido en la BD', "db=$val");

// 2) La contraseña también se puede guardar…
$res = httpPost('/api/admin/settings/update.php', ['key' => 'mailing_smtp_pass', 'value' => 'secreta-123'], $tokenAdmin);
assertHttp(200, $res, 'super_admin guarda mailing_smtp_pass');

// …pero el GET del admin la devuelve REDACTADA (nunca viaja al navegador).
$res = httpGet('/api/admin/settings.php', $tokenAdmin);
assertHttp(200, $res, 'GET de settings responde');
$d = $res['json']['data'] ?? [];
check(($d['mailing_smtp_pass'] ?? 'x') === '', 'mailing_smtp_pass llega vacía (redactada)', json_encode($d['mailing_smtp_pass'] ?? null));
check(($d['brevo_api_key'] ?? '') === '', 'brevo_api_key llega vacía (redactada)', '');
check(($d['mailing_smtp_host'] ?? '') === 'smtp.test.local', 'Las claves no secretas sí llegan', $d['mailing_smtp_host'] ?? '');

// 3) Vendedor normal NO puede tocar ajustes globales.
$res = httpPost('/api/admin/settings/update.php', ['key' => 'mailing_smtp_host', 'value' => 'hack.local'], $tokenVendor);
check($res['code'] === 403, 'Vendedor normal → 403', 'HTTP ' . $res['code']);

// 4) Clave fuera de la lista blanca → 403.
$res = httpPost('/api/admin/settings/update.php', ['key' => 'otp_whatsapp_number', 'value' => 'x'], $tokenAdmin);
check($res['code'] === 403, 'Clave no listada → 403 (lista blanca activa)', 'HTTP ' . $res['code']);
