<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', null, 405);
}

$db = null;

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('JSON invalido');
    }

    $ticketId = (int)($input['ticket_id'] ?? 0);
    $paymentMethod = strtolower(trim($input['payment_method'] ?? ''));
    $proof = $input['proof'] ?? null;

    if ($ticketId <= 0) {
        Response::error('ID de ticket invalido', null, 400);
    }

    $allowedMethods = ['nequi', 'bancolombia', 'daviplata', 'efecty', 'manual'];
    if (!in_array($paymentMethod, $allowedMethods)) {
        Response::error('Metodo de pago invalido. Permitidos: ' . implode(', ', $allowedMethods), null, 400);
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT t.*, r.ticket_price, r.title as raffle_title
        FROM tickets t
        INNER JOIN raffles r ON t.raffle_id = r.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        Response::error('Ticket no encontrado', null, 404);
    }

    if ($ticket['status'] !== 'reserved') {
        Response::error('El ticket no esta reservado o ya fue pagado (estado: ' . $ticket['status'] . ')');
    }

    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE tickets SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'reserved'");
    $stmt->execute([$ticketId]);

    if ($stmt->rowCount() === 0) {
        $db->rollBack();
        Response::error('El ticket ya no esta disponible');
    }

    $proofUrl = null;
    if ($proof && strpos($proof, 'data:image') === 0) {
        preg_match('/data:image\/(.*?);base64,/', $proof, $matches);
        $imageType = $matches[1] ?? 'png';
        $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $proof));

        if ($imageData) {
            $filename = 'payment_proof_' . $ticketId . '_' . time() . '.' . $imageType;
            $uploadDir = __DIR__ . '/../../uploads/payment_proofs/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (file_put_contents($uploadDir . $filename, $imageData) !== false) {
                $proofUrl = '/uploads/payment_proofs/' . $filename;
            }
        }
    }

    $reference = 'PAY-' . $ticketId . '-' . strtoupper(bin2hex(random_bytes(4)));
    $amount = $ticket['ticket_price'];
    $userId = $ticket['user_id'];

    $stmtPayment = $db->prepare("
        INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status, payment_gateway_response, ip_address, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE transaction_status = 'pending', payment_gateway_response = VALUES(payment_gateway_response), updated_at = NOW()
    ");
    $gatewayData = json_encode(['proof_url' => $proofUrl, 'method' => $paymentMethod, 'manual' => true]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmtPayment->execute([$userId, $ticket['raffle_id'], $ticketId, $amount, $paymentMethod, $reference, $gatewayData, $ip]);

    $db->commit();

    Logger::activity('payment_confirmed_manual', (int)$userId, [
        'ticket_id' => $ticketId,
        'amount' => $amount,
        'method' => $paymentMethod,
        'reference' => $reference,
        'has_proof' => !empty($proofUrl)
    ]);

    Response::success([
        'ticket_id' => $ticketId,
        'status' => 'paid',
        'reference' => $reference,
        'amount' => (float)$amount
    ], 'Pago confirmado exitosamente');

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::exception($e);
    Response::serverError('Error al confirmar el pago');
}
