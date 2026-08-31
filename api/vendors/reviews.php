<?php

declare(strict_types=1);

/**
 * API: Reseñas de compradores VERIFICADOS al organizador
 * POST /api/vendors/reviews.php { slug, ticket_code, rating (1-5), comment? }
 *
 * La credencial es el CÓDIGO DE LA BOLETA: solo existe en boletos PAGADOS,
 * así que poseerlo prueba la compra. La boleta debe pertenecer a una rifa de
 * ESE organizador. Una reseña por comprador por rifa (la nueva pisa la
 * anterior). Todo el sistema se apaga con el setting reviews_enabled.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}
if (!RateLimiter::check('reviews_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 10, 10)) {
    Response::rateLimitExceeded('Demasiados intentos. Espera unos minutos.');
}

try {
    $db = Database::getInstance()->getConnection();

    $enabled = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'reviews_enabled'")->fetchColumn();
    if ($enabled !== '1') {
        Response::error('Las reseñas están deshabilitadas en la plataforma', 'REVIEWS_DISABLED', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($input['slug'] ?? '')));
    $rating = (int)($input['rating'] ?? 0);
    $comment = trim(strip_tags((string)($input['comment'] ?? '')));
    if (function_exists('mb_substr')) {
        $comment = mb_substr($comment, 0, 500);
    } else {
        $comment = substr($comment, 0, 500);
    }
    // Normalización Crockford del código (igual que el verificador).
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($input['ticket_code'] ?? '')));
    $code = strtr($code, ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);

    if ($slug === '' || strlen($code) !== 12) {
        Response::error('Faltan datos: organizador y código de boleta (XXXX-XXXX-XXXX)', null, 422);
    }
    if ($rating < 1 || $rating > 5) {
        Response::error('La calificación debe ser de 1 a 5 estrellas', null, 422);
    }

    // La boleta pagada ES la credencial; debe ser de una rifa de ESTE organizador.
    $stmt = $db->prepare("
        SELECT t.id AS ticket_id, t.user_id, t.raffle_id,
               COALESCE(r.vendor_id, r.created_by) AS vendor_id, v.slug AS vendor_slug
        FROM tickets t
        JOIN raffles r ON r.id = t.raffle_id
        JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
        WHERE t.ticket_code = ? AND t.status = 'paid'
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t) {
        Response::error('No encontramos una boleta pagada con ese código. Revisa que esté bien escrito.', null, 404);
    }
    if ($t['vendor_slug'] !== $slug) {
        Response::error('Esa boleta no es de este organizador: solo puedes reseñar a quien le compraste.', null, 403);
    }
    if (empty($t['user_id'])) {
        Response::error('La boleta no tiene comprador asociado', null, 409);
    }

    // Upsert (VALUES(): producción corre MariaDB). Una reseña por rifa;
    // volver a enviar actualiza la existente.
    $db->prepare("
        INSERT INTO vendor_reviews (vendor_id, user_id, raffle_id, ticket_id, rating, comment)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating), comment = VALUES(comment),
            ticket_id = VALUES(ticket_id), updated_at = NOW()
    ")->execute([
        (int)$t['vendor_id'], (int)$t['user_id'], (int)$t['raffle_id'],
        (int)$t['ticket_id'], $rating, $comment !== '' ? $comment : null,
    ]);

    Logger::activity('vendor_review_saved', (int)$t['user_id'], [
        'vendor_id' => (int)$t['vendor_id'], 'raffle_id' => (int)$t['raffle_id'], 'rating' => $rating,
    ]);

    Response::success(['rating' => $rating], '¡Gracias! Tu reseña quedó publicada.');
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al guardar la reseña');
}
