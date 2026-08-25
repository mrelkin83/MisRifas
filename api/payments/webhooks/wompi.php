<?php
/**
 * Webhook: Wompi
 * POST /api/payments/webhooks/wompi.php
 *
 * ESTE ES EL CRÍTICO - Solo el webhook cambia estados
 * El frontend NUNCA confirma pagos
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
$wompiSecret = $config['payments']['wompi']['events_secret'] ?? '';

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!$payload || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Validar firma de Wompi (CRÍTICO para seguridad). Falla cerrado: si el
    // secreto no esta configurado, se rechaza el webhook en vez de aceptarlo
    // sin verificar (evita fraude de pago con payloads forjados).
    $signature = $_SERVER['HTTP_X_WOMPI_SIGNATURE'] ?? '';
    if (empty($wompiSecret)) {
        Logger::error('Wompi webhook: WOMPI_EVENTS_SECRET no configurado, rechazando');
        http_response_code(401);
        echo json_encode(['error' => 'Webhook not configured']);
        exit;
    }
    $expectedSignature = hash_hmac('sha256', $raw, $wompiSecret);
    if (!hash_equals($expectedSignature, $signature)) {
        Logger::error('Wompi webhook: firma inválida', [
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
        'wompi',
        $payload['event']['type'] ?? 'unknown',
        $payload['data']['transaction']['id'] ?? '',
        json_encode($payload),
        json_encode(['signature' => $signature, 'headers' => getallheaders() ?? []])
    ]);
    $webhookLogId = $db->lastInsertId();

    // ========================================
    // PROCESAR WEBHOOK SOLO SI ES UNA TRANSACCIÓN
    // ========================================
    $transaction = $payload['data']['transaction'] ?? [];
    $transactionId = $transaction['id'] ?? '';
    $status = strtoupper($transaction['status'] ?? '');

    // Solo procesamos payment_intents que existen
    $stmt = $db->prepare("
        SELECT pi.* FROM payment_intents pi
        WHERE pi.gateway_reference = ? AND pi.gateway = 'wompi'
    ");
    $stmt->execute([$transactionId]);
    $paymentIntent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paymentIntent) {
        Logger::warning('Wompi webhook: payment_intent no encontrado', [
            'transaction_id' => $transactionId,
            'webhook_log_id' => $webhookLogId
        ]);
        http_response_code(200); // Devolver 200 para evitar reintentos
        echo json_encode(['received' => true, 'note' => 'payment_intent no encontrado']);
        exit;
    }

    // Verificar si ya fue procesado (IDEMPOTENCIA)
    if ($paymentIntent['status'] !== 'PENDING') {
        Logger::info('Wompi webhook: ya procesado', [
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
    $db->beginTransaction();

    try {
        if ($status === 'APPROVED' || $status === 'SUCCESS') {
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
                $transaction['id'],
                json_encode($transaction),
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

            // 3. Marcar tickets como pagados. Cada raffle ya tiene una fila
            // por numero desde su creacion (TicketRepository::generateTickets()),
            // asi que esto es un UPDATE, no un INSERT - insertar chocaba
            // siempre con el UNIQUE (raffle_id, ticket_number) y ademas
            // omitia `opportunities` (NOT NULL sin default), asi que la
            // transaccion completa hacia rollback en todo pago aprobado: el
            // comprador quedaba cobrado por Wompi pero sin boleto localmente.
            $stmt = $db->prepare("
                UPDATE tickets t
                INNER JOIN numero_reservas nr
                    ON t.raffle_id = nr.raffle_id AND t.ticket_number = nr.numero
                SET t.status = 'paid', t.user_id = nr.user_id, t.paid_at = NOW()
                WHERE nr.payment_intent_id = ? AND nr.estado = 'PAGADO'
            ");
            $stmt->execute([$paymentIntent['id']]);

            // 4. Notificar (WhatsApp + Email)
            // Aquí se enviarían las notificaciones automáticas
            Logger::activity('payment_approved_wompi', $paymentIntent['user_id'], [
                'payment_intent_id' => $paymentIntent['id'],
                'raffle_id' => $paymentIntent['raffle_id'],
                'transaction_id' => $transactionId,
                'amount' => $paymentIntent['amount']
            ]);

        } elseif ($status === 'REJECTED' || $status === 'FAILED' || $status === 'DECLINED') {
            // ========================================
            // PAGO RECHAZADO: RESERVADO → DISPONIBLE
            // ========================================

            // 1. Actualizar payment_intent
            $stmt = $db->prepare("
                UPDATE payment_intents
                SET status = 'REJECTED',
                    gateway_response_code = ?,
                    gateway_response = ?,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $transaction['status'],
                json_encode($transaction),
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

            Logger::activity('payment_rejected_wompi', $paymentIntent['user_id'], [
                'payment_intent_id' => $paymentIntent['id'],
                'raffle_id' => $paymentIntent['raffle_id'],
                'transaction_id' => $transactionId,
                'reason' => $transaction['status']
            ]);

        } else {
            Logger::warning('Wompi webhook: status no manejado', [
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

        Logger::error('Wompi webhook: error procesando', [
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
