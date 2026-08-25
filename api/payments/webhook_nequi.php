<?php
/**
 * Webhook: Pago Nequi/PSE
 * POST /api/payments/webhook_nequi.php
 * 
 * Recibe notificaciones de pago de Nequi (vía Bancolombia API)
 * y actualiza el estado del boleto automáticamente.
 * 
 * NOTA: Para activar, el vendedor debe registrar esta URL
 * en su dashboard de Nequi Business/Bancolombia Developers
 * como Webhook URL de pagos.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/services/WhatsAppService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    $nequiSignature = $_SERVER['HTTP_X_NEQUI_SIGNATURE'] ?? '';
    $nequiSecret = getenv('NEQUI_WEBHOOK_SECRET') ?: '';

    // Falla cerrado: sin secreto configurado se rechaza el webhook. La
    // referencia "TICKET_{raffle_id}_{ticket_id}" es un ID secuencial
    // adivinable, no un secreto - la firma es la unica proteccion real.
    if (empty($nequiSecret)) {
        error_log('[NEQUI] WEBHOOK_SECRET no configurado - rechazando webhook');
        http_response_code(401);
        echo json_encode(['error' => 'Webhook not configured']);
        exit;
    }
    if (empty($nequiSignature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Signature required']);
        exit;
    }
    $expectedSignature = hash_hmac('sha256', $raw, $nequiSecret);
    if (!hash_equals($expectedSignature, $nequiSignature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    Logger::activity('nequi_webhook_received', 0, ['reference' => $payload['reference'] ?? 'unknown', 'status' => $payload['status'] ?? 'unknown']);

    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    // ----------------------------------------------------------------
    // ESTRUCTURA DEL WEBHOOK NEQUI (Bancolombia API sandbox/prod):
    // En producción real, el cuerpo específico depende del proveedor.
    // Estructura esperada genérica para demo:
    // {
    //   "reference": "TICKET_<raffle_id>_<ticket_id>",  <- definida al crear el cobro
    //   "status": "PAID"  | "DECLINED" | "PENDING",
    //   "amount": 50000,
    //   "payer_phone": "3001234567"
    // }
    // ----------------------------------------------------------------

    $reference = $payload['reference'] ?? '';
    $status    = strtoupper($payload['status'] ?? '');

    // La reference que generamos al reservar el boleto es "TICKET_{raffle_id}_{ticket_id}"
    if (!preg_match('/^TICKET_(\d+)_(\d+)$/', $reference, $matches)) {
        // Intentamos con el patrón de Wompi
        http_response_code(200); // Respondemos 200 para no generar retries innecesarios
        echo json_encode(['received' => true, 'note' => 'reference format not matched']);
        exit;
    }

    $raffleId = (int)$matches[1];
    $ticketId = (int)$matches[2];

    if ($status === 'PAID' || $status === 'APPROVED') {
        // Marcar el boleto como pagado
        $stmt = $db->prepare("UPDATE tickets SET status = 'paid', paid_at = NOW() WHERE id = ? AND raffle_id = ? AND status = 'reserved'");
        $stmt->execute([$ticketId, $raffleId]);

        if ($stmt->rowCount() > 0) {
            Logger::activity('ticket_auto_paid_nequi', 0, ['ticket_id' => $ticketId, 'raffle_id' => $raffleId]);

            // Disparar notificación WhatsApp al comprador
            $infoStmt = $db->prepare("
                SELECT t.ticket_number, r.name as raffle_name, r.ticket_price, r.draw_date, r.created_by,
                       u.full_name, u.phone
                FROM tickets t
                JOIN raffles r ON t.raffle_id = r.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.id = ?
            ");
            $infoStmt->execute([$ticketId]);
            $info = $infoStmt->fetch();

            if ($info && $info['phone']) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $ticketUrl = $protocol . '://' . $host . '/MisRifas/public/raffle.php?id=' . intval($raffleId);
                WhatsAppService::notifyReservation(
                    $info['created_by'],
                    $info['raffle_name'],
                    $info['ticket_number'],
                    $info['full_name'] ?? 'Comprador',
                    $info['phone'],
                    date('Y-m-d', strtotime($info['draw_date'])),
                    $info['ticket_price'],
                    $ticketUrl
                );
            }
        }
    } elseif ($status === 'DECLINED' || $status === 'FAILED') {
        // Liberar el boleto
        $stmt = $db->prepare("UPDATE tickets SET status = 'available', user_id = NULL, reserved_at = NULL, reserved_until = NULL WHERE id = ? AND raffle_id = ?");
        $stmt->execute([$ticketId, $raffleId]);
        Logger::activity('ticket_payment_declined_nequi', 0, ['ticket_id' => $ticketId]);
    }

    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (Exception $e) {
    Logger::exception($e);
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
