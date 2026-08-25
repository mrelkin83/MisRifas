<?php
/**
 * API: Generar Link de Pago Wompi
 * POST /api/payments/create-link.php
 */

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
    Response::error('Método no permitido', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $ticketId = (int)($input['ticket_id'] ?? 0);
    $paymentMethod = $input['payment_method'] ?? 'NEQUI';

    if (!$ticketId) {
        Response::error('ID de boleto requerido', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT t.*, r.name as raffle_name, r.ticket_price, COALESCE(r.vendor_id, r.created_by) as vendor_id
        FROM tickets t
        INNER JOIN raffles r ON t.raffle_id = r.id
        WHERE t.id = ? AND t.status = 'reserved'
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        Response::notFound('Boleto no encontrado o no está reservado');
    }

    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$ticket['vendor_id']]);
    $vendor = $stmt->fetch();

    $wompiConfig = json_decode($vendor['payment_config'] ?? '{}', true);

    if (empty($wompiConfig['public_key'])) {
        Response::error('El creador de la rifa no ha configurado Wompi');
    }

    $reference = 'MR-' . $ticket['id'] . '-' . time();
    $amountCents = (int)($ticket['ticket_price'] * 100);

    $config = require __DIR__ . '/../../config/app.php';
    $appUrl = $config['app']['url'];

    $wompiData = [
        'public_key' => $wompiConfig['public_key'],
        'currency' => 'COP',
        'amount_in_cents' => $amountCents,
        'reference' => $reference,
        'redirect_url' => $appUrl . '/payment.html?ticket=' . $ticketId . '&status=success',
        'webhook_url' => $appUrl . '/api/payments/webhook.php',
        'customer_data' => [
            'email' => $input['email'] ?? '',
            'phone_number' => $input['phone'] ?? '',
            'full_name' => $input['name'] ?? ''
        ],
        'payment_methods' => [
            'payment_method' => $paymentMethod
        ]
    ];

    $wompiUrl = 'https://checkout.wompi.co/p';

    Logger::info('Wompi payment link created', [
        'ticket_id' => $ticketId,
        'reference' => $reference,
        'amount' => $amountCents
    ]);

    Response::success([
        'payment_url' => $wompiUrl . '?' . http_build_query($wompiData),
        'reference' => $reference,
        'amount' => $ticket['ticket_price'],
        'payment_method' => $paymentMethod
    ]);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al crear el link de pago');
}
