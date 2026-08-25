<?php
/**
 * API: Pagos Manuales (Admin Vendedor)
 * GET /api/admin/payments.php   - Listar boletos "verifying" del vendedor
 * POST /api/admin/payments.php  - Aprobar o rechazar un pago manual
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {     header('Access-Control-Allow-Origin: *');
 http_response_code(200); exit; }

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Listar tickets con comprobante de pago pendiente de validación manual
        // de las rifas del vendedor. Los datos del pago viven en `payments`
        // (payment_method/proof), no en `tickets` (esa tabla solo tiene status).
        $stmt = $db->prepare("
            SELECT
                t.id as ticket_id,
                t.ticket_number,
                t.status,
                p.id as payment_id,
                p.payment_method,
                p.transaction_status as payment_status,
                p.payment_gateway_response,
                p.created_at as reported_at,
                t.reserved_at,
                r.name as raffle_name,
                r.id as raffle_id,
                u.name as buyer_name,
                u.phone_whatsapp as buyer_phone
            FROM payments p
            JOIN tickets t ON t.id = p.ticket_id
            JOIN raffles r ON t.raffle_id = r.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE COALESCE(r.vendor_id, r.created_by) = ?
              AND p.transaction_status = 'pending'
              AND t.status = 'reserved'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$adminUser['id']]);
        $tickets = $stmt->fetchAll();

        foreach ($tickets as &$row) {
            $gw = json_decode($row['payment_gateway_response'] ?? '{}', true);
            $row['proof_url'] = $gw['proof_url'] ?? null;
            unset($row['payment_gateway_response']);
        }
        unset($row);

        Response::success($tickets);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action    = $input['action'] ?? '';
        $ticketId  = (int)($input['ticket_id'] ?? 0);

        if (!$ticketId || !in_array($action, ['approve', 'reject'])) {
            Response::error('Datos inválidos', null, 400);
        }

        // Verificar que el ticket pertenece a una rifa del vendedor y que
        // tiene un pago manual pendiente real (evita aprobar tickets ajenos
        // o sin comprobante reportado).
        $stmt = $db->prepare("
            SELECT t.id, t.status, p.id as payment_id
            FROM tickets t
            JOIN raffles r ON t.raffle_id = r.id
            LEFT JOIN payments p ON p.ticket_id = t.id AND p.transaction_status = 'pending'
            WHERE t.id = ? AND COALESCE(r.vendor_id, r.created_by) = ?
        ");
        $stmt->execute([$ticketId, $adminUser['id']]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            Response::error('No tienes permiso sobre este boleto', null, 403);
        }

        if (!$ticket['payment_id']) {
            Response::error('Este boleto no tiene un pago pendiente de revision', null, 400);
        }

        if ($action === 'approve') {
            // Si un cron libero la reserva entre el chequeo de arriba y este
            // UPDATE (por ejemplo, expiro justo antes de que el admin le
            // diera aprobar), el WHERE status='reserved' no afecta ninguna
            // fila y antes se respondia "aprobado" igual - el pago quedaba
            // marcado completed pero el ticket nunca paid (silencioso).
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE tickets SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'reserved'");
                $stmt->execute([$ticketId]);
                if ($stmt->rowCount() === 0) {
                    $db->rollBack();
                    Response::error('El boleto ya no está reservado (pudo expirar); no se aprobó el pago', null, 409);
                }

                $stmt = $db->prepare("UPDATE payments SET transaction_status = 'completed', verified_at = NOW() WHERE id = ?");
                $stmt->execute([$ticket['payment_id']]);

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

            Logger::activity('payment_manual_approved', $adminUser['id'], ['ticket_id' => $ticketId, 'payment_id' => $ticket['payment_id']]);
            Response::success(['message' => 'Pago aprobado. El boleto ahora está vendido.']);
        }

        if ($action === 'reject') {
            $stmt = $db->prepare("UPDATE payments SET transaction_status = 'failed' WHERE id = ?");
            $stmt->execute([$ticket['payment_id']]);

            $stmt = $db->prepare("
                UPDATE tickets
                SET status = 'available', user_id = NULL, reserved_at = NULL, reserved_until = NULL
                WHERE id = ?
            ");
            $stmt->execute([$ticketId]);
            Logger::activity('payment_manual_rejected', $adminUser['id'], ['ticket_id' => $ticketId, 'payment_id' => $ticket['payment_id']]);
            Response::success(['message' => 'Pago rechazado. El boleto volvió a estar disponible.']);
        }
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en gestión de pagos');
}
