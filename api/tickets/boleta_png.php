<?php

declare(strict_types=1);

/**
 * Sirve la boleta digital en PNG (promt2.md §9.6).
 * GET /api/tickets/boleta_png.php?c=XXXX-XXXX-XXXX
 *
 * La imagen vive FUERA del directorio público (storage/boletas/) y se genera
 * bajo demanda con caché — nunca dentro de una transacción. Poseer el código
 * (no adivinable, §9.3) es la autorización; rate limit contra enumeración.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';
require_once __DIR__ . '/../../api/services/Boleta.php';
require_once __DIR__ . '/../../api/services/BoletaImage.php';

if (!RateLimiter::check('boletapng_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 20, 5)) {
    http_response_code(429);
    die('Demasiadas consultas');
}

$db = Database::getInstance()->getConnection();
$b = Boleta::buscar($db, (string)($_GET['c'] ?? ''));
if (!$b) {
    http_response_code(404);
    die('Boleta no encontrada');
}

$path = BoletaImage::ensure(Boleta::datosImagen($b));

header('Content-Type: image/png');
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="boleta-' . TicketCode::format($b['ticket_code']) . '.png"');
header('Cache-Control: private, max-age=300');
readfile($path);
