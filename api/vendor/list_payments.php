<?php
/**
 * API: Listar Pagos del Vendedor
 * GET /api/vendor/list_payments.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';

Auth::requireVendor();

$db = Database::getInstance()->getConnection();
$vendorId = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("
        SELECT p.id, p.amount, p.status, p.created_at,
               t.ticket_number, u.name as buyer_name, r.name as raffle_name
        FROM payments p
        JOIN tickets t ON p.ticket_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN raffles r ON t.raffle_id = r.id
        WHERE r.vendor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$vendorId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success($payments, 'Pagos listados');
} catch (Exception $e) {
    Response::serverError('Error al listar pagos');
}
