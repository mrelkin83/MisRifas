<?php

declare(strict_types=1);

/**
 * API: Apartados del vendedor — el "fiado" (promt2.md §8)
 *
 * GET  /api/vendor/holds.php?raffle_id=N            → cartera de la rifa
 * POST /api/vendor/holds.php
 *   { action: 'hold',      raffle_id, ticket_number, holder_name, holder_phone, note? }
 *   { action: 'mark_paid', ticket_id, payment_method? (cash|nequi|daviplata|breb) }
 *   { action: 'release',   ticket_id }
 *   { action: 'remind',    ticket_id }   ← lo dispara el VENDEDOR, nunca solo (§8.5)
 *
 * Reglas §8.2: solo el vendedor dueño, nunca el comprador ni automático;
 * nombre y celular OBLIGATORIOS (sin ellos no hay cobro posible después);
 * vence en cutoff_at; en el talonario público se ve ocupado; un held NO gana.
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
require_once __DIR__ . '/../../api/services/PaymentKeys.php';

try {
    $vendor = Auth::requireVendor();
    $vendorId = (int)$vendor['id'];
    $db = Database::getInstance()->getConnection();

    $raffleDelVendedor = function (int $raffleId) use ($db, $vendorId): ?array {
        $stmt = $db->prepare("
            SELECT id, name, status, ticket_price, draw_date, cutoff_at
            FROM raffles WHERE id = ? AND COALESCE(vendor_id, created_by) = ?
        ");
        $stmt->execute([$raffleId, $vendorId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    };

    // ── GET: cartera (§8.4) ──
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $raffleId = (int)($_GET['raffle_id'] ?? 0);
        $raffle = $raffleDelVendedor($raffleId);
        if (!$raffle) {
            Response::error('No tienes permiso sobre esta rifa', null, 403);
        }
        $stmt = $db->prepare("
            SELECT id AS ticket_id, ticket_number, holder_name, holder_phone, held_at, held_note,
                   TIMESTAMPDIFF(DAY, held_at, NOW()) AS dias
            FROM tickets
            WHERE raffle_id = ? AND status = 'held'
            ORDER BY held_at ASC
        ");
        $stmt->execute([$raffleId]);
        $holds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cutoff = $raffle['cutoff_at'];
        Response::success([
            'raffle' => ['id' => (int)$raffle['id'], 'name' => $raffle['name'],
                         'ticket_price' => (float)$raffle['ticket_price'], 'cutoff_at' => $cutoff],
            'total_apartado' => (float)$raffle['ticket_price'] * count($holds),
            'cantidad' => count($holds),
            'dias_para_corte' => $cutoff ? max(0, (int)floor((strtotime($cutoff) - time()) / 86400)) : null,
            'holds' => $holds,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = (string)($input['action'] ?? '');

    // ── hold: apartar (available → held) ──
    if ($action === 'hold') {
        $raffleId = (int)($input['raffle_id'] ?? 0);
        $number = trim((string)($input['ticket_number'] ?? ''));
        $holderName = trim((string)($input['holder_name'] ?? ''));
        $holderPhone = preg_replace('/\D+/', '', (string)($input['holder_phone'] ?? ''));
        $note = mb_substr(trim((string)($input['note'] ?? '')), 0, 255);

        $raffle = $raffleDelVendedor($raffleId);
        if (!$raffle) {
            Response::error('No tienes permiso sobre esta rifa', null, 403);
        }
        if ($raffle['status'] !== 'active') {
            Response::error('Solo se aparta en rifas activas', null, 409);
        }
        if (!preg_match('/^\d{2,4}$/', $number)) {
            Response::error('Número inválido', null, 422);
        }
        // §8.2: sin nombre y celular la operación SE RECHAZA.
        if (mb_strlen($holderName) < 3) {
            Response::error('El nombre de la persona es obligatorio (sin él no hay cobro posible después)', null, 422);
        }
        if (!preg_match('/^3[0-9]{9}$/', $holderPhone)) {
            Response::error('El celular de la persona es obligatorio (celular colombiano válido)', null, 422);
        }
        if ($raffle['cutoff_at'] && strtotime((string)$raffle['cutoff_at']) < time()) {
            Response::error('El cierre de apartados de esta rifa ya pasó (' . date('d/m/Y', strtotime((string)$raffle['cutoff_at'])) . ')', null, 409);
        }

        $db->beginTransaction();
        try {
            $row = TicketStateMachine::lockByNumber($db, $raffleId, $number);
            if ($row['status'] !== 'available') {
                $db->rollBack();
                Response::error('El número ' . $number . ' no está disponible (estado: ' . $row['status'] . ')', 'TICKET_NOT_AVAILABLE', 409);
            }
            TicketStateMachine::apply($db, $row, 'held', [
                'actor' => 'vendor', 'source' => 'dashboard', 'actor_id' => $vendorId,
                'reason' => 'apartado por el vendedor',
                'fields' => [
                    'held_by_vendor_id' => $vendorId,
                    'holder_name' => $holderName,
                    'holder_phone' => $holderPhone,
                    'held_at' => date('Y-m-d H:i:s'),
                    'held_note' => $note !== '' ? $note : null,
                ],
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        Logger::activity('ticket_held', $vendorId, ['raffle_id' => $raffleId, 'ticket_number' => $number]);
        Response::success(['ticket_number' => $number, 'status' => 'held'],
            'Número ' . $number . ' apartado para ' . $holderName . '. Vence con el corte de la rifa.');
    }

    // Acciones sobre un held existente: cargar y validar propiedad.
    $ticketId = (int)($input['ticket_id'] ?? 0);
    $stmt = $db->prepare("
        SELECT t.*, r.name AS raffle_name, r.ticket_price,
               COALESCE(r.vendor_id, r.created_by) AS owner_id
        FROM tickets t JOIN raffles r ON r.id = t.raffle_id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t || (int)$t['owner_id'] !== $vendorId) {
        Response::error('No tienes permiso sobre este boleto', null, 403);
    }
    if ($t['status'] !== 'held') {
        Response::error('Ese boleto no está apartado (estado: ' . $t['status'] . ')', null, 409);
    }

    // ── mark_paid: el comprador pagó, o el vendedor lo asume (held → paid) ──
    if ($action === 'mark_paid') {
        $method = (string)($input['payment_method'] ?? 'cash');
        if (!in_array($method, ['cash', 'nequi', 'daviplata', 'breb'], true)) {
            $method = 'cash';
        }
        // Fila en users: sin user_id el ganador quedaría fuera del sorteo.
        $userRepo = new UserRepository();
        $u = $userRepo->findByPhone((string)$t['holder_phone']);
        $buyerId = $u ? (int)$u['id'] : (int)$userRepo->create([
            'unique_id' => bin2hex(random_bytes(16)),
            'name' => $t['holder_name'],
            'phone_whatsapp' => $t['holder_phone'],
            'email' => null,
        ]);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
            $stmt->execute([$ticketId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'held') {
                $db->rollBack();
                Response::error('El boleto ya no está apartado', null, 409);
            }
            TicketStateMachine::apply($db, $row, 'paid', [
                'actor' => 'vendor', 'source' => 'dashboard', 'actor_id' => $vendorId,
                'reason' => 'apartado cobrado (' . $method . ')',
                'fields' => [
                    'user_id' => $buyerId,
                    'paid_at' => date('Y-m-d H:i:s'),
                    'payment_method' => $method,
                    'buyer_name' => $row['holder_name'],
                    'buyer_phone' => $row['holder_phone'],
                ],
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        Logger::activity('hold_paid', $vendorId, ['ticket_id' => $ticketId, 'method' => $method]);

        // Boleta al comprador — post-commit, best-effort.
        require_once __DIR__ . '/../../api/services/Boleta.php';
        Boleta::enviarPorWhatsApp($db, $ticketId, $vendorId);

        Response::success(['status' => 'paid'], 'Apartado cobrado: el boleto #' . $t['ticket_number'] . ' quedó pagado y con boleta.');
    }

    // ── release: liberar el número ──
    if ($action === 'release') {
        TicketStateMachine::transition($db, $ticketId, 'available', [
            'actor' => 'vendor', 'source' => 'dashboard', 'actor_id' => $vendorId,
            'reason' => 'apartado liberado por el vendedor',
        ]);
        Logger::activity('hold_released', $vendorId, ['ticket_id' => $ticketId]);
        Response::success(['status' => 'available'], 'El número #' . $t['ticket_number'] . ' volvió a la venta.');
    }

    // ── remind: recordatorio DIRECTO al comprador — solo si el vendedor lo pide (§8.5) ──
    if ($action === 'remind') {
        $keys = PaymentKeys::sanear(PaymentKeys::delVendor($db, $vendorId));
        $pago = [];
        if ($keys['nequi_phone'] !== '') {
            $pago[] = 'Nequi: ' . $keys['nequi_phone'];
        }
        if ($keys['daviplata_phone'] !== '') {
            $pago[] = 'DaviPlata: ' . $keys['daviplata_phone'];
        }
        if ($keys['breb_key'] !== '') {
            $pago[] = 'Bre-B: ' . $keys['breb_key'];
        }
        $texto = 'Hola ' . $t['holder_name'] . ' 👋 Te recuerdo tu número *' . $t['ticket_number']
            . '* apartado en la rifa *' . $t['raffle_name'] . '*.'
            . "\nValor: $" . number_format((float)$t['ticket_price'], 0, ',', '.')
            . ($pago ? ("\nPuedes pagar por:\n" . implode("\n", $pago)) : '')
            . ($keys['accepts_cash'] ? "\nTambién recibo en efectivo." : '')
            . "\n¡Gracias!";
        require_once __DIR__ . '/../whatsapp/notify.php';
        $enviado = notificarWhatsAppVendor($vendorId, (string)$t['holder_phone'], $texto);
        if (!$enviado) {
            Response::error('No se pudo enviar (¿tu WhatsApp está vinculado?). También puedes escribirle directo al ' . $t['holder_phone'], null, 502);
        }
        Logger::activity('hold_reminder_sent', $vendorId, ['ticket_id' => $ticketId]);
        Response::success([], 'Recordatorio enviado a ' . $t['holder_name'] . ' por WhatsApp.');
    }

    Response::error('Acción inválida', null, 400);
} catch (TicketNotFound $e) {
    Response::error('Ese número no existe en el talonario', null, 404);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error en apartados');
}
