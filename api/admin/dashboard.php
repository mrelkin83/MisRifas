<?php
/**
 * API: Dashboard Admin
 * GET /api/admin/dashboard.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', null, 405);
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    $stats = [];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM raffles WHERE status = 'active' AND created_by = ?");
    $stmt->execute([$adminUser['id']]);
    $stats['active_raffles'] = (int)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM raffles WHERE status = 'draft' AND created_by = ?");
    $stmt->execute([$adminUser['id']]);
    $stats['draft_raffles'] = (int)$stmt->fetch()['total'];

    // Estas 4 no filtraban por vendor (a diferencia de active_raffles/
    // draft_raffles/total_sales arriba) - cualquier vendor, incluido uno
    // recien auto-registrado, veia ventas/compradores/reservas/comisiones
    // vencidas de TODA la plataforma (volumen de la competencia). super_admin
    // si ve el total de la plataforma, a proposito.
    $esSuperAdmin = ($adminUser['role'] ?? '') === 'super_admin';

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets t INNER JOIN raffles r ON t.raffle_id = r.id
                       WHERE t.status = 'paid'" . ($esSuperAdmin ? '' : ' AND r.created_by = ?'));
    $esSuperAdmin ? $stmt->execute() : $stmt->execute([$adminUser['id']]);
    $stats['tickets_sold'] = (int)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(DISTINCT t.user_id) as total FROM tickets t INNER JOIN raffles r ON t.raffle_id = r.id
                       WHERE t.status = 'paid'" . ($esSuperAdmin ? '' : ' AND r.created_by = ?'));
    $esSuperAdmin ? $stmt->execute() : $stmt->execute([$adminUser['id']]);
    $stats['total_buyers'] = (int)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(r.ticket_price), 0) as total
                       FROM tickets t
                       INNER JOIN raffles r ON t.raffle_id = r.id
                       WHERE t.status = 'paid' AND r.created_by = ?");
    $stmt->execute([$adminUser['id']]);
    $stats['total_sales'] = (float)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets t INNER JOIN raffles r ON t.raffle_id = r.id
                       WHERE t.status = 'reserved' AND t.reserved_until > NOW()" . ($esSuperAdmin ? '' : ' AND r.created_by = ?'));
    $esSuperAdmin ? $stmt->execute() : $stmt->execute([$adminUser['id']]);
    $stats['active_reservations'] = (int)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM raffles r
                       WHERE r.commission_paid = 0 AND r.commission_due_date <= NOW() AND r.status = 'active'" . ($esSuperAdmin ? '' : ' AND r.created_by = ?'));
    $esSuperAdmin ? $stmt->execute() : $stmt->execute([$adminUser['id']]);
    $stats['overdue_commissions'] = (int)$stmt->fetch()['total'];

    Response::success($stats);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar el dashboard');
}
