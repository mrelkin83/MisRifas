<?php

declare(strict_types=1);

/**
 * API: Registrar venta en efectivo (promt2.md §5.2)
 * POST /api/vendor/cash_sale.php
 *   { raffle_id, ticket_number, buyer_name, buyer_phone, buyer_email? }
 *
 * Solo el VENDEDOR dueño de la rifa, desde su panel — nunca el comprador
 * desde el enlace público. Va directo a 'paid' sin comprobante, pero EXIGE
 * nombre y celular del comprador (sin ellos no hay boleta emitible ni
 * trazabilidad de disputa) y queda en la bitácora con el vendedor como actor.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/repositories/UserRepository.php';
require_once __DIR__ . '/../../api/services/TicketStateMachine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $vendor = Auth::requireVendor();
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $raffleId = (int)($input['raffle_id'] ?? 0);
    $number = trim((string)($input['ticket_number'] ?? ''));
    $buyerName = trim((string)($input['buyer_name'] ?? ''));
    $buyerPhone = preg_replace('/\D+/', '', (string)($input['buyer_phone'] ?? ''));
    $buyerEmail = trim((string)($input['buyer_email'] ?? ''));

    if ($raffleId <= 0 || !preg_match('/^\d{2,4}$/', $number)) {
        Response::error('Rifa o número inválido', null, 422);
    }
    // §5.2 / §8.2: sin nombre y celular no hay operación.
    if (mb_strlen($buyerName) < 3) {
        Response::error('El nombre del comprador es requerido (mínimo 3 caracteres)', null, 422);
    }
    if (!preg_match('/^3[0-9]{9}$/', $buyerPhone)) {
        Response::error('El celular del comprador es requerido (celular colombiano válido)', null, 422);
    }
    if ($buyerEmail !== '' && !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        Response::error('El email no es válido', null, 422);
    }

    // La rifa debe ser del vendedor autenticado y estar activa.
    $stmt = $db->prepare("
        SELECT id, name, status FROM raffles
        WHERE id = ? AND COALESCE(vendor_id, created_by) = ?
    ");
    $stmt->execute([$raffleId, (int)$vendor['id']]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$raffle) {
        Response::error('No tienes permiso sobre esta rifa', null, 403);
    }
    if ($raffle['status'] !== 'active') {
        Response::error('Solo se registran ventas sobre rifas activas (estado: ' . $raffle['status'] . ')', null, 409);
    }

    // Comprador en `users` (con user_id el boleto entra al sorteo y a las
    // notificaciones; sin él, un ganador en efectivo quedaría invisible para
    // process_draws). Email opcional aquí: la venta es cara a cara.
    $userRepo = new UserRepository();
    $buyer = $userRepo->findByPhone($buyerPhone);
    if (!$buyer) {
        $uniqueId = bin2hex(random_bytes(16));
        $buyerId = $userRepo->create([
            'unique_id' => $uniqueId,
            'name' => $buyerName,
            'phone_whatsapp' => $buyerPhone,
            'email' => $buyerEmail !== '' ? $buyerEmail : null,
        ]);
    } else {
        $buyerId = (int)$buyer['id'];
        if (empty($buyer['email']) && $buyerEmail !== '') {
            $db->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$buyerEmail, $buyerId]);
        }
    }

    $db->beginTransaction();
    try {
        $row = TicketStateMachine::lockByNumber($db, $raffleId, $number);
        if ($row['status'] !== 'available') {
            $db->rollBack();
            Response::error('El número ' . $number . ' no está disponible (estado: ' . $row['status'] . ')', 'TICKET_NOT_AVAILABLE', 409);
        }
        // §7.2: available → paid, disparador "el vendedor registra venta en
        // efectivo". Sin comprobante, con trazabilidad completa.
        TicketStateMachine::apply($db, $row, 'paid', [
            'actor' => 'vendor', 'source' => 'dashboard', 'actor_id' => (int)$vendor['id'],
            'reason' => 'venta en efectivo',
            'detail' => ['buyer_name' => $buyerName, 'buyer_phone' => $buyerPhone],
            'fields' => [
                'user_id' => $buyerId,
                'paid_at' => date('Y-m-d H:i:s'),
                'payment_method' => 'cash',
                'buyer_name' => $buyerName,
                'buyer_phone' => $buyerPhone,
            ],
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    Logger::activity('cash_sale', (int)$vendor['id'], [
        'raffle_id' => $raffleId, 'ticket_number' => $number, 'buyer_phone' => $buyerPhone,
    ]);

    Response::success([
        'raffle_id' => $raffleId,
        'ticket_number' => $number,
        'status' => 'paid',
        'payment_method' => 'cash',
    ], 'Venta en efectivo registrada. El número quedó pagado.');
} catch (TicketNotFound $e) {
    Response::error('Ese número no existe en el talonario', null, 404);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al registrar la venta');
}
