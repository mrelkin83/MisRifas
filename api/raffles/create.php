<?php
/**
 * API: Crear Rifa
 * POST /api/raffles/create.php
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
require_once __DIR__ . '/../../api/utils/Validator.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/repositories/RaffleRepository.php';
require_once __DIR__ . '/../../api/repositories/TicketRepository.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

$db = null;

try {
    $adminUser = Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::error('JSON inválido');
    }

    // Valores por defecto para campos opcionales
    $input['digits']        = isset($input['digits'])        ? (int)$input['digits']        : 2;
    $input['opportunities'] = isset($input['opportunities']) ? (int)$input['opportunities'] : 1;
    $input['winning_mode']  = $input['winning_mode']  ?? 'last_2';

    $validator = new Validator($input);
    $validator
        ->required('name',               'El nombre es requerido')
        ->minLength('name', 5,           'El nombre debe tener al menos 5 caracteres')
        ->required('description',        'La descripción es requerida')
        ->required('department',         'El departamento es requerido')
        ->required('city',               'La ciudad es requerida')
        ->required('scope',              'El alcance es requerido')
        ->in('scope',                    ['municipal', 'departamental', 'national'], 'Alcance inválido')
        ->required('whatsapp_contact',   'El WhatsApp es requerido')
        ->phoneColombia('whatsapp_contact', 'El WhatsApp debe ser válido')
        ->required('responsible_person', 'El responsable es requerido')
        ->required('ticket_price',       'El precio es requerido')
        ->min('ticket_price', 1000,      'El precio mínimo es $1,000')
        ->required('total_tickets',      'El total de boletos es requerido')
        ->in('digits',        [2, 3, 4],              'Las cifras deben ser 2, 3 o 4')
        ->required('draw_date',          'La fecha de sorteo es requerida')
        ->required('lottery_id',         'La lotería es requerida')
        ->in('opportunities', [1, 2, 4, 5],           'Las oportunidades deben ser 1, 2, 4 o 5')
        ->in('winning_mode',  ['last_2','first_2','last_3','first_3','last_4'], 'Modo inválido');

    if ($validator->fails()) {
        Response::validationError($validator->getErrors());
    }

    // Validar que el modo de ganar coincida con las cifras
    $digits = (int)$input['digits'];
    $winningMode = $input['winning_mode'];
    
    if ($digits === 2 && !in_array($winningMode, ['last_2', 'first_2'])) {
        Response::error('Para rifas de 2 cifras, el modo de ganar debe ser últimas 2 o primeras 2 cifras');
    }
    if ($digits === 3 && !in_array($winningMode, ['last_3', 'first_3'])) {
        Response::error('Para rifas de 3 cifras, el modo de ganar debe ser últimas 3 o primeras 3 cifras');
    }
    if ($digits === 4 && $winningMode !== 'last_4') {
        Response::error('Para rifas de 4 cifras, el modo de ganar debe ser últimas 4 cifras');
    }

    // Validar coherencia entre oportunidades y total_tickets
    $opportunities = (int)$input['opportunities'];
    $totalTickets = (int)$input['total_tickets'];
    $maxNumbers = pow(10, $digits);
    $expectedTickets = (int)floor($maxNumbers / $opportunities);
    
    if ($totalTickets !== $expectedTickets) {
        Response::error("Para {$digits} cifras y {$opportunities} oportunidad(es), deben ser {$expectedTickets} boletos");
    }

    // Validar que 2 cifras solo tenga 1 oportunidad
    if ($digits === 2 && $opportunities !== 1) {
        Response::error('Para rifas de 2 cifras solo se permite 1 oportunidad');
    }

    // image_url solo puede ser una ruta local relativa (subida via
    // /api/upload/image.php). Una URL externa aqui permite SSRF: el
    // generador de OG (api/og/generate.php) hace file_get_contents()
    // sobre este valor sin mas validacion.
    $imageUrl = trim($input['image_url'] ?? '/assets/images/placeholder.jpg');
    if ($imageUrl !== '' && (strpos($imageUrl, '//') !== false || strpos($imageUrl, '..') !== false || strpos($imageUrl, ':') !== false || $imageUrl[0] !== '/')) {
        Response::error('image_url invalida: solo se permiten rutas locales', null, 400);
    }

    $raffleData = [
        'name'               => Validator::sanitize($input['name']),
        'description'        => Validator::sanitize($input['description']),
        'image_url'          => $imageUrl,
        'department'         => Validator::sanitize($input['department']),
        'city'               => Validator::sanitize($input['city']),
        'scope'              => $input['scope'],
        'whatsapp_contact'   => Validator::sanitize($input['whatsapp_contact']),
        'responsible_person' => Validator::sanitize($input['responsible_person']),
        'ticket_price'       => (float)$input['ticket_price'],
        'total_tickets'      => (int)$input['total_tickets'],
        'digits'             => (int)$input['digits'],
        'draw_date'          => $input['draw_date'],
        'lottery_id'         => (int)$input['lottery_id'],
        'opportunities'      => (int)$input['opportunities'],
        'winning_mode'       => $input['winning_mode'],
        'status'             => RAFFLE_STATUS_DRAFT,
        'created_by'         => $adminUser['id'],
    ];

    $raffleRepo = new RaffleRepository();
    $ticketRepo = new TicketRepository();

    // Una única transacción que envuelve TODO
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    $raffleId = $raffleRepo->createRaffle($raffleData);

    // Guardar imágenes adicionales si existen (misma validacion anti-SSRF)
    if (!empty($input['image_urls']) && is_array($input['image_urls'])) {
        $safeUrls = array_values(array_filter($input['image_urls'], function ($u) {
            $u = trim((string)$u);
            return $u !== '' && strpos($u, '//') === false && strpos($u, '..') === false && strpos($u, ':') === false && $u[0] === '/';
        }));
        if ($safeUrls) {
            $raffleRepo->addRaffleImages($raffleId, $safeUrls);
        }
    }

    $generated = $ticketRepo->generateTickets(
        $raffleId,
        $raffleData['total_tickets'],
        $raffleData['digits'],
        $raffleData['opportunities']
    );

    if (!$generated) {
        $db->rollBack();
        Response::error('Error al generar los boletos');
    }

    $db->commit();

    Logger::activity('raffle_created', $adminUser['id'], [
        'raffle_id' => $raffleId,
        'name'      => $raffleData['name'],
    ]);

    Response::success([
        'raffle_id' => $raffleId,
        'message'   => 'Rifa creada exitosamente',
    ], 'Rifa creada exitosamente', 201);

} catch (Exception $e) {
    // Solo hacer rollback si la transacción fue iniciada
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    Logger::exception($e);
    Response::serverError('Error al crear la rifa: ' . $e->getMessage());
}
