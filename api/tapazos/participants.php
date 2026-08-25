<?php
/**
 * API: Tapazos - Obtener participantes y revelar
 * GET /api/tapazos/participants.php?tapazo_id=X
 * POST /api/tapazos/participants.php - Unirse o revelar
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
require_once __DIR__ . '/../../api/utils/Auth.php';

try {
    $adminUser = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'join') {
            $tapazoId = intval($input['tapazo_id'] ?? 0);
            $name = trim($input['participant_name'] ?? '');
            $phone = trim($input['participant_phone'] ?? '');

            if (!$tapazoId || empty($name)) {
                Response::error('Nombre y tapazo son requeridos');
            }

            // Buscar slot pendiente disponible
            $stmt = $db->prepare("
                SELECT id, cap_number FROM tapazo_participants 
                WHERE tapazo_id = ? AND status = 'pending' 
                ORDER BY id ASC LIMIT 1
            ");
            $stmt->execute([$tapazoId]);
            $slot = $stmt->fetch();

            if (!$slot) {
                Response::error('No hay cupos disponibles en este tapazo');
            }

            $stmt = $db->prepare("
                UPDATE tapazo_participants 
                SET participant_name = ?, participant_phone = ?, status = 'confirmed'
                WHERE id = ?
            ");
            $stmt->execute([$name, $phone, $slot['id']]);

            Response::success([
                'message' => 'Te has unido al tapazo',
                'cap_number' => $slot['cap_number']
            ]);
        }

        if ($action === 'reveal') {
            $tapazoId = intval($input['tapazo_id'] ?? 0);
            $participantId = intval($input['participant_id'] ?? 0);

            $stmt = $db->prepare("
                UPDATE tapazo_participants SET status = 'revealed'
                WHERE id = ? AND tapazo_id = ?
            ");
            $stmt->execute([$participantId, $tapazoId]);

            Response::success(['message' => 'Tapa revelada']);
        }

        if ($action === 'complete') {
            $tapazoId = intval($input['tapazo_id'] ?? 0);

            // Determinar ganador
            $stmt = $db->prepare("SELECT win_mode FROM tapazos WHERE id = ?");
            $stmt->execute([$tapazoId]);
            $tapazo = $stmt->fetch();

            $order = $tapazo['win_mode'] === 'lowest' ? 'ASC' : 'DESC';
            $stmt = $db->prepare("
                SELECT tp.*, t.name as tapazo_name, t.prize, t.win_mode
                FROM tapazo_participants tp
                JOIN tapazos t ON tp.tapazo_id = t.id
                WHERE tp.tapazo_id = ? AND tp.status != 'pending'
                ORDER BY tp.cap_number {$order} LIMIT 1
            ");
            $stmt->execute([$tapazoId]);
            $winner = $stmt->fetch();

            $stmt = $db->prepare("UPDATE tapazos SET status = 'completed' WHERE id = ?");
            $stmt->execute([$tapazoId]);

            Response::success([
                'message' => 'Tapazo completado',
                'winner' => $winner
            ]);
        }

        Response::error('Acción inválida');
    }

    // GET - Obtener participantes
    $tapazoId = intval($_GET['tapazo_id'] ?? 0);
    if (!$tapazoId) {
        Response::error('tapazo_id requerido');
    }

    $stmt = $db->prepare("
        SELECT * FROM tapazo_participants 
        WHERE tapazo_id = ? 
        ORDER BY cap_number ASC
    ");
    $stmt->execute([$tapazoId]);
    $participants = $stmt->fetchAll();

    Response::success($participants);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar participantes');
}
