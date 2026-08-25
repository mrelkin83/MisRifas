<?php
/**
 * API: Crear Reserva (transaccional)
 * POST /api/payments/create-reservation.php
 *
 * Flujo:
 * 1. Recibir múltiples números seleccionados
 * 2. Validar que estén disponibles
 * 3. Crear registros en numero_reservas (ESTADO=RESERVADO, expires_at=NOW()+10min)
 * 4. Crear payment_intent (PENDING)
 * 5. Devolver datos del gateway
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !is_array($input)) {
        Response::error('Datos inválidos');
    }

    // Extraer datos
    $raffleId = intval($input['raffle_id'] ?? 0);
    $numeros = $input['numeros'] ?? [];

    if ($raffleId <= 0) {
        Response::error('raffle_id es requerido', null, 400);
    }

    if (empty($numeros) || !is_array($numeros)) {
        Response::error('numeros es requerido y debe ser un array', null, 400);
    }

    $gateway = trim($input['payment_gateway'] ?? '');
    if ($gateway !== 'wompi') {
        Response::error('Solo se acepta Wompi como gateway de pago', null, 400);
    }

    // Validar números (solo dígitos, 2-4 caracteres)
    foreach ($numeros as $numero) {
        $numero = trim(strval($numero));
        if (!preg_match('/^\d{2,4}$/', $numero)) {
            Response::error('Número inválido: ' . $numero, null, 400);
        }
    }

    $db = Database::getInstance()->getConnection();

    // Validar raffle existe y está activa
    $stmt = $db->prepare("SELECT id, name, ticket_price, total_tickets, status FROM raffles WHERE id = ? AND status = 'active'");
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$raffle) {
        Response::error('La rifa no existe o no está activa', null, 404);
    }

    // Validar que todos los números estén disponibles
    $numeroPlaceholders = implode(',', array_fill(0, count($numeros), '?'));
    $stmt = $db->prepare("
        SELECT numero FROM numero_reservas
        WHERE raffle_id = ? AND numero IN ({$numeroPlaceholders})
        AND estado IN ('RESERVADO', 'PAGADO')
    ");
    $stmt->execute(array_merge([$raffleId], $numeros));
    $numerosNoDisponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($numerosNoDisponibles) > 0) {
        Response::error('Los siguientes números no están disponibles: ' . implode(', ', $numerosNoDisponibles) . '. Selecciona otros números para continuar.', null, 400);
    }

    // ========================================
    // TRANSACCIÓN: Crear Reserva
    // ========================================
    $db->beginTransaction();

    try {
        // Generar reservation_id único
        $reservationId = 'RES-' . $raffleId . '-' . bin2hex(random_bytes(8));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $amount = $raffle['ticket_price'] * count($numeros);

        // 0. `tickets` (usada por api/tickets/reserve.php) y `numero_reservas`
        // (usada por este endpoint) son dos sistemas de inventario que antes
        // no se consultaban entre si: dos compradores podian terminar cada
        // uno creyendo que tenia el mismo numero, uno por cada checkout.
        // Se bloquea y reserva tambien la fila de `tickets` de cada numero,
        // con el mismo candado (SELECT ... FOR UPDATE) que ya usa
        // TicketRepository::reserveTicket() - `tickets` queda como la unica
        // fuente de verdad de disponibilidad para ambos flujos.
        $lockStmt = $db->prepare("SELECT id, status FROM tickets WHERE raffle_id = ? AND ticket_number = ? FOR UPDATE");
        $updateTicketStmt = $db->prepare("
            UPDATE tickets SET status = 'reserved', user_id = ?, reserved_at = NOW(), reserved_until = ?
            WHERE id = ?
        ");
        foreach ($numeros as $numero) {
            $lockStmt->execute([$raffleId, $numero]);
            $ticketRow = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ticketRow || $ticketRow['status'] !== 'available') {
                throw new Exception('El número ' . $numero . ' ya no está disponible. Selecciona otros números para continuar.');
            }
            $updateTicketStmt->execute([$_SESSION['user_id'] ?? null, $expiresAt, $ticketRow['id']]);
        }

        // 1. Insertar en numero_reservas (cada número individualmente)
        $stmt = $db->prepare("
            INSERT INTO numero_reservas
                (raffle_id, numero, estado, user_id, reservation_id, reserved_at, expires_at)
            VALUES (?, ?, 'RESERVADO', ?, ?, NOW(), ?)
        ");
        foreach ($numeros as $numero) {
            $stmt->execute([$raffleId, $numero, $_SESSION['user_id'] ?? null, $reservationId, $expiresAt]);
        }

        // 2. Crear payment_intent
        $stmt = $db->prepare("
            INSERT INTO payment_intents
                (raffle_id, user_id, amount, gateway, status, created_at)
            VALUES (?, ?, ?, ?, 'PENDING', NOW())
        ");
        $stmt->execute([$raffleId, $_SESSION['user_id'], $amount, $gateway]);

        $paymentIntentId = $db->lastInsertId();

        // 3. Actualizar numero_reservas con payment_intent_id
        $stmt = $db->prepare("
            UPDATE numero_reservas
            SET payment_intent_id = ?
            WHERE reservation_id = ?
        ");
        $stmt->execute([$paymentIntentId, $reservationId]);

        $db->commit();

        Logger::activity('reservation_created', $_SESSION['user_id'], [
            'raffle_id' => $raffleId,
            'numeros_count' => count($numeros),
            'amount' => $amount,
            'gateway' => $gateway,
            'reservation_id' => $reservationId,
            'payment_intent_id' => $paymentIntentId,
            'expires_at' => $expiresAt
        ]);

        // ========================================
        // RESPUESTA SEGÚN GATEWAY
        // ========================================
        $responseData = [
            'reservation_id' => $reservationId,
            'payment_intent_id' => $paymentIntentId,
            'amount' => $amount,
            'currency' => 'COP',
            'numeros' => $numeros,
            'expires_at' => $expiresAt,
            'raffle' => [
                'id' => $raffle['id'],
                'name' => $raffle['name'],
                'ticket_price' => (float)$raffle['ticket_price']
            ]
        ];

        // Datos por gateway (SOLO Wompi)
        if ($gateway === 'wompi') {
            global $config;
            $wompiData = [
                'amount_in_cents' => intval($amount * 100),
                'currency' => 'COP',
                'customer_email' => $input['customer_email'] ?? '',
                'customer_name' => $input['customer_name'] ?? '',
                'reference' => strval($paymentIntentId),
                'payment_method' => $input['payment_method'] ?? 'nequi',
                'payment_type' => 'reservation',
                'redirect_url' => BASE_PATH . '/public/pago-procesando.php?reservation_id=' . $reservationId . '&payment_intent_id=' . $paymentIntentId
            ];

            $responseData['wompi'] = $wompiData;
        }

        Response::success($responseData, 'Reserva creada exitosamente');

    } catch (Exception $e) {
        $db->rollBack();
        Logger::exception($e);
        Response::serverError('Error al crear la reserva: ' . $e->getMessage());
    }

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar la solicitud');
}
