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
    $stmt = $db->prepare("SELECT id, name, ticket_price, total_tickets, status, draw_date, sales_blocked, COALESCE(vendor_id, created_by) AS owner_id FROM raffles WHERE id = ? AND status = 'active'");
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

        // §6 — Codificación de centavos: sufijo único entre las órdenes
        // vigentes de la rifa para que el vendedor identifique el pago por el
        // monto. Un solo número en talonario ≤100 → sufijo = el número de la
        // boleta; si no, aleatorio [1,999] verificado contra las vigentes.
        if (count($numeros) === 1 && (int)$raffle['total_tickets'] <= 100) {
            $paymentSuffix = (int)$numeros[0];
        } else {
            $stmt = $db->prepare("
                SELECT DISTINCT payment_suffix FROM numero_reservas
                WHERE raffle_id = ? AND estado = 'RESERVADO' AND payment_suffix IS NOT NULL
            ");
            $stmt->execute([$raffleId]);
            $usados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            do {
                $paymentSuffix = random_int(1, 999);
            } while (in_array($paymentSuffix, $usados, true));
        }
        $amount = $raffle['ticket_price'] * count($numeros) + $paymentSuffix;

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
        // las filas de la orden comparten el sufijo de centavos)
        $stmt = $db->prepare("
            INSERT INTO numero_reservas
                (raffle_id, numero, estado, user_id, reservation_id, reserved_at, expires_at, payment_suffix)
            VALUES (?, ?, 'RESERVADO', ?, ?, NOW(), ?, ?)
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
