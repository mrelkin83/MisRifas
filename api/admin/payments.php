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
                p.amount,
                p.created_at as reported_at,
                TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) as age_minutes,
                t.reserved_at,
                r.name as raffle_name,
                r.id as raffle_id,
                r.ticket_price,
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

        // Monto EXACTO de la orden (§6): precio × boletos de la misma
        // reservation + su sufijo — es el número que el vendedor debe ver en
        // su app de banco para identificar el pago.
        $suffixStmt = $db->prepare("
            SELECT payment_suffix, COUNT(*) AS n FROM numero_reservas
            WHERE reservation_id = ? GROUP BY payment_suffix LIMIT 1
        ");
        foreach ($tickets as &$row) {
            $gw = json_decode($row['payment_gateway_response'] ?? '{}', true);
            $row['proof_url'] = $gw['proof_url'] ?? null;
            // §16: comprobantes nuevos se sirven por controlador con token;
            // los legados conservan su ruta directa.
            $row['proof_link'] = !empty($gw['proof_token'])
                ? '/api/vendor/proof.php?t=' . $gw['proof_token']
                : (!empty($gw['proof_url']) ? '/public' . $gw['proof_url'] : null);
            $row['flags'] = is_array($gw['flags'] ?? null) ? $gw['flags'] : [];
            $row['order_amount'] = null;
            if (!empty($gw['reservation_id'])) {
                $suffixStmt->execute([$gw['reservation_id']]);
                if ($ord = $suffixStmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['order_amount'] = (float)$row['ticket_price'] * (int)$ord['n'] + (int)($ord['payment_suffix'] ?? 0);
                }
            }
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

        // §10: AMBAS vías (WhatsApp y panel) usan el MISMO servicio de
        // dominio; aquí solo cambia source='dashboard'.
        require_once __DIR__ . '/../../api/services/PaymentReview.php';

        if ($action === 'approve') {
            $r = PaymentReview::aprobar($db, $ticketId, (int)$adminUser['id'], 'dashboard');
            if (!$r['ok']) {
                $code = $r['estado'] === 'sin_permiso' ? 403 : ($r['estado'] === 'sin_pago' ? 400 : 409);
                Response::error($r['mensaje'], null, $code);
            }
            Logger::activity('payment_manual_approved', $adminUser['id'], ['ticket_id' => $ticketId]);

            // §9.6: boleta por WhatsApp al comprador — después del commit,
            // best-effort (si el vendedor no tiene canal, queda descargable).
            require_once __DIR__ . '/../../api/services/Boleta.php';
            Boleta::enviarPorWhatsApp($db, (int)$ticketId, (int)$adminUser['id']);

            Response::success(['message' => $r['mensaje']]);
        }

        if ($action === 'reject') {
            // §10.2: motivo OBLIGATORIO de la lista corta.
            $reason = (string)($input['reason'] ?? '');
            $r = PaymentReview::rechazar($db, $ticketId, (int)$adminUser['id'], 'dashboard', $reason);
            if (!$r['ok']) {
                $code = $r['estado'] === 'sin_permiso' ? 403 : ($r['estado'] === 'motivo_invalido' ? 422 : 409);
                Response::error($r['mensaje'], null, $code);
            }
            Logger::activity('payment_manual_rejected', $adminUser['id'], ['ticket_id' => $ticketId, 'motivo' => $reason]);
            Response::success(['message' => $r['mensaje']]);
        }
    }

    Response::error('Método no permitido', null, 405);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en gestión de pagos');
}
