<?php
/**
 * Scraper de resultados administrable (Gestión de Rifas → Scraper).
 * - GET devuelve estado VIVO: interruptor, pendientes, loterías con slug
 *   efectivo y override, últimos resultados.
 * - Solo super_admin (vendedor → 403).
 * - El interruptor y los slugs por lotería persisten; slug inválido → 422.
 * - Con el scraper APAGADO, "ejecutar" se niega (409) — y el cron también
 *   respeta el interruptor (ScraperRunner::habilitado).
 */

section('Scraper — configuración administrable y estado en vivo');
$db = testdb();
$tokenAdmin = fxToken(1);
$tokenVendor = fxToken(5);

require_once __DIR__ . '/../../api/services/ScraperRunner.php';

// Estado previo para restaurar.
$prevEnabled = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key='scraper_enabled'")->fetchColumn();
$lot = $db->query("SELECT id, api_source FROM lotteries ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
onTeardown(function () use ($db, $prevEnabled, $lot) {
    $db->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='scraper_enabled'")->execute([$prevEnabled]);
    if ($lot) {
        $db->prepare("UPDATE lotteries SET api_source=? WHERE id=?")->execute([$lot['api_source'], $lot['id']]);
    }
});

// 1) Gate de rol.
$res = httpGet('/api/admin/scraper.php', $tokenVendor);
check($res['code'] === 403, 'Vendedor normal → 403', 'HTTP ' . $res['code']);

// 2) GET con estructura viva.
$res = httpGet('/api/admin/scraper.php', $tokenAdmin);
assertHttp(200, $res, 'super_admin obtiene el estado del scraper');
$d = $res['json']['data'] ?? [];
check(array_key_exists('enabled', $d) && isset($d['loterias']) && array_key_exists('pendientes', $d),
    'Trae interruptor, loterías y pendientes', json_encode(array_keys($d)));
check(!empty($d['loterias'][0]['slug_auto']), 'Cada lotería expone su slug efectivo', json_encode($d['loterias'][0] ?? null));

// 3) Guardar: apagar + slug propio.
$res = httpPost('/api/admin/scraper.php', [
    'action' => 'guardar', 'enabled' => false,
    'sources' => [$lot['id'] => 'loteria-de-prueba'],
], $tokenAdmin);
assertHttp(200, $res, 'Guardar configuración (apagado + slug propio)');
$v = (string)$db->query("SELECT setting_value FROM system_settings WHERE setting_key='scraper_enabled'")->fetchColumn();
check($v === '0', 'El interruptor quedó en 0 en la BD', "db=$v");
$src = (string)$db->query("SELECT api_source FROM lotteries WHERE id=" . (int)$lot['id'])->fetchColumn();
check($src === 'loteria-de-prueba', 'El slug propio quedó en lotteries.api_source', "db=$src");
check(ScraperRunner::habilitado($db) === false, 'ScraperRunner (el cron) también lo ve apagado', '');

// 4) Apagado → ejecutar se niega.
$res = httpPost('/api/admin/scraper.php', ['action' => 'ejecutar'], $tokenAdmin);
check($res['code'] === 409, 'Ejecutar con el scraper apagado → 409', 'HTTP ' . $res['code']);

// 5) Slug inválido → 422 (no se cuela nada raro en la URL del scrape).
$res = httpPost('/api/admin/scraper.php', [
    'action' => 'guardar', 'enabled' => true,
    'sources' => [$lot['id'] => '../etc/passwd'],
], $tokenAdmin);
check($res['code'] === 422, 'Slug inválido → 422', 'HTTP ' . $res['code']);

// ── Calendario de loterías (día/hora/activa administrables) ──
section('Loterías — calendario (día y hora) administrable');
$cal = $db->query("SELECT id, day_of_week, draw_time, active FROM lotteries ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
onTeardown(function () use ($db, $cal) {
    $db->prepare("UPDATE lotteries SET day_of_week=?, draw_time=?, active=? WHERE id=?")
       ->execute([$cal['day_of_week'], $cal['draw_time'], $cal['active'], $cal['id']]);
    $db->exec("DELETE FROM lotteries WHERE name = 'Lotería de Prueba Regresión'");
});

// 6) Vendedor no puede tocar el calendario.
$res = httpPost('/api/admin/lotteries.php', ['action' => 'guardar', 'loterias' => [
    ['id' => $cal['id'], 'day_of_week' => 'sunday', 'draw_time' => '20:00', 'active' => true],
]], $tokenVendor);
check($res['code'] === 403, 'Calendario: vendedor → 403', 'HTTP ' . $res['code']);

// 7) super_admin cambia día y hora → persiste.
$res = httpPost('/api/admin/lotteries.php', ['action' => 'guardar', 'loterias' => [
    ['id' => $cal['id'], 'day_of_week' => 'sunday', 'draw_time' => '20:15', 'active' => true],
]], $tokenAdmin);
assertHttp(200, $res, 'Cambiar día y hora de una lotería');
$fila = $db->query("SELECT day_of_week, draw_time FROM lotteries WHERE id=" . (int)$cal['id'])->fetch(PDO::FETCH_ASSOC);
check($fila['day_of_week'] === 'sunday' && $fila['draw_time'] === '20:15:00',
    'Día y hora persistidos en la BD', json_encode($fila));

// 8) Día u hora inválidos → 422.
$res = httpPost('/api/admin/lotteries.php', ['action' => 'guardar', 'loterias' => [
    ['id' => $cal['id'], 'day_of_week' => 'lunes', 'draw_time' => '20:00', 'active' => true],
]], $tokenAdmin);
check($res['code'] === 422, 'Día inválido → 422', 'HTTP ' . $res['code']);
$res = httpPost('/api/admin/lotteries.php', ['action' => 'guardar', 'loterias' => [
    ['id' => $cal['id'], 'day_of_week' => 'monday', 'draw_time' => '25:99', 'active' => true],
]], $tokenAdmin);
check($res['code'] === 422, 'Hora inválida → 422', 'HTTP ' . $res['code']);

// 8b) Renombrar la lotería (el name viaja junto a día/hora) → persiste;
//     y un nombre duplicado con OTRA lotería → 409.
$nombreOriginal = (string)$db->query("SELECT name FROM lotteries WHERE id=" . (int)$cal['id'])->fetchColumn();
$otro = (string)$db->query("SELECT name FROM lotteries WHERE id <> " . (int)$cal['id'] . " LIMIT 1")->fetchColumn();
$res = httpPost('/api/admin/lotteries.php', ['action' => 'guardar', 'loterias' => [
    ['id' => $cal['id'], 'name' => 'Lotería Renombrada [TEST]', 'day_of_week' => 'sunday', 'draw_time' => '20:15', 'active' => true],
]], $tokenAdmin);
assertHttp(200, $res, 'Renombrar una lotería');
$n = (string)$db->query("SELECT name FROM lotteries WHERE id=" . (int)$cal['id'])->fetchColumn();
check($n === 'Lotería Renombrada [TEST]', 'El nombre nuevo persistió', "db=$n");
$res = httpPost('/api/admin/lotteries.php', ['action' => 'guardar', 'loterias' => [
    ['id' => $cal['id'], 'name' => $otro, 'day_of_week' => 'sunday', 'draw_time' => '20:15', 'active' => true],
]], $tokenAdmin);
check($res['code'] === 409, 'Renombrar a un nombre ya usado → 409', 'HTTP ' . $res['code']);
$db->prepare("UPDATE lotteries SET name=? WHERE id=?")->execute([$nombreOriginal, $cal['id']]);

// 9) Crear lotería nueva + duplicado rechazado.
$res = httpPost('/api/admin/lotteries.php', [
    'action' => 'crear', 'name' => 'Lotería de Prueba Regresión',
    'day_of_week' => 'sunday', 'draw_time' => '19:00',
], $tokenAdmin);
assertHttp(200, $res, 'Crear una lotería nueva');
$res = httpPost('/api/admin/lotteries.php', [
    'action' => 'crear', 'name' => 'Lotería de Prueba Regresión',
    'day_of_week' => 'sunday', 'draw_time' => '19:00',
], $tokenAdmin);
check($res['code'] === 409, 'Nombre duplicado → 409', 'HTTP ' . $res['code']);
