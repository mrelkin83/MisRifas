<?php
/**
 * API: Crear Rifa
 * POST /api/vendor/create_raffle.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

Auth::requireVendor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', null, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$department = trim($input['department'] ?? '');
$city = trim($input['city'] ?? '');
$lottery_id = intval($input['lottery_id'] ?? 0);
$draw_date = $input['draw_date'] ?? '';
$ticket_price = floatval($input['ticket_price'] ?? 0);
$total_tickets = intval($input['total_tickets'] ?? 0);
$digits = intval($input['digits'] ?? 4);
$opportunities = intval($input['opportunities'] ?? 1);
$winning_mode = $input['winning_mode'] ?? 'last_2';
$description = trim($input['description'] ?? '');

if (empty($name) || empty($department) || empty($city) || $lottery_id <= 0 || empty($draw_date) || $ticket_price <= 0 || $total_tickets <= 0) {
    Response::error('Todos los campos son requeridos', null, 400);
}

$db = Database::getInstance()->getConnection();
$vendorId = $_SESSION['user_id'];

try {
    $stmt = $db->prepare("
        INSERT INTO raffles (name, description, city, department, lottery_id, draw_date, ticket_price, total_tickets, digits, opportunities, winning_mode, status, vendor_id, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())
    ");
    $stmt->execute([$name, $description, $city, $department, $lottery_id, $draw_date, $ticket_price, $total_tickets, $digits, $opportunities, $winning_mode, $vendorId, $vendorId]);

    $raffleId = $db->lastInsertId();

    Logger::activity('raffle_created', $vendorId, ['raffle_id' => $raffleId, 'name' => $name]);

    Response::success(['raffle_id' => $raffleId], 'Rifa creada exitosamente');
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al crear rifa');
}
