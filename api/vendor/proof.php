<?php

declare(strict_types=1);

/**
 * Sirve el comprobante de pago (promt2.md §16: los comprobantes viven FUERA
 * del directorio público y se sirven por controlador).
 * GET /api/vendor/proof.php?t=<proof_token>
 *
 * El token (48 hex, no adivinable) es la autorización: solo lo reciben el
 * vendedor (aviso WA + panel). Rate limit contra enumeración.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if (!RateLimiter::check('proof_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 60, 5)) {
    http_response_code(429);
    die('Demasiadas consultas');
}

$token = (string)($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(404);
    die('No encontrado');
}

$db = Database::getInstance()->getConnection();
// LIKE solo por el token (48 hex, único): MySQL normaliza el JSON con
// espacio tras los dos puntos, así que '"proof_token":"..."' NO matchea.
$stmt = $db->prepare("SELECT payment_gateway_response FROM payments WHERE payment_gateway_response LIKE ? LIMIT 1");
$stmt->execute(['%' . $token . '%']);
$gw = json_decode((string)$stmt->fetchColumn() ?: '{}', true);

$file = (string)($gw['proof_file'] ?? '');
if ($file === '' || !preg_match('/^proof_[a-z0-9_]+\.jpg$/', $file)) {
    http_response_code(404);
    die('No encontrado');
}
$path = __DIR__ . '/../../storage/comprobantes/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    die('No encontrado');
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
