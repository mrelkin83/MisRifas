<?php
/**
 * API: Comisiones del Admin
 * GET /api/admin/commissions.php
 * POST /api/admin/commissions.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($adminUser['role'] !== 'super_admin') {
            Response::error('No tienes permisos', null, 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action']) && $input['action'] === 'mark_paid' && !empty($input['raffle_id'])) {
            $stmt = $db->prepare("UPDATE raffles SET commission_paid = 1 WHERE id = ?");
            $stmt->execute([$input['raffle_id']]);
            
            Logger::activity('commission_marked_paid', $adminUser['id'], ['raffle_id' => $input['raffle_id']]);
            Response::success(['message' => 'Comisión marcada como pagada']);
            exit;
        }
        
        if (isset($input['action']) && $input['action'] === 'update_amount' && !empty($input['raffle_id']) && isset($input['amount'])) {
            $stmt = $db->prepare("UPDATE raffles SET commission_amount = ? WHERE id = ?");
            $stmt->execute([floatval($input['amount']), $input['raffle_id']]);
            
            Logger::activity('commission_amount_updated', $adminUser['id'], ['raffle_id' => $input['raffle_id'], 'amount' => $input['amount']]);
            Response::success(['message' => 'Monto de comisión actualizado']);
            exit;
        }
        
        Response::error('Acción inválida o faltan datos', null, 400);
    }
    
    // Si es super_admin ve todas, sino solo las de sus rifas
    if ($adminUser['role'] === 'super_admin') {
        $stmt = $db->query("
            SELECT r.id as raffle_id, r.name as raffle_name, r.commission_amount, r.commission_due_date, r.commission_paid,
                   u.full_name as creator_name,
                   (SELECT COUNT(*) FROM tickets t WHERE t.raffle_id = r.id AND t.status = 'paid') as total_sales
            FROM raffles r
            JOIN admin_users u ON r.created_by = u.id
            WHERE r.commission_amount > 0
            ORDER BY r.commission_due_date ASC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT r.id as raffle_id, r.name as raffle_name, r.commission_amount, r.commission_due_date, r.commission_paid,
                   (SELECT COUNT(*) FROM tickets t WHERE t.raffle_id = r.id AND t.status = 'paid') as total_sales
            FROM raffles r
            WHERE r.created_by = ? AND r.commission_amount > 0
            ORDER BY r.commission_due_date ASC
        ");
        $stmt->execute([$adminUser['id']]);
    }
    
    $commissions = $stmt->fetchAll();
    Response::success($commissions);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar comisiones');
}
