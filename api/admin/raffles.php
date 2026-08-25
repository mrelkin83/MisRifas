<?php
/**
 * API: Rifas del Admin
 * GET /api/admin/raffles.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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
    
    // Si es super_admin ve todas las rifas, sino solo las suyas
    if ($adminUser['role'] === 'super_admin') {
        $stmt = $db->query("
            SELECT r.*, l.name as lottery_name, u.full_name as creator_name,
                (SELECT COUNT(*) FROM tickets WHERE raffle_id = r.id AND status = 'paid') as sold_tickets,
                (SELECT COUNT(*) FROM tickets WHERE raffle_id = r.id AND status = 'reserved') as reserved_tickets
            FROM raffles r
            LEFT JOIN lotteries l ON r.lottery_id = l.id
            LEFT JOIN admin_users u ON r.created_by = u.id
            ORDER BY r.created_at DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT r.*, l.name as lottery_name,
                (SELECT COUNT(*) FROM tickets WHERE raffle_id = r.id AND status = 'paid') as sold_tickets,
                (SELECT COUNT(*) FROM tickets WHERE raffle_id = r.id AND status = 'reserved') as reserved_tickets
            FROM raffles r
            LEFT JOIN lotteries l ON r.lottery_id = l.id
            WHERE r.created_by = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$adminUser['id']]);
    }
    $raffles = $stmt->fetchAll();

    Response::success($raffles);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar las rifas');
}
