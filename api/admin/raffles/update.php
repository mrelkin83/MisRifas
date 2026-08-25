<?php
/**
 * API: Update Raffle
 * POST /api/admin/raffles/update.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/utils/Logger.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';
require_once __DIR__ . '/../../../api/utils/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        Response::error('ID de rifa requerido');
    }

    $raffleId = intval($input['id']);

    // Check permissions
    if ($adminUser['role'] !== 'super_admin') {
        $stmt = $db->prepare("SELECT id FROM raffles WHERE id = ? AND created_by = ?");
        $stmt->execute([$raffleId, $adminUser['id']]);
        if (!$stmt->fetch()) {
            Response::error('No tienes permisos para editar esta rifa', null, 403);
        }
    }

    $fields = [];
    $params = [];

    // Sanitizado igual que api/raffles/create.php - faltaba aqui, y ese hueco
    // era un XSS almacenado: name/description se renderizan sin escapar via
    // innerHTML tanto en el dashboard del super_admin como en el storefront
    // publico (raffle.php).
    if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = Validator::sanitize($input['name']); }
    if (isset($input['status'])) { $fields[] = 'status = ?'; $params[] = $input['status']; }
    if (isset($input['ticket_price'])) { $fields[] = 'ticket_price = ?'; $params[] = floatval($input['ticket_price']); }
    if (isset($input['total_tickets'])) { $fields[] = 'total_tickets = ?'; $params[] = intval($input['total_tickets']); }
    if (isset($input['draw_date'])) { $fields[] = 'draw_date = ?'; $params[] = $input['draw_date']; }
    if (isset($input['lottery_id'])) { $fields[] = 'lottery_id = ?'; $params[] = intval($input['lottery_id']); }
    if (isset($input['whatsapp_contact'])) { $fields[] = 'whatsapp_contact = ?'; $params[] = Validator::sanitize($input['whatsapp_contact']); }
    if (isset($input['responsible_person'])) { $fields[] = 'responsible_person = ?'; $params[] = Validator::sanitize($input['responsible_person']); }
    if (isset($input['description'])) { $fields[] = 'description = ?'; $params[] = Validator::sanitize($input['description']); }

    if (empty($fields)) {
        Response::error('No hay datos para actualizar');
    }

    $fields[] = 'updated_at = NOW()';
    $params[] = $raffleId;

    $sql = "UPDATE raffles SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    Logger::activity('raffle_updated', $adminUser['id'], ['raffle_id' => $raffleId]);

    Response::success(['message' => 'Rifa actualizada correctamente']);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al actualizar la rifa');
}
