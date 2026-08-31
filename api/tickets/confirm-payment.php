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
require_once __DIR__ . '/../../api/services/TicketStateMachine.php';

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
    $reservationId = trim($input['reservation_id'] ?? '');
    $paymentMethod = strtolower(trim($input['payment_method'] ?? ''));
    $proof = $input['proof'] ?? null;

    if ($ticketId <= 0 && $reservationId === '') {
        Response::error('ID de ticket o de reserva invalido', null, 400);
    }

    $allowedMethods = ['nequi', 'bancolombia', 'daviplata', 'efecty', 'manual'];
    if (!in_array($paymentMethod, $allowedMethods)) {
        Response::error('Metodo de pago invalido. Permitidos: ' . implode(', ', $allowedMethods), null, 400);
    }

    $db = Database::getInstance()->getConnection();

    // reservation_id agrupa varios boletos reservados juntos (selector
    // multiple de raffle.php via create-reservation.php) - se resuelve a
    // la lista real de tickets uniendo con numero_reservas, ya que
    // `tickets` no guarda el reservation_id directamente.
    if ($reservationId !== '') {
        $stmt = $db->prepare("
            SELECT t.*, r.ticket_price, r.name as raffle_title
            FROM numero_reservas nr
            INNER JOIN tickets t ON t.raffle_id = nr.raffle_id AND t.ticket_number = nr.numero
            INNER JOIN raffles r ON t.raffle_id = r.id
            WHERE nr.reservation_id = ?
        ");
        $stmt->execute([$reservationId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tickets)) {
            Response::error('Reserva no encontrada', null, 404);
        }
    } else {
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

        $tickets = [$ticket];
    }

    foreach ($tickets as $t) {
        if ($t['status'] !== 'reserved') {
            Response::error('El boleto #' . $t['ticket_number'] . ' no esta reservado o ya fue pagado (estado: ' . $t['status'] . ')');
        }
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
                $filenamePrefix = $reservationId !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', $reservationId) : (string)$ticketId;
                $filename = 'payment_proof_' . $filenamePrefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $imageType;
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

    // Un comprobante cubre todos los boletos de la reserva (selector
    // multiple) - se inserta una fila en `payments` por boleto, todas
    // apuntando al mismo comprobante, para que la revision/aprobacion del
    // vendedor (api/admin/payments.php) siga operando boleto por boleto
    // sin cambios.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $gatewayData = json_encode(['proof_url' => $proofUrl, 'method' => $paymentMethod, 'manual' => true, 'reservation_id' => $reservationId ?: null]);
    $stmtPayment = $db->prepare("
        INSERT INTO payments (user_id, raffle_id, ticket_id, amount, payment_method, transaction_reference, transaction_status, payment_gateway_response, ip_address, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
    ");

    // §11: bloquear en orden ascendente de id para evitar deadlocks.
    usort($tickets, fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

    $totalAmount = 0;
    $ticketIds = [];
    $userId = null;
    foreach ($tickets as $t) {
        $reference = 'PAY-' . $t['id'] . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmtPayment->execute([$t['user_id'], $t['raffle_id'], $t['id'], $t['ticket_price'], $paymentMethod, $reference, $gatewayData, $ip]);
        // Comprobante subido → el boleto pasa a revisión del vendedor (§7.2:
        // reserved → pending_review), en la MISMA transacción que la fila de
        // payments. El cron de expiración ya no lo puede liberar por TTL de
        // reserva (tiene su propio TTL de revisión).
        TicketStateMachine::transition($db, (int)$t['id'], 'pending_review', [
            'actor' => 'buyer', 'source' => 'web', 'actor_id' => (int)$t['user_id'],
            'reason' => 'comprobante subido',
            'detail' => ['method' => $paymentMethod, 'proof' => $proofUrl, 'reference' => $reference],
            'fields' => ['payment_method' => in_array($paymentMethod, ['nequi', 'daviplata', 'breb', 'cash'], true) ? $paymentMethod : null],
        ]);
        $totalAmount += (float)$t['ticket_price'];
        $ticketIds[] = (int)$t['id'];
        $userId = $t['user_id'];
    }

    $db->commit();

    Logger::activity('payment_proof_reported', (int)$userId, [
        'ticket_ids' => $ticketIds,
        'reservation_id' => $reservationId ?: null,
        'amount' => $totalAmount,
        'method' => $paymentMethod,
        'has_proof' => !empty($proofUrl)
    ]);

    Response::success([
        'ticket_ids' => $ticketIds,
        'status' => 'pending_review',
        'payment_status' => 'pending_review',
        'amount' => $totalAmount
    ], 'Comprobante recibido. Tu' . (count($ticketIds) > 1 ? 's boletos quedaran pagados' : ' boleto quedara pagado') . ' cuando el vendedor verifique el pago.');

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::exception($e);
    Response::serverError('Error al confirmar el pago');
}
