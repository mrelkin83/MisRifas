<?php
/**
 * API: Aprobar Pago Manual
 * POST /api/vendor/approve_payment.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

Auth::requireVendor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$paymentId = intval($input['payment_id'] ?? 0);

if ($paymentId <= 0) {
    Response::error('payment_id requerido', null, 400);
}

$db = Database::getInstance()->getConnection();
$vendorId = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("
        SELECT p.*, t.raffle_id, r.vendor_id
        FROM payments p
        JOIN tickets t ON p.ticket_id = t.id
        JOIN raffles r ON t.raffle_id = r.id
        WHERE p.id = ? AND r.vendor_id = ? AND p.status = 'pending'
    ");
    $stmt->execute([$paymentId, $vendorId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        Response::error('Pago no encontrado o ya procesado', null, 404);
    }

    $stmt = $db->prepare("UPDATE payments SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?");
    $stmt->execute([$paymentId]);

    $stmt = $db->prepare("UPDATE tickets SET status = 'paid' WHERE id = ?");
    $stmt->execute([$payment['ticket_id']]);

    Logger::activity('payment_approved', $vendorId, ['payment_id' => $paymentId, 'ticket_id' => $payment['ticket_id']]);

    Response::success(null, 'Pago aprobado exitosamente');
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al aprobar pago');
}
