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
require_once __DIR__ . '/../../api/services/TicketStateMachine.php';

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
              AND t.status IN ('reserved', 'pending_review')
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
            $db->beginTransaction();
            try {
                // La fila se relee CON lock dentro de la transición. Boletos
                // que aún estén en 'reserved' (comprobantes anteriores a la
                // máquina de estados) pasan primero por pending_review para
                // que la bitácora refleje el flujo real.
                $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
                $stmt->execute([$ticketId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || !in_array($row['status'], ['reserved', 'pending_review'], true)) {
                    $db->rollBack();
                    Response::error('El boleto ya no está en revisión (pudo expirar); no se aprobó el pago', null, 409);
                }
                if ($row['status'] === 'reserved') {
                    $row = TicketStateMachine::apply($db, $row, 'pending_review', [
                        'actor' => 'system', 'source' => 'dashboard',
                        'reason' => 'normalización: comprobante previo a la máquina de estados',
                    ]);
                }
                TicketStateMachine::apply($db, $row, 'paid', [
                    'actor' => 'vendor', 'source' => 'dashboard', 'actor_id' => (int)$adminUser['id'],
                    'reason' => 'pago confirmado por el vendedor',
                    'detail' => ['payment_id' => (int)$ticket['payment_id']],
                    'fields' => ['paid_at' => date('Y-m-d H:i:s'), 'payment_id' => (int)$ticket['payment_id']],
                ]);

                $stmt = $db->prepare("UPDATE payments SET transaction_status = 'completed', verified_at = NOW() WHERE id = ?");
                $stmt->execute([$ticket['payment_id']]);

                $db->commit();
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            Logger::activity('payment_manual_approved', $adminUser['id'], ['ticket_id' => $ticketId, 'payment_id' => $ticket['payment_id']]);

            // §9.6: boleta por WhatsApp al comprador — después del commit,
            // best-effort (si el vendedor no tiene canal, queda descargable).
            require_once __DIR__ . '/../../api/services/Boleta.php';
            Boleta::enviarPorWhatsApp($db, (int)$ticketId, (int)$adminUser['id']);

            Response::success(['message' => 'Pago aprobado. El boleto ahora está vendido.']);
        }

        if ($action === 'reject') {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
                $stmt->execute([$ticketId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && in_array($row['status'], ['reserved', 'pending_review'], true)) {
                    if ($row['status'] === 'reserved') {
                        $row = TicketStateMachine::apply($db, $row, 'pending_review', [
                            'actor' => 'system', 'source' => 'dashboard',
                            'reason' => 'normalización: comprobante previo a la máquina de estados',
                        ]);
                    }
                    TicketStateMachine::apply($db, $row, 'available', [
                        'actor' => 'vendor', 'source' => 'dashboard', 'actor_id' => (int)$adminUser['id'],
                        'reason' => 'pago rechazado por el vendedor',
                        'detail' => ['payment_id' => (int)$ticket['payment_id']],
                    ]);
                }
                $stmt = $db->prepare("UPDATE payments SET transaction_status = 'failed' WHERE id = ?");
                $stmt->execute([$ticket['payment_id']]);
                $db->commit();
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
            Logger::activity('payment_manual_rejected', $adminUser['id'], ['ticket_id' => $ticketId, 'payment_id' => $ticket['payment_id']]);
            Response::success(['message' => 'Pago rechazado. El boleto volvió a estar disponible.']);
        }
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en gestión de pagos');
}
