<?php
/**
 * API: Pagos Manuales (Admin Vendedor)
 * GET /api/admin/payments.php   - Listar boletos "verifying" del vendedor
 * POST /api/admin/payments.php  - Aprobar o rechazar un pago manual
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Listar tickets con pago pendiente de validación manual de las rifas del vendedor
        $stmt = $db->prepare("
            SELECT
                t.id as ticket_id,
                t.ticket_number,
                t.status,
                t.payment_method,
                t.payment_status,
                t.payment_proof_url,
                t.reserved_at,
                r.name as raffle_name,
                r.id as raffle_id,
                u.full_name as buyer_name,
                u.phone as buyer_phone
            FROM tickets t
            JOIN raffles r ON t.raffle_id = r.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE r.created_by = ?
              AND t.payment_method IN ('transferencia', 'efectivo', 'contraentrega')
              AND t.payment_status IN ('pending', 'verifying')
            ORDER BY t.reserved_at DESC
        ");
        $stmt->execute([$adminUser['id']]);
        $tickets = $stmt->fetchAll();

        Response::success($tickets);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action    = $input['action'] ?? '';
        $ticketId  = (int)($input['ticket_id'] ?? 0);

        if (!$ticketId || !in_array($action, ['approve', 'reject'])) {
            Response::error('Datos inválidos', null, 400);
        }

        // Verificar que el ticket pertenece a una rifa del vendedor
        $stmt = $db->prepare("
            SELECT t.id, t.status FROM tickets t
            JOIN raffles r ON t.raffle_id = r.id
            WHERE t.id = ? AND r.created_by = ?
        ");
        $stmt->execute([$ticketId, $adminUser['id']]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Response::error('No tienes permiso sobre este boleto', null, 403);
        }

        if ($action === 'approve') {
            $stmt = $db->prepare("
                UPDATE tickets
                SET status = 'paid', payment_status = 'approved', paid_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
            Logger::activity('payment_manual_approved', $adminUser['id'], ['ticket_id' => $ticketId]);
            Response::success(['message' => 'Pago aprobado. El boleto ahora está vendido.']);
        }

        if ($action === 'reject') {
            $stmt = $db->prepare("
                UPDATE tickets
                SET status = 'available', payment_status = 'rejected', user_id = NULL, reserved_at = NULL, reserved_until = NULL
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
            Logger::activity('payment_manual_rejected', $adminUser['id'], ['ticket_id' => $ticketId]);
            Response::success(['message' => 'Pago rechazado. El boleto volvió a estar disponible.']);
        }
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en gestión de pagos');
}
