<?php
/**
 * Editor de plantillas (v4.13).
 * - Solo super_admin (403 para vendedor normal).
 * - GET lista las 7 con default + override; guardar aplica al MENSAJE REAL
 *   que construye MessageBuilderService; restaurar vuelve al original.
 * - Guarda crítica: si el editor borra {confirm_url} del mensaje al ganador,
 *   el sistema la repone al enviar.
 */

section('Editor de plantillas — overrides, guardas y restaurar');
$db = testdb();
$tokenAdmin = fxToken(1);
$tokenVendor = fxToken(5);

require_once __DIR__ . '/../../api/services/MessageBuilderService.php';
onTeardown(function () use ($db) {
    $db->exec("DELETE FROM message_templates WHERE template_key IN ('winner', 'payment_confirmed')");
});

// 1) Gate de rol.
$res = httpPost('/api/admin/templates.php', ['key' => 'winner', 'body_text' => 'x'], $tokenVendor);
check($res['code'] === 403, 'Vendedor normal → 403', 'HTTP ' . $res['code']);

// 2) GET lista con defaults.
$res = httpGet('/api/admin/templates.php', $tokenAdmin);
$ok = assertHttp(200, $res, 'GET lista las plantillas');
$tpls = $res['json']['data'] ?? [];
check(count($tpls) === 7, 'Son 7 plantillas editables', 'n=' . count($tpls));
$winner = null;
foreach ($tpls as $t) {
    if ($t['key'] === 'winner') {
        $winner = $t;
    }
}
check($winner && $winner['custom_text'] === null && strpos($winner['default_text'], '{confirm_url}') !== false,
    'winner arranca en ORIGINAL y su default incluye {confirm_url}', '');

// 3) Guardar override SIN la variable crítica → guarda con aviso, y el
//    mensaje real la repone.
$res = httpPost('/api/admin/templates.php', [
    'key' => 'winner',
    'body_text' => 'GANASTE {nombre}!! La rifa {raffle_name} es tuya con el {ticket_number}.',
], $tokenAdmin);
$ok = assertHttp(200, $res, 'Guardar override del ganador');
check(in_array('{confirm_url}', $res['json']['data']['variables_sin_usar'] ?? [], true),
    'El editor avisa que no usaste {confirm_url}', json_encode($res['json']['data'] ?? []));

MessageBuilderService::recargarPlantillas();
$msg = MessageBuilderService::buildWinnerMessage(
    ['name' => 'Rifa X', 'draw_date' => date('Y-m-d'), 'image_url' => ''],
    ['ticket_number' => '07'],
    ['name' => 'Camila', 'confirm_url' => 'https://x/confirmar?t=abc'],
    ['name' => 'Lotería Y'],
    '07'
);
check(strpos($msg['body_text'], 'GANASTE Camila!!') === 0, 'El mensaje REAL usa la plantilla editada', substr($msg['body_text'], 0, 60));
check(strpos($msg['body_text'], 'https://x/confirmar?t=abc') !== false,
    'GUARDA: el enlace de aceptación se repone aunque el editor lo haya borrado', $msg['body_text']);

// 4) Otra plantilla con todas sus variables → sin avisos.
$res = httpPost('/api/admin/templates.php', [
    'key' => 'payment_confirmed',
    'body_text' => 'Listo {nombre}: pago confirmado del boleto {ticket_number} en {raffle_name}. Sorteo: {draw_date}.',
], $tokenAdmin);
check(($res['json']['data']['variables_sin_usar'] ?? ['x']) === [], 'Con todas las variables no hay aviso', '');

// 5) Restaurar → vuelve el default.
$res = httpPost('/api/admin/templates.php', ['key' => 'winner', 'restore' => true], $tokenAdmin);
assertHttp(200, $res, 'Restaurar el original');
MessageBuilderService::recargarPlantillas();
$msg = MessageBuilderService::buildWinnerMessage(
    ['name' => 'Rifa X', 'draw_date' => date('Y-m-d'), 'image_url' => ''],
    ['ticket_number' => '07'],
    ['name' => 'Camila', 'confirm_url' => 'https://x/c'],
    ['name' => 'Lotería Y'],
    '07'
);
check(strpos($msg['body_text'], 'Felicitaciones Camila!') === 0, 'Tras restaurar, vuelve el texto original', substr($msg['body_text'], 0, 50));

// 6) Validaciones.
$res = httpPost('/api/admin/templates.php', ['key' => 'inexistente', 'body_text' => 'x'], $tokenAdmin);
check($res['code'] === 422, 'Plantilla desconocida → 422', 'HTTP ' . $res['code']);
$res = httpPost('/api/admin/templates.php', ['key' => 'winner', 'body_text' => str_repeat('a', 2100)], $tokenAdmin);
check($res['code'] === 422, 'Texto de más de 2000 chars → 422', 'HTTP ' . $res['code']);
