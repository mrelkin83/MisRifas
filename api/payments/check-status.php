<?php
/**
 * API: Verificar Estado de Pago (Polling)
 * GET /api/payments/check-status.php?payment_intent_id=XXX
 *
 * Este endpoint es consultado por el frontend (polling)
 * para verificar el estado del pago SIN modificarlo.
 * SOLO el webhook puede cambiar el estado.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', null, 405);
}

try {
    $paymentIntentId = trim($_GET['payment_intent_id'] ?? '');
    $reservationId = trim($_GET['reservation_id'] ?? '');

    if (!$paymentIntentId && !$reservationId) {
        Response::error('payment_intent_id o reservation_id es requerido', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    if ($paymentIntentId) {
        $stmt = $db->prepare("
            SELECT pi.id, pi.raffle_id, pi.amount, pi.gateway, pi.status,
                   pi.created_at, pi.updated_at, pi.gateway_payment_id,
                   GROUP_CONCAT(nr.numero ORDER BY nr.numero SEPARATOR ',') as numeros
            FROM payment_intents pi
            LEFT JOIN numero_reservas nr ON nr.payment_intent_id = pi.id
            WHERE pi.id = ?
            GROUP BY pi.id
        ");
        $stmt->execute([$paymentIntentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("
            SELECT pi.id, pi.raffle_id, pi.amount, pi.gateway, pi.status,
                   pi.created_at, pi.updated_at, pi.gateway_payment_id,
                   GROUP_CONCAT(nr.numero ORDER BY nr.numero SEPARATOR ',') as numeros
            FROM numero_reservas nr
            LEFT JOIN payment_intents pi ON pi.id = nr.payment_intent_id
            WHERE nr.reservation_id = ?
            GROUP BY pi.id
        ");
        $stmt->execute([$reservationId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$payment) {
        Response::error('Pago no encontrado', null, 404);
    }

    $responseData = [
        'payment_intent_id' => $payment['id'],
        'raffle_id' => $payment['raffle_id'],
        'amount' => (float)$payment['amount'],
        'gateway' => $payment['gateway'],
        'status' => $payment['status'],
        'created_at' => $payment['created_at'],
        'updated_at' => $payment['updated_at'],
        'gateway_payment_id' => $payment['gateway_payment_id']
    ];

    if ($payment['numeros']) {
        $responseData['numeros'] = explode(',', $payment['numeros']);
    }

    Response::success($responseData, 'Estado obtenido exitosamente');

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al verificar estado: ' . $e->getMessage());
}
