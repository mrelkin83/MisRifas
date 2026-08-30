<?php
/**
 * API pública de aceptación del premio por el ganador (transparencia).
 *   GET  ?t=<token>            -> detalles del premio + estado de aceptación
 *   POST {token, action}       -> action = 'accept' | 'decline'
 *
 * Sin login: el token del enlace ES la credencial (se envía solo al ganador
 * por WhatsApp/correo). Idempotente: solo transiciona desde 'pending'. Se
 * registra fecha e IP para dejar constancia verificable.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

function jr(array $d, int $c = 200): void {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = trim((string)($in['token'] ?? ''));
    $action = (string)($in['action'] ?? '');
} else {
    $token = trim((string)($_GET['t'] ?? ''));
    $action = '';
}

if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/', $token)) {
    jr(['success' => false, 'message' => 'Enlace inválido'], 400);
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT rw.id, rw.acceptance_status, rw.accepted_at,
               rw.winning_number, rw.matched_opportunity,
               r.name AS raffle_name, r.draw_date,
               l.name AS lottery_name,
               t.ticket_number,
               u.name AS winner_name
        FROM raffle_winners rw
        JOIN raffles r ON rw.raffle_id = r.id
        LEFT JOIN lotteries l ON r.lottery_id = l.id
        LEFT JOIN tickets t ON rw.ticket_id = t.id
        LEFT JOIN users u ON rw.user_id = u.id
        WHERE rw.acceptance_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $w = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$w) {
        jr(['success' => false, 'message' => 'No encontramos este premio. El enlace puede ser incorrecto.'], 404);
    }

    $details = [
        'raffle_name'    => $w['raffle_name'],
        'lottery_name'   => $w['lottery_name'],
        'draw_date'      => $w['draw_date'],
        'winning_number' => $w['winning_number'],
        'ticket_number'  => $w['ticket_number'] !== null ? str_pad((string)$w['ticket_number'], 4, '0', STR_PAD_LEFT) : null,
        'winner_name'    => $w['winner_name'],
        'status'         => $w['acceptance_status'],
        'accepted_at'    => $w['accepted_at'],
    ];

    if ($method === 'GET') {
        jr(['success' => true, 'winner' => $details]);
    }

    // POST: registrar la decisión.
    if (!in_array($action, ['accept', 'decline'], true)) {
        jr(['success' => false, 'message' => 'Acción inválida'], 400);
    }

    if ($w['acceptance_status'] !== 'pending') {
        // Ya decidido: idempotente, devolvemos el estado actual sin cambiarlo.
        jr(['success' => true, 'already' => true, 'winner' => $details]);
    }

    $newStatus = $action === 'accept' ? 'accepted' : 'declined';
    $ip = substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $upd = $db->prepare("
        UPDATE raffle_winners
        SET acceptance_status = ?, accepted_at = NOW(), acceptance_ip = ?
        WHERE id = ? AND acceptance_status = 'pending'
    ");
    $upd->execute([$newStatus, $ip, $w['id']]);

    $details['status'] = $newStatus;
    $details['accepted_at'] = date('Y-m-d H:i:s');
    jr(['success' => true, 'winner' => $details]);

} catch (Throwable $e) {
    error_log('winners/accept error: ' . $e->getMessage());
    jr(['success' => false, 'message' => 'Ocurrió un error. Intenta de nuevo.'], 500);
}
