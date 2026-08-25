<?php
/**
 * API: Reservar Boleto
 * POST /api/tickets/reserve.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar dependencias
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Validator.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/repositories/TicketRepository.php';
require_once __DIR__ . '/../../api/repositories/RaffleRepository.php';
require_once __DIR__ . '/../../api/repositories/UserRepository.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

// Limitar reservas por IP - sin esto un solo cliente puede bloquear todos
// los numeros disponibles de una rifa (denegacion de inventario)
if (!RateLimiter::check('reserve_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 20, 1)) {
    Response::rateLimitExceeded('Demasiadas reservas seguidas. Espera un momento.');
}

try {
    // Obtener datos del body
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('JSON inválido');
    }

    // Validar datos
    $validator = new Validator($input);

    $validator
        ->required('raffle_id', 'El ID de la rifa es requerido')
        ->integer('raffle_id', 'El ID de la rifa debe ser un número')
        ->required('ticket_number', 'El número de boleto es requerido')
        ->required('user.name', 'El nombre es requerido')
        ->minLength('user.name', 3, 'El nombre debe tener al menos 3 caracteres')
        ->required('user.phone', 'El teléfono es requerido')
        ->phoneColombia('user.phone', 'El teléfono debe ser válido de Colombia');

    // Email opcional
    if (!empty($input['user']['email'])) {
        $validator->email('user.email', 'El email no es válido');
    }

    // Horas de reserva (2, 4 o 6)
    if (isset($input['reservation_hours'])) {
        $validator->in('reservation_hours', [2, 4, 6], 'Las horas de reserva deben ser 2, 4 o 6');
    }

    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }

    // Sanitizar datos
    $raffleId = (int)$input['raffle_id'];
    $ticketNumber = Validator::sanitize($input['ticket_number']);
    $userName = Validator::sanitize($input['user']['name']);
    $userPhone = Validator::sanitize($input['user']['phone']);
    $userEmail = isset($input['user']['email']) ? Validator::sanitize($input['user']['email']) : null;
    $reservationHours = $input['reservation_hours'] ?? 2;

    // Inicializar repositorios
    $ticketRepo = new TicketRepository();
    $raffleRepo = new RaffleRepository();
    $userRepo = new UserRepository();

    // Verificar que la rifa existe y está activa
    $raffle = $raffleRepo->getRaffleWithStats($raffleId);

    if (!$raffle) {
        Response::notFound('La rifa no existe');
    }

    if ($raffle['status'] !== RAFFLE_STATUS_ACTIVE) {
        Response::error('La rifa no está activa', null, 400);
    }

    // Verificar que el boleto existe y está disponible
    if (!$ticketRepo->isTicketAvailable($raffleId, $ticketNumber)) {
        Response::conflict('El boleto no está disponible');
    }

    // Crear o actualizar usuario
    $user = $userRepo->findByPhone($userPhone);

    if (!$user) {
        // Generar UUID único
        $uniqueId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $userId = $userRepo->create([
            'unique_id' => $uniqueId,
            'name' => $userName,
            'phone_whatsapp' => $userPhone,
            'email' => $userEmail,
        ]);

        $user = $userRepo->findById($userId);
    }

    // Reservar boleto (con control de concurrencia)
    $ticket = $ticketRepo->reserveTicket(
        $raffleId,
        $ticketNumber,
        $user['id'],
        $reservationHours
    );

    if (!$ticket) {
        Response::conflict('No se pudo reservar el boleto. Intente con otro número.');
    }

    // Generar instrucciones de pago
    $paymentReference = 'REF-' . strtoupper(substr(md5($ticket['id'] . time()), 0, 10));

    $paymentInstructions = [
        'method' => 'nequi',
        'amount' => $raffle['ticket_price'],
        'reference' => $paymentReference,
        'instructions' => [
            'step_1' => 'Abre tu app Nequi',
            'step_2' => "Envía $" . number_format($raffle['ticket_price'], 0, ',', '.') . " a la cuenta de Nequi",
            'step_3' => "Usa como referencia: {$paymentReference}",
            'step_4' => 'Tu boleto se confirmará automáticamente al recibir el pago',
        ],
        'deadline' => $ticket['reserved_until'],
    ];

    // Log de actividad
    Logger::activity('ticket_reserved', $user['id'], [
        'raffle_id' => $raffleId,
        'ticket_id' => $ticket['id'],
        'ticket_number' => $ticketNumber,
        'reserved_until' => $ticket['reserved_until'],
    ]);

    // Generar URL de pago
    $paymentUrl = '/payment?ticket=' . $ticket['id'];

    // Respuesta exitosa
    Response::success([
        'ticket' => [
            'id' => $ticket['id'],
            'ticket_number' => $ticket['ticket_number'],
            'opportunities' => json_decode($ticket['opportunities']),
            'reserved_until' => $ticket['reserved_until'],
            'status' => $ticket['status'],
        ],
        'user' => [
            'unique_id' => $user['unique_id'],
            'name' => $user['name'],
            'phone' => $user['phone_whatsapp'],
        ],
        'raffle' => [
            'id' => $raffle['id'],
            'name' => $raffle['name'],
            'draw_date' => $raffle['draw_date'],
            'ticket_price' => $raffle['ticket_price'],
        ],
        'payment_instructions' => $paymentInstructions,
        'payment_url' => $paymentUrl,
    ], 'Boleto reservado exitosamente', 201);

} catch (PDOException $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar la reserva');

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError($e->getMessage());
}
