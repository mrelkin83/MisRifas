<?php
/**
 * API: Listar Participantes de una Rifa
 * GET /api/vendor/list_participants.php?raffle_id=xxx
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';

Auth::requireVendor();

$raffleId = intval($_GET['raffle_id'] ?? 0);

if ($raffleId <= 0) {
    Response::error('raffle_id requerido', null, 400);
}

$db = Database::getInstance()->getConnection();
$vendorId = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("
        SELECT t.id, t.ticket_number, t.status, t.created_at,
               u.name, u.phone_whatsapp as phone
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN raffles r ON t.raffle_id = r.id
        WHERE t.raffle_id = ? AND r.vendor_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$raffleId, $vendorId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success($participants, 'Participantes listados');
} catch (Exception $e) {
    Response::serverError('Error al listar participantes');
}
