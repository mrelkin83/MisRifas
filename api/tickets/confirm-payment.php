<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', null, 405);
}

// La compra es de invitado (reserve.php no exige login), por lo que este
// endpoint tampoco puede exigirlo. La proteccion real contra el fraude
// original (C1) es que YA NO marca el ticket como pagado directamente -
// solo registra el comprobante como 'pending' para revision humana via
// POST /api/admin/payments.php (action=approve). El rate limit evita
// spam de reportes falsos sobre boletos ajenos.
if (!RateLimiter::check('confirm_payment_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 5)) {
    Response::rateLimitExceeded('Demasiados intentos. Intenta de nuevo en unos minutos.');
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
        SELECT t.*, r.ticket_price, r.name as raffle_title
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

    // El subtipo declarado en el data-URI NO es de fiar como extension de
    // archivo (asi entraba un .php disfrazado de "imagen") - whitelist
    // estricta en el regex, y ademas se valida el contenido real con
    // getimagesizefromstring() antes de escribir nada a disco, igual que
    // Uploader::upload() ya hace para los demas flujos de imagen.
    $proofUrl = null;
    if ($proof && strpos($proof, 'data:image') === 0
        && preg_match('/^data:image\/(jpe?g|png|webp);base64,(.+)$/i', $proof, $matches)) {
        $imageType = strtolower($matches[1]) === 'jpg' ? 'jpg' : strtolower($matches[1]);
        $imageData = base64_decode($matches[2], true);

        if ($imageData !== false) {
            $imageInfo = @getimagesizefromstring($imageData);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if ($imageInfo !== false && in_array($imageInfo['mime'], $allowedMimes, true)) {
                $filename = 'payment_proof_' . $ticketId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $imageType;
                $uploadDir = __DIR__ . '/../../uploads/payment_proofs/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (file_put_contents($uploadDir . $filename, $imageData) !== false) {
                    $proofUrl = '/uploads/payment_proofs/' . $filename;
                }
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

    Logger::activity('payment_proof_reported', (int)$userId, [
        'ticket_id' => $ticketId,
        'amount' => $amount,
        'method' => $paymentMethod,
        'reference' => $reference,
        'has_proof' => !empty($proofUrl)
    ]);

    Response::success([
        'ticket_id' => $ticketId,
        'status' => 'reserved',
        'payment_status' => 'pending_review',
        'reference' => $reference,
        'amount' => (float)$amount
    ], 'Comprobante recibido. Tu boleto quedara pagado cuando el vendedor verifique el pago.');

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::exception($e);
    Response::serverError('Error al confirmar el pago');
}
