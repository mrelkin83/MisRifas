<?php

declare(strict_types=1);

/**
 * API: Reportar entrega del premio (promt2.md §13.4 paso 3)
 * POST /api/vendor/delivery.php { raffle_id }
 *
 * El vendedor declara que entregó → delivery_reported. Eso NO lo muestra en
 * verde: el hall lo marca "pendiente de confirmación del ganador". Se genera
 * un delivery_token DISTINTO al de aceptación y el GANADOR recibe un mensaje
 * NUEVO (separado, §13.4) con su enlace para confirmar o disputar.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $vendor = Auth::requireVendor();
    $vendorId = (int)$vendor['id'];
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $raffleId = (int)($input['raffle_id'] ?? 0);

    $stmt = $db->prepare("
        SELECT rw.id, rw.acceptance_status, rw.delivery_status,
               r.name AS raffle_name,
               u.name AS winner_name, u.phone_whatsapp AS winner_phone, u.email AS winner_email,
               t.ticket_number
        FROM raffle_winners rw
        JOIN raffles r ON r.id = rw.raffle_id AND COALESCE(r.vendor_id, r.created_by) = ?
        JOIN users u ON u.id = rw.user_id
        JOIN tickets t ON t.id = rw.ticket_id
        WHERE rw.raffle_id = ?
        ORDER BY rw.id DESC LIMIT 1
    ");
    $stmt->execute([$vendorId, $raffleId]);
    $w = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$w) {
        Response::error('Esta rifa no tiene ganador registrado (o no es tuya)', null, 404);
    }
    if ($w['acceptance_status'] !== 'accepted') {
        Response::error('El ganador aún no ha aceptado su premio con su enlace. Pídele que lo acepte primero — así queda constancia pública antes de la entrega.', null, 409);
    }
    if ($w['delivery_status'] === 'delivery_confirmed') {
        Response::error('La entrega ya fue confirmada por el ganador ✅', null, 409);
    }
    if ($w['delivery_status'] === 'disputed') {
        Response::error('La entrega está en DISPUTA: el ganador dice que no recibió. Resuélvelo con él o con el administrador.', null, 409);
    }
    if ($w['delivery_status'] === 'delivery_reported') {
        Response::error('Ya reportaste la entrega; falta que el ganador la confirme con su enlace.', null, 409);
    }

    // Token de ENTREGA — distinto al de aceptación, se invalida al usarse.
    $deliveryToken = bin2hex(random_bytes(32));
    $db->prepare("
        UPDATE raffle_winners
        SET delivery_status = 'delivery_reported', delivery_reported_at = NOW(), delivery_token = ?
        WHERE id = ? AND delivery_status = 'pending'
    ")->execute([$deliveryToken, $w['id']]);

    Logger::activity('delivery_reported', $vendorId, ['raffle_id' => $raffleId, 'winner_id' => $w['id']]);

    // Mensaje NUEVO al ganador (separado del de aceptación por diseño §13.4).
    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $confirmUrl = $appUrl . BASE_PATH . '/public/entrega-confirmar.php?t=' . $deliveryToken;
    $texto = "Hola {$w['winner_name']} 👋\n\nEl organizador reporta que TE ENTREGÓ el premio de la rifa \"{$w['raffle_name']}\" (boleto #{$w['ticket_number']}).\n\n"
        . "¿Lo recibiste? Confírmalo aquí (o repórtalo si NO lo has recibido):\n{$confirmUrl}\n\n"
        . 'Tu confirmación queda pública en el hall de ganadores y protege a los próximos compradores. — MisRifas';
    $ins = $db->prepare("
        INSERT INTO message_queue
            (raffle_id, vendor_id, recipient_phone, recipient_email, channel, message_type, subject, body_text, status, scheduled_at, created_at)
        VALUES (?, ?, ?, ?, ?, 'winner', ?, ?, 'pending', NOW(), NOW())
    ");
    if (!empty($w['winner_email']) && filter_var($w['winner_email'], FILTER_VALIDATE_EMAIL)) {
        $ins->execute([$raffleId, $vendorId, null, $w['winner_email'], 'email', '¿Recibiste tu premio? Confírmalo — ' . $w['raffle_name'], $texto]);
    }
    if (!empty($w['winner_phone'])) {
        $ins->execute([$raffleId, $vendorId, $w['winner_phone'], null, 'whatsapp', '¿Recibiste tu premio?', $texto]);
    }

    Response::success(['delivery_status' => 'delivery_reported'],
        'Entrega reportada. El ganador recibió su enlace para confirmarla — hasta entonces figura como "pendiente de confirmación".');
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al reportar la entrega');
}
