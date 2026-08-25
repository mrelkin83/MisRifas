<?php
/**
 * Webhook: Mercado Pago
 * POST /api/payments/webhooks/mercadopago.php
 *
 * ESTE ES EL CRÍTICO - Solo el webhook cambia estados
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$config = require __DIR__ . '/../../../config/app.php';
$mpSecret = $config['payments']['mercadopago']['webhook_secret'] ?? '';

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!$payload || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Validar firma de Mercado Pago (CRÍTICO para seguridad). Falla cerrado:
    // sin secreto configurado se rechaza, no se acepta sin verificar.
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? $_SERVER['HTTP_X_PAYMENT_NOTIFICATION_SIGNATURE'] ?? '';
    if (empty($mpSecret)) {
        Logger::error('Mercadopago webhook: MERCADOPAGO_WEBHOOK_SECRET no configurado, rechazando');
        http_response_code(401);
        echo json_encode(['error' => 'Webhook not configured']);
        exit;
    }
    $expectedSignature = hash_hmac('sha256', $raw, $mpSecret);
    if (!hash_equals($expectedSignature, $signature)) {
        Logger::error('Mercadopago webhook: firma inválida', [
            'signature' => $signature,
            'expected' => $expectedSignature
        ]);
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // ========================================
    // REGISTRAR WEBHOOK EN LOGS
    // ========================================
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        INSERT INTO webhook_logs
        (gateway, event_type, transaction_id, payload, headers, received_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        'mercadopago',
        $payload['type'] ?? 'unknown',
        $payload['data']['id'] ?? '',
        json_encode($payload),
        json_encode(['signature' => $signature, 'headers' => getallheaders() ?? []])
    ]);
    $webhookLogId = $db->lastInsertId();

    // ========================================
    // PROCESAR WEBHOOK
    // ========================================
    $paymentId = $payload['data']['id'] ?? '';
    $paymentReference = $payload['data']['external_reference'] ?? '';

    if ($paymentReference === '') {
        Logger::warning('Mercadopago webhook: sin external_reference', [
            'payment_id' => $paymentId,
            'webhook_log_id' => $webhookLogId
        ]);
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'no_reference']);
        exit;
    }

    // Buscar payment_intent por external_reference
    $stmt = $db->prepare("
        SELECT pi.* FROM payment_intents pi
        WHERE pi.id = ? AND pi.gateway = 'mercadopago'
    ");
    $stmt->execute([$paymentReference]);
    $paymentIntent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paymentIntent) {
        Logger::warning('Mercadopago webhook: payment_intent no encontrado', [
            'payment_reference' => $paymentReference,
            'webhook_log_id' => $webhookLogId
        ]);
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'payment_intent_not_found']);
        exit;
    }

    // Verificar si ya fue procesado (IDEMPOTENCIA)
    if ($paymentIntent['status'] !== 'PENDING') {
        Logger::info('Mercadopago webhook: ya procesado', [
            'payment_intent_id' => $paymentIntent['id'],
            'current_status' => $paymentIntent['status']
        ]);
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'already_processed']);
        exit;
    }

    // ========================================
    // CAMBIAR ESTADOS SEGÚN STATUS
    // ========================================
    $status = strtoupper($payload['type'] ?? '');

    $db->beginTransaction();

    try {
        if ($status === 'APPROVED' || $status === 'AUTHORIZED') {
            // ========================================
            // PAGO APROBADO: RESERVADO → PAGADO
            // ========================================

            // 1. Actualizar payment_intent
            $stmt = $db->prepare("
                UPDATE payment_intents
                SET status = 'APPROVED',
                    gateway_transaction_id = ?,
                    gateway_response = ?,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $paymentId,
                json_encode($payload),
                $paymentIntent['id']
            ]);

            // 2. Cambiar números a PAGADO
            $stmt = $db->prepare("
                UPDATE numero_reservas
                SET estado = 'PAGADO',
                    updated_at = NOW()
                WHERE payment_intent_id = ? AND estado = 'RESERVADO'
            ");
            $stmt->execute([$paymentIntent['id']]);

            // 3. Generar tickets
            $stmt = $db->prepare("
                INSERT INTO tickets (raffle_id, user_id, ticket_number, status, created_at)
                SELECT nr.raffle_id, nr.user_id, nr.numero, 'paid', NOW()
                FROM numero_reservas nr
                WHERE nr.payment_intent_id = ? AND nr.estado = 'PAGADO'
            ");
            $stmt->execute([$paymentIntent['id']]);

            // 4. Notificar (WhatsApp + Email)
            Logger::activity('payment_approved_mercadopago', $paymentIntent['user_id'], [
                'payment_intent_id' => $paymentIntent['id'],
                'raffle_id' => $paymentIntent['raffle_id'],
                'payment_id' => $paymentId,
                'amount' => $paymentIntent['amount']
            ]);

        } elseif ($status === 'REJECTED' || $status === 'CANCELLED') {
            // ========================================
            // PAGO RECHAZADO: RESERVADO → DISPONIBLE
            // ========================================

            // 1. Actualizar payment_intent
            $stmt = $db->prepare("
                UPDATE payment_intents
                SET status = 'REJECTED',
                    gateway_response = ?,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($payload),
                $paymentIntent['id']
            ]);

            // 2. Cambiar números a DISPONIBLE
            $stmt = $db->prepare("
                UPDATE numero_reservas
                SET estado = 'DISPONIBLE',
                    user_id = NULL,
                    reservation_id = NULL,
                    reserved_at = NULL,
                    expires_at = NULL,
                    payment_intent_id = NULL,
                    updated_at = NOW()
                WHERE payment_intent_id = ? AND estado = 'RESERVADO'
            ");
            $stmt->execute([$paymentIntent['id']]);

            Logger::activity('payment_rejected_mercadopago', $paymentIntent['user_id'], [
                'payment_intent_id' => $paymentIntent['id'],
                'raffle_id' => $paymentIntent['raffle_id'],
                'payment_id' => $paymentId,
                'reason' => $status
            ]);

        } else {
            Logger::warning('Mercadopago webhook: status no manejado', [
                'status' => $status,
                'payment_intent_id' => $paymentIntent['id']
            ]);
        }

        // 5. Marcar webhook como procesado
        $stmt = $db->prepare("
            UPDATE webhook_logs
            SET processed = true, processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$webhookLogId]);

        $db->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'processed' => true]);

    } catch (Exception $e) {
        $db->rollBack();

        $stmt = $db->prepare("
            UPDATE webhook_logs
            SET error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $webhookLogId]);

        Logger::error('Mercadopago webhook: error procesando', [
            'payment_intent_id' => $paymentIntent['id'] ?? '',
            'error' => $e->getMessage()
        ]);

        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} catch (Exception $e) {
    Logger::exception($e);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
