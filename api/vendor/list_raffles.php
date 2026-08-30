<?php
/**
 * API: Listar Rifas del Vendedor
 * GET /api/vendor/list_raffles.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';

$authVendor = Auth::requireVendor();

$db = Database::getInstance()->getConnection();
// Identidad del token (no de la sesión) para funcionar igual vía Bearer.
$vendorId = $authVendor['id'];

try {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.city, r.department, r.status, r.draw_date, r.ticket_price, r.image_url,
               r.total_tickets, (SELECT COUNT(*) FROM tickets t WHERE t.raffle_id = r.id AND t.status = 'paid') as sold_tickets
        FROM raffles r
        WHERE r.vendor_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$vendorId]);
    $raffles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success($raffles, 'Rifas listadas');
} catch (Exception $e) {
    Response::serverError('Error al listar rifas');
}
