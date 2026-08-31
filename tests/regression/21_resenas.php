<?php
/**
 * Reseñas de compradores VERIFICADOS (v4.12).
 *
 * - La credencial es el código de una boleta PAGADA del organizador.
 * - Una reseña por comprador por rifa (re-enviar ACTUALIZA, no duplica).
 * - Boleta de OTRO organizador → 403; código inexistente → 404.
 * - Interruptor de plataforma reviews_enabled: apagado → 403 y el perfil
 *   público no muestra la sección.
 */

section('Reseñas — comprador verificado, upsert e interruptor');
$db = testdb();

$prevSetting = $db->query("SELECT setting_value FROM system_settings WHERE setting_key='reviews_enabled'")->fetchColumn();
onTeardown(function () use ($db, $prevSetting) {
    $db->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='reviews_enabled'")->execute([$prevSetting]);
});
$db->exec("UPDATE system_settings SET setting_value='1' WHERE setting_key='reviews_enabled'");

// Rifa del vendor 5 con un boleto pagado (con código = credencial).
$raffle = fxRaffle(['tickets' => 10, 'created_by' => 5]);
$db->prepare("UPDATE raffles SET vendor_id=5 WHERE id=?")->execute([$raffle]);
$buyer = fxBuyer();
$code = 'TEST' . strtoupper(bin2hex(random_bytes(4)));
$db->prepare("UPDATE tickets SET status='paid', user_id=?, paid_at=NOW(), ticket_code=?, issued_at=NOW() WHERE raffle_id=? AND ticket_number='03'")
   ->execute([$buyer['id'], $code, $raffle]);
$slug5 = (string)$db->query("SELECT slug FROM vendors WHERE id=5")->fetchColumn();
onTeardown(function () use ($db, $raffle) {
    $db->prepare("DELETE FROM vendor_reviews WHERE raffle_id = ?")->execute([$raffle]);
});

// 1) Reseña válida → publicada.
$res = httpPost('/api/vendors/reviews.php', [
    'slug' => $slug5, 'ticket_code' => $code, 'rating' => 5,
    'comment' => '  <b>Excelente</b> organizador, cumplió con todo  ',
]);
$ok = assertHttp(200, $res, 'Comprador con boleta pagada publica su reseña');
$row = $db->query("SELECT rating, comment FROM vendor_reviews WHERE raffle_id=$raffle")->fetch(PDO::FETCH_ASSOC);
check((int)($row['rating'] ?? 0) === 5, 'La reseña quedó con 5 estrellas', json_encode($row));
check(strpos((string)$row['comment'], '<b>') === false, 'El comentario se guarda SIN HTML (strip_tags)', $row['comment'] ?? '');

// 2) Re-enviar ACTUALIZA (no duplica).
$res = httpPost('/api/vendors/reviews.php', [
    'slug' => $slug5, 'ticket_code' => $code, 'rating' => 3, 'comment' => 'Actualizo mi opinión',
]);
assertHttp(200, $res, 'Re-enviar la reseña actualiza la existente');
$n = (int)$db->query("SELECT COUNT(*) FROM vendor_reviews WHERE raffle_id=$raffle")->fetchColumn();
$r2 = (int)$db->query("SELECT rating FROM vendor_reviews WHERE raffle_id=$raffle")->fetchColumn();
check($n === 1 && $r2 === 3, 'Una sola fila, con la calificación nueva (3★)', "filas=$n rating=$r2");

// 3) Validaciones: rating fuera de rango y código inexistente.
$res = httpPost('/api/vendors/reviews.php', ['slug' => $slug5, 'ticket_code' => $code, 'rating' => 7]);
check($res['code'] === 422, 'Rating 7 → 422', 'HTTP ' . $res['code']);
$res = httpPost('/api/vendors/reviews.php', ['slug' => $slug5, 'ticket_code' => 'AAAABBBBCCCC', 'rating' => 5]);
check($res['code'] === 404, 'Código de boleta inexistente → 404', 'HTTP ' . $res['code']);

// 4) Boleta de OTRO organizador → 403 (solo reseñas a quien le compraste).
$slug1 = (string)$db->query("SELECT slug FROM vendors WHERE id=1")->fetchColumn();
$res = httpPost('/api/vendors/reviews.php', ['slug' => $slug1, 'ticket_code' => $code, 'rating' => 1]);
check($res['code'] === 403, 'Boleta de otro organizador → 403', 'HTTP ' . $res['code']);

// 5) Perfil público: muestra la reseña con nombre enmascarado.
$res = httpGet('/public/organizador.php?slug=' . urlencode($slug5));
check($res['code'] === 200 && strpos((string)$res['raw'], 'Reseñas de compradores') !== false,
    'El perfil del organizador muestra la sección de reseñas', 'HTTP ' . $res['code']);
check(strpos((string)$res['raw'], 'Actualizo mi opinión') !== false, 'La reseña aparece publicada', '');
check(strpos((string)$res['raw'], (string)$buyer['phone']) === false, 'El celular del comprador NO se expone', '');

// 5b) Transparencia del RESPONSABLE: nombre visible, documento solo parcial.
check(strpos((string)$res['raw'], '¿Quién responde por estas rifas?') !== false,
    'El perfil muestra la tarjeta del responsable', '');
$docCompleto = (string)$db->query("SELECT document_number FROM vendors WHERE id=5")->fetchColumn();
if (strlen(preg_replace('/\D/', '', $docCompleto)) >= 5) {
    check(strpos((string)$res['raw'], $docCompleto) === false,
        'El documento COMPLETO del responsable NO se expone (solo parcial)', '');
}

// 6) Interruptor APAGADO: API 403 y la sección desaparece del perfil.
$db->exec("UPDATE system_settings SET setting_value='0' WHERE setting_key='reviews_enabled'");
$res = httpPost('/api/vendors/reviews.php', ['slug' => $slug5, 'ticket_code' => $code, 'rating' => 4]);
check($res['code'] === 403 && ($res['json']['errors'] ?? '') === 'REVIEWS_DISABLED',
    'Con reseñas deshabilitadas la API responde 403 REVIEWS_DISABLED', 'HTTP ' . $res['code']);
$res = httpGet('/public/organizador.php?slug=' . urlencode($slug5));
check(strpos((string)$res['raw'], 'Reseñas de compradores') === false,
    'Con el interruptor apagado el perfil NO muestra reseñas', '');
