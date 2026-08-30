<?php
/** Rate limiting del Tapazo público (crear). Payloads inválidos: no crean nada. */

section('Tapazo — rate limiting activo en crear.php');
$codes = [];
for ($i = 0; $i < 7; $i++) {
    // Título/fecha vacíos: si supera el limiter, valida y devuelve 400; nunca inserta.
    $res = httpPost('/api/tapazo/crear.php', ['titulo' => '', 'fecha_hora_destape' => '']);
    $codes[] = $res['code'];
}
$has429 = in_array(429, $codes, true);
check($has429, 'Una ráfaga de creaciones dispara 429 (limiter activo)', 'códigos: ' . implode(',', $codes));
// Ninguna respuesta debe ser 200 (no se crean tapazos con payload inválido).
check(!in_array(200, $codes, true), 'Ningún tapazo se creó con payload inválido', 'códigos: ' . implode(',', $codes));
