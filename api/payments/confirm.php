<?php

/**
 * API: Confirmar Pago (Wompi Webhook)
 *
 * POST /api/payments/confirm.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // GET: El Frontend consulta el estado
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $reference = $_GET['reference'] ?? '';
        if (empty($reference)) {
            http_response_code(400);
            echo json_encode(['error' => 'Referencia requerida']);
            exit;
        }

        $stmt = $db->prepare("SELECT transaction_status, ticket_id FROM payments WHERE transaction_reference = ?");
        $stmt->execute([$reference]);
        $payment = $stmt->fetch();

        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no encontrado']);
            exit;
        }

        echo json_encode(['payment' => $payment]);
        exit;
    }

    // POST: Wompi Events
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payloadRaw = file_get_contents('php://input');
        $input = json_decode($payloadRaw, true);

        if (!$input || !isset($input['event'], $input['data']['transaction'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload invalido']);
            exit;
        }

        if ($input['event'] !== 'transaction.updated') {
            http_response_code(200);
            echo json_encode(['ok' => 'Evento ignorado']);
            exit;
        }

        $transaction = $input['data']['transaction'];
        $reference = $transaction['reference'];
        $amountInCents = $transaction['amount_in_cents'];
        $status = $transaction['status'];
        $wompiSignature = $input['signature']['checksum'] ?? '';

        // Buscar el pago y el creador de la rifa para obtener el Events Secret (o public local)
        $stmtPayment = $db->prepare("
            SELECT p.id, p.ticket_id, p.amount, p.transaction_status, r.created_by 
            FROM payments p
            JOIN raffles r ON p.raffle_id = r.id
            WHERE p.transaction_reference = ?
        ");
        $stmtPayment->execute([$reference]);
        $paymentRow = $stmtPayment->fetch();

        if (!$paymentRow) {
            http_response_code(404);
            echo json_encode(['error' => 'Pago no existe en el sistema']);
            exit;
        }

        if ($paymentRow['transaction_status'] === 'completed') {
            http_response_code(200);
            echo json_encode(['ok' => 'Pago ya fue procesado con exito anteriormente']);
            exit;
        }

        // Obtener config de admin
        $stmtAdmin = $db->prepare("SELECT wompi_config FROM admin_users WHERE id = ?");
        $stmtAdmin->execute([$paymentRow['created_by']]);
        $adminOptions = $stmtAdmin->fetch();
        
        $wompiConfig = $adminOptions ? json_decode($adminOptions['wompi_config'], true) : [];
        $eventsSecret = $wompiConfig['events_secret'] ?? getenv('WOMPI_EVENTS_SECRET') ?? '';

        if (empty($eventsSecret)) {
            error_log('[WOMPI] CRITICAL: WOMPI_EVENTS_SECRET not configured');
            http_response_code(500);
            echo json_encode(['error' => 'Gateway not configured']);
            exit;
        }

        if (empty($wompiSignature)) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing signature']);
            exit;
        }

        $timestamp = $input['timestamp'];
        $properties = $transaction['id'] . $transaction['status'] . $transaction['amount_in_cents'] . $timestamp . $eventsSecret;
        $calculatedChecksum = hash('sha256', $properties);

        if (!hash_equals($calculatedChecksum, $wompiSignature)) {
            error_log('[WOMPI] Signature mismatch. Expected: ' . $calculatedChecksum);
            http_response_code(400);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        // Validar valor pagado
        $amountInPesos = $amountInCents / 100;
        if ($amountInPesos < $paymentRow['amount']) {
            http_response_code(400);
            echo json_encode(['error' => 'Valor insuficiente pagado']);
            exit;
        }

        if ($status === 'APPROVED') {
            $db->beginTransaction();
            try {
                // Actualizar Pago
                $stmtUpdPayment = $db->prepare("
                    UPDATE payments 
                    SET transaction_status = 'completed', payment_gateway_response = ?, verified_at = NOW() 
                    WHERE id = ?
                ");
                $stmtUpdPayment->execute([json_encode($transaction), $paymentRow['id']]);

                // Actualizar Ticket
                $stmtUpdTicket = $db->prepare("
                    UPDATE tickets 
                    SET status = 'paid', paid_at = NOW(), payment_id = ? 
                    WHERE id = ?
                ");
                $stmtUpdTicket->execute([$paymentRow['id'], $paymentRow['ticket_id']]);

                $db->commit();
                http_response_code(200);
                echo json_encode(['ok' => 'Pago procesado satisfactoriamente']);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Error de concurrencia BD']);
            }
        } else if ($status === 'DECLINED' || $status === 'ERROR') {
            // Actualizar a fallido y liberar
            $db->beginTransaction();
            try {
                $stmtUpdPayment = $db->prepare("
                    UPDATE payments 
                    SET transaction_status = 'failed', payment_gateway_response = ?
                    WHERE id = ?
                ");
                $stmtUpdPayment->execute([json_encode($transaction), $paymentRow['id']]);

                // Liberar el ticket si estaba reservado
                $stmtUpdTicket = $db->prepare("
                    UPDATE tickets 
                    SET status = 'available', user_id = NULL, reserved_at = NULL, reserved_until = NULL 
                    WHERE id = ? AND status != 'paid'
                ");
                $stmtUpdTicket->execute([$paymentRow['ticket_id']]);
                
                $db->commit();
                http_response_code(200);
                echo json_encode(['ok' => 'Pago rechazado, boleto liberado']);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Error de BD liberando boleto']);
            }
        } else {
            http_response_code(200);
            echo json_encode(['ok' => 'Estado en pendiente']);
        }
    } else {
        http_response_code(405);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error Crítico del Sistema']);
}
