<?php

declare(strict_types=1);

/**
 * API: Confirmación de entrega por el GANADOR (promt2.md §13.4 pasos 4-5)
 * GET  /api/winners/delivery.php?t=<token>          → datos para la página
 * POST /api/winners/delivery.php
 *   { token, action: 'confirm', photo?: dataURI }   → delivery_confirmed
 *   { token, action: 'dispute', reason }            → disputed (+aviso vendedor y admin)
 *
 * El token es DISTINTO al de aceptación y se invalida al usarse. La foto es
 * opcional, se valida por contenido real y se re-codifica con GD antes de
 * guardarse FUERA del directorio público.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if (!RateLimiter::check('delivery_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 20, 5)) {
    Response::error('Demasiados intentos. Espera unos minutos.', null, 429);
}

try {
    $db = Database::getInstance()->getConnection();

    $token = (string)($_SERVER['REQUEST_METHOD'] === 'GET'
        ? ($_GET['t'] ?? '')
        : ((json_decode(file_get_contents('php://input'), true) ?: [])['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        Response::error('Enlace inválido o vencido', null, 404);
    }

    $stmt = $db->prepare("
        SELECT rw.id, rw.delivery_status, rw.raffle_id,
               r.name AS raffle_name, r.image_url,
               COALESCE(r.vendor_id, r.created_by) AS vendor_id,
               v.business_name AS vendor_name, v.phone AS vendor_phone, v.email AS vendor_email,
               u.name AS winner_name, t.ticket_number
        FROM raffle_winners rw
        JOIN raffles r ON r.id = rw.raffle_id
        JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
        JOIN users u ON u.id = rw.user_id
        JOIN tickets t ON t.id = rw.ticket_id
        WHERE rw.delivery_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $w = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$w || $w['delivery_status'] !== 'delivery_reported') {
        Response::error('Enlace inválido, ya usado o vencido', null, 404);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Response::success([
            'raffle_name' => $w['raffle_name'],
            'ticket_number' => $w['ticket_number'],
            'winner_name' => $w['winner_name'],
            'vendor_name' => $w['vendor_name'],
        ]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string)($input['action'] ?? '');
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    if ($action === 'confirm') {
        // Foto opcional del ganador recibiendo el premio (§13.4).
        $photoPath = null;
        $photo = (string)($input['photo'] ?? '');
        if ($photo !== '' && preg_match('/^data:image\/(jpe?g|png|webp);base64,(.+)$/i', $photo, $m)) {
            $raw = base64_decode($m[2], true);
            if ($raw !== false && strlen($raw) <= 5 * 1024 * 1024) {
                $info = @getimagesizefromstring($raw);
                if ($info !== false && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    // Re-codificar con GD: descarta cualquier payload embebido.
                    $im = @imagecreatefromstring($raw);
                    if ($im !== false) {
                        $dir = __DIR__ . '/../../storage/entregas';
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $file = 'entrega_' . $w['id'] . '_' . bin2hex(random_bytes(6)) . '.jpg';
                        imagejpeg($im, $dir . '/' . $file, 82);
                        imagedestroy($im);
                        $photoPath = 'storage/entregas/' . $file;
                    }
                }
            }
        }

        $db->prepare("
            UPDATE raffle_winners
            SET delivery_status = 'delivery_confirmed', delivery_confirmed_at = NOW(),
                delivery_confirmed_ip = ?, delivery_photo_path = ?, delivery_token = NULL,
                prize_delivered = 1, prize_delivered_at = NOW()
            WHERE id = ? AND delivery_status = 'delivery_reported'
        ")->execute([$ip, $photoPath, $w['id']]);
        Logger::activity('delivery_confirmed', (int)$w['id'], ['raffle_id' => $w['raffle_id'], 'con_foto' => $photoPath !== null]);
        Response::success(['delivery_status' => 'delivery_confirmed'],
            '¡Gracias por confirmar! Tu entrega ya figura en verde en el hall de ganadores. 🎉');
    }

    if ($action === 'dispute') {
        $reason = mb_substr(trim((string)($input['reason'] ?? '')), 0, 2000);
        if (mb_strlen($reason) < 5) {
            Response::error('Cuéntanos brevemente qué pasó (mínimo 5 caracteres)', null, 422);
        }
        $db->prepare("
            UPDATE raffle_winners
            SET delivery_status = 'disputed', dispute_reason = ?, delivery_token = NULL
            WHERE id = ? AND delivery_status = 'delivery_reported'
        ")->execute([$reason, $w['id']]);
        Logger::warning('DISPUTA de entrega de premio', ['winner_id' => $w['id'], 'raffle_id' => $w['raffle_id']]);

        // Avisar al vendedor y al administrador (§13.4 paso 5).
        $texto = "⚠️ DISPUTA de entrega en la rifa \"{$w['raffle_name']}\".\n\n"
            . "El ganador {$w['winner_name']} (boleto #{$w['ticket_number']}) reporta que NO recibió el premio.\n"
            . "Motivo: {$reason}\n\nLa disputa es visible públicamente hasta resolverse.";
        $ins = $db->prepare("
            INSERT INTO message_queue
                (raffle_id, vendor_id, recipient_phone, recipient_email, channel, message_type, subject, body_text, status, scheduled_at, created_at)
            VALUES (?, ?, ?, ?, ?, 'winner', ?, ?, 'pending', NOW(), NOW())
        ");
        if (!empty($w['vendor_email'])) {
            $ins->execute([$w['raffle_id'], $w['vendor_id'], null, $w['vendor_email'], 'email', '⚠️ Disputa de entrega — ' . $w['raffle_name'], $texto]);
        }
        if (!empty($w['vendor_phone'])) {
            $ins->execute([$w['raffle_id'], $w['vendor_id'], $w['vendor_phone'], null, 'whatsapp', '⚠️ Disputa de entrega', $texto]);
        }
        $admins = $db->query("SELECT id, email FROM vendors WHERE role = 'super_admin' AND email IS NOT NULL");
        foreach ($admins->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $ins->execute([$w['raffle_id'], (int)$a['id'], null, $a['email'], 'email', '⚠️ [ADMIN] Disputa de entrega — ' . $w['raffle_name'], $texto]);
        }

        Response::success(['delivery_status' => 'disputed'],
            'Reporte recibido. El organizador y el administrador fueron notificados; la disputa queda visible públicamente.');
    }

    Response::error('Acción inválida', null, 400);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar la confirmación');
}
