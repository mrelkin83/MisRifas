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
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Validator.php';
require_once __DIR__ . '/../../api/repositories/UserRepository.php';
require_once __DIR__ . '/../../api/services/TicketStateMachine.php';

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
    if (!in_array($gateway, ['wompi', 'manual'], true)) {
        Response::error('Gateway de pago invalido. Permitidos: wompi, manual', null, 400);
    }

    // Igual que api/tickets/reserve.php: la compra es de invitado, asi que
    // se identifica al comprador por nombre+telefono (no hay sesion PHP
    // iniciada en este flujo) y se busca/crea su fila en `users`. Sin esto
    // `numero_reservas`/`tickets`/`payments` quedaban con user_id NULL,
    // y payments.user_id es NOT NULL - confirm-payment.php fallaba siempre.
    $validator = new Validator($input);
    $validator
        ->required('user.name', 'El nombre es requerido')
        ->minLength('user.name', 3, 'El nombre debe tener al menos 3 caracteres')
        ->required('user.phone', 'El telefono es requerido')
        ->phoneColombia('user.phone', 'El telefono debe ser valido de Colombia')
        // El email es el canal por defecto de notificacion de resultados
        // (ganador/no-ganador/re-sorteo): sin el, el comprador quedaria sin
        // forma garantizada de enterarse del resultado.
        ->required('user.email', 'El email es requerido para notificarte el resultado del sorteo')
        ->email('user.email', 'El email no es valido');
    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }

    $userName = Validator::sanitize($input['user']['name']);
    $userPhone = Validator::sanitize($input['user']['phone']);
    $userEmail = Validator::sanitize($input['user']['email']);

    // Validar números (solo dígitos, 2-4 caracteres)
    foreach ($numeros as $numero) {
        $numero = trim(strval($numero));
        if (!preg_match('/^\d{2,4}$/', $numero)) {
            Response::error('Número inválido: ' . $numero, null, 400);
        }
    }

    $db = Database::getInstance()->getConnection();

    // Buscar o crear el comprador (misma logica que reserve.php)
    $userRepo = new UserRepository();
    $user = $userRepo->findByPhone($userPhone);
    if (!$user) {
        $userId = $userRepo->createUser([
            'name' => $userName,
            'phone_whatsapp' => $userPhone,
            'email' => $userEmail,
        ]);
        $user = $userRepo->findById($userId);
    } elseif (empty($user['email']) && $userEmail) {
        // Comprador recurrente registrado antes de que el email fuera
        // obligatorio: completar su ficha para poder notificarle resultados.
        $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$userEmail, $user['id']]);
        $user['email'] = $userEmail;
    }

    // Validar raffle existe y está activa
    $stmt = $db->prepare("SELECT id, name, ticket_price, total_tickets, status, draw_date, sales_blocked, whatsapp_contact, image_url, COALESCE(vendor_id, created_by) AS owner_id FROM raffles WHERE id = ? AND status = 'active'");
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$raffle) {
        Response::error('La rifa no existe o no está activa', null, 404);
    }

    // §15.3: rifa en mora con la plataforma → ventas NUEVAS suspendidas
    // (los boletos ya pagados y el sorteo no se afectan).
    if (!empty($raffle['sales_blocked'])) {
        Response::error('Las ventas de esta rifa están suspendidas temporalmente por el organizador. El sorteo se realizará normalmente para los boletos ya pagados.', 'SALES_BLOCKED', 423);
    }

    // Integridad del sorteo: cerrar ventas cuando la fecha de sorteo ya pasó,
    // aunque el cron todavía no marque la rifa como 'completed'. Sin esto se
    // podía reservar un número DESPUÉS de conocerse el resultado de la lotería.
    if (!empty($raffle['draw_date']) && strtotime($raffle['draw_date']) <= time()) {
        Response::error('Esta rifa ya cerró sus ventas (la fecha del sorteo ya pasó).', null, 409);
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
        // Mismo contrato que el candado de la transacción (TicketNotAvailable):
        // 409, no 400 — el cliente trata ambos igual.
        Response::error('Los siguientes números no están disponibles: ' . implode(', ', $numerosNoDisponibles) . '. Selecciona otros números para continuar.', 'TICKET_NOT_AVAILABLE', 409);
    }

    // ========================================
    // TRANSACCIÓN: Crear Reserva
    // ========================================
    $db->beginTransaction();

    try {
        // Generar reservation_id único
        $reservationId = 'RES-' . $raffleId . '-' . bin2hex(random_bytes(8));
        // TTL configurable (reservation_ttl_minutes, default 45 — §7.4).
        $ttlMin = TicketStateMachine::reservationTtlMinutes($db);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlMin} minutes"));

        // §6 ELIMINADO por decisión del dueño (2026-09-01): el comprador paga
        // SIEMPRE el valor real exacto (precio × cantidad), sin sufijo de
        // ningún tipo. El pago se identifica por la referencia de la
        // transferencia y el comprobante adjunto — pagar "de más" para
        // identificar la compra confundía y ya se había pedido quitar.
        // La columna payment_suffix se conserva (reservas históricas).
        $paymentSuffix = 0;
        $amount = $raffle['ticket_price'] * count($numeros);

        // §11: bloquear SIEMPRE en orden ascendente — dos peticiones que
        // bloqueen los mismos números en orden distinto se matan en deadlock.
        sort($numeros, SORT_STRING);

        // 0. `tickets` (usada por api/tickets/reserve.php) y `numero_reservas`
        // (usada por este endpoint) son dos sistemas de inventario que antes
        // no se consultaban entre si: dos compradores podian terminar cada
        // uno creyendo que tenia el mismo numero, uno por cada checkout.
        // Se bloquea y reserva tambien la fila de `tickets` de cada numero,
        // con el mismo candado (SELECT ... FOR UPDATE) que ya usa
        // TicketRepository::reserveTicket() - `tickets` queda como la unica
        // fuente de verdad de disponibilidad para ambos flujos.
        // reserved_until en el lock para poder reutilizar reservas expiradas:
        // un ticket 'reserved' cuyo tiempo ya venció es tomable de nuevo aunque
        // el cron de liberación aún no lo haya marcado 'available'. Sin esto,
        // los tickets quedaban bloqueados hasta que corriera el cron (que en
        // hosting sin scheduler puede no correr nunca) → ventas perdidas.
        foreach ($numeros as $numero) {
            $ticketRow = TicketStateMachine::lockByNumber($db, (int)$raffleId, (string)$numero);

            // Una reserva vencida es retomable aunque el cron no la haya
            // liberado aún: se libera aquí mismo (transición real, con
            // bitácora) y luego se reserva para el nuevo comprador.
            if ($ticketRow['status'] === 'reserved'
                && !empty($ticketRow['reserved_until'])
                && strtotime($ticketRow['reserved_until']) < time()) {
                $ticketRow = TicketStateMachine::apply($db, $ticketRow, 'available', [
                    'actor' => 'system', 'source' => 'web',
                    'reason' => 'reserva vencida retomada en checkout',
                ]);
            }
            if ($ticketRow['status'] !== 'available') {
                throw new TicketNotAvailable((int)$raffleId, (string)$numero, (string)$ticketRow['status']);
            }
            TicketStateMachine::apply($db, $ticketRow, 'reserved', [
                'actor' => 'buyer', 'source' => 'web', 'actor_id' => (int)$user['id'],
                'fields' => [
                    'user_id' => (int)$user['id'],
                    'reserved_at' => date('Y-m-d H:i:s'),
                    'reserved_until' => $expiresAt,
                ],
            ]);
        }

        // 1. Insertar en numero_reservas (cada número individualmente; todas
        // las filas de la orden comparten el sufijo de centavos).
        // UPSERT obligatorio: cuando una reserva expira, el cron deja la fila
        // en estado DISPONIBLE (no la borra) y el UNIQUE (raffle_id, numero)
        // bloqueaba re-reservar ese número PARA SIEMPRE (1062 en producción).
        // El candado FOR UPDATE sobre el ticket ya garantizó que el número
        // está libre, así que pisar la fila huérfana es correcto.
        // VALUES() y no el alias "AS nueva": producción corre MariaDB, que no
        // soporta la sintaxis nueva de MySQL 8.0.19+ (en MySQL VALUES() sigue
        // funcionando, solo está deprecado).
        $stmt = $db->prepare("
            INSERT INTO numero_reservas
                (raffle_id, numero, estado, user_id, reservation_id, reserved_at, expires_at, payment_suffix)
            VALUES (?, ?, 'RESERVADO', ?, ?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE
                estado = 'RESERVADO', user_id = VALUES(user_id),
                reservation_id = VALUES(reservation_id), reserved_at = NOW(),
                expires_at = VALUES(expires_at), payment_suffix = VALUES(payment_suffix)
        ");
        foreach ($numeros as $numero) {
            $stmt->execute([$raffleId, $numero, $user['id'], $reservationId, $expiresAt, $paymentSuffix]);
        }

        // payment_intents era exclusivo del gateway automático (eliminado).
        // La tabla queda por compatibilidad histórica (§4.4) pero ninguna
        // ruta la escribe: reservation_id es la única clave de agrupación.
        $paymentIntentId = null;

        $db->commit();

        Logger::activity('reservation_created', $user['id'], [
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
            // §6: el comprador debe transferir EXACTAMENTE este monto.
            'payment_suffix' => $paymentSuffix,
            // §5.3: solo los métodos que el vendedor configuró.
            'payment_methods' => (function () use ($db, $raffle) {
                require_once __DIR__ . '/../../api/services/PaymentKeys.php';
                return PaymentKeys::metodosDisponibles(PaymentKeys::delVendor($db, (int)$raffle['owner_id']));
            })(),
            'raffle' => [
                'id' => $raffle['id'],
                'name' => $raffle['name'],
                'ticket_price' => (float)$raffle['ticket_price']
            ]
        ];

        // Único flujo de pago: manual (el comprador transfiere directo al
        // vendedor y sube comprobante). Las pasarelas se eliminaron — la
        // plataforma nunca toca el dinero del comprador.
        $responseData['payment_url'] = BASE_PATH . '/public/payment.php?reservation_id=' . $reservationId;

        // Confirmación de la reserva al COMPRADOR (correo siempre; WhatsApp
        // si el organizador tiene su número vinculado). Antes no se encolaba
        // NADA: el comprador quedaba sin rastro de su compra. Best-effort:
        // un fallo aquí jamás tumba la reserva ya confirmada.
        try {
            require_once __DIR__ . '/../../api/services/MessageBuilderService.php';
            // El WhatsApp que ve el comprador es el REGISTRADO por el
            // organizador en su cuenta (su configuración individual); el
            // whatsapp_contact de la rifa queda solo como respaldo.
            $vp = $db->prepare('SELECT phone FROM vendors WHERE id = ?');
            $vp->execute([(int)$raffle['owner_id']]);
            $telVendedor = trim((string)$vp->fetchColumn());
            if ($telVendedor !== '') {
                $raffle['whatsapp_contact'] = $telVendedor;
            }
            // Al comprador se le confirman sus NÚMEROS en juego (opportunities),
            // nunca el consecutivo del boleto (regla de producto: evita reclamos).
            $ph = implode(',', array_fill(0, count($numeros), '?'));
            $opsStmt = $db->prepare("SELECT ticket_number, opportunities FROM tickets WHERE raffle_id = ? AND ticket_number IN ({$ph})");
            $opsStmt->execute(array_merge([$raffleId], $numeros));
            $boletos = $opsStmt->fetchAll(PDO::FETCH_ASSOC) ?: $numeros;
            $msg = MessageBuilderService::buildReservationOrderMessage(
                $raffle, $boletos,
                ['name' => $userName],
                (float)$amount, (int)$ttlMin
            );
            $insMq = $db->prepare("
                INSERT INTO message_queue (raffle_id, vendor_id, recipient_user_id, recipient_phone, recipient_email,
                                           channel, message_type, subject, body_text, body_html, status, scheduled_at, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?, 'pending', NOW(), NOW())
            ");
            $insMq->execute([$raffleId, (int)$raffle['owner_id'], (int)$user['id'], $userPhone, $userEmail,
                'email', 'reservation', $msg['subject'], $msg['body_text'], $msg['body_html']]);
            if ($userPhone !== '') {
                $insMq->execute([$raffleId, (int)$raffle['owner_id'], (int)$user['id'], $userPhone, $userEmail,
                    'whatsapp', 'reservation', $msg['subject'], $msg['body_text'], null]);
            }
        } catch (Throwable $e) {
            Logger::error('No se pudo encolar la confirmación de reserva', ['error' => $e->getMessage(), 'reservation' => $reservationId]);
        }

        Response::success($responseData, 'Reserva creada exitosamente');

    } catch (TicketNotAvailable $e) {
        $db->rollBack();
        Response::error('El número ' . $e->ticketNumber . ' ya no está disponible. Selecciona otros números para continuar.', 'TICKET_NOT_AVAILABLE', 409);
    } catch (Exception $e) {
        $db->rollBack();
        Logger::exception($e);
        Response::serverError('Error al crear la reserva: ' . $e->getMessage());
    }

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar la solicitud');
}
