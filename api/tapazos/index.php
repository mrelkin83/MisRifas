<?php
/**
 * API: Tapazos - CRUD
 * GET /api/tapazos/index.php - Listar tapazos
 * POST /api/tapazos/index.php - Crear tapazo
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
        
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $prize = trim($input['prize'] ?? '');
        $totalParticipants = intval($input['total_participants'] ?? 6);
        $winMode = $input['win_mode'] ?? 'highest';
        $whatsapp = trim($input['whatsapp'] ?? '');

        if (empty($name)) {
            Response::error('El nombre es requerido');
        }

        $stmt = $db->prepare("
            INSERT INTO tapazos (name, description, prize, total_participants, win_mode, whatsapp, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
        ");
        $stmt->execute([$name, $description, $prize, $totalParticipants, $winMode, $whatsapp, $adminUser['id']]);
        $tapazoId = (int)$db->lastInsertId();

        // Generar números de tapa (1 al total_participants) y mezclar
        $caps = range(1, $totalParticipants);
        shuffle($caps);

        // Crear slots de participantes con números pre-asignados
        for ($i = 0; $i < $totalParticipants; $i++) {
            $stmt = $db->prepare("
                INSERT INTO tapazo_participants (tapazo_id, participant_name, cap_number, status)
                VALUES (?, '', ?, 'pending')
            ");
            $stmt->execute([$tapazoId, $caps[$i]]);
        }

        Logger::activity('tapazo_created', $adminUser['id'], ['tapazo_id' => $tapazoId, 'name' => $name]);

        Response::success(['id' => $tapazoId, 'message' => 'Tapazo creado exitosamente']);
    }

    // GET - Listar tapazos
    $stmt = $db->prepare("
        SELECT t.*, u.full_name as creator_name,
            (SELECT COUNT(*) FROM tapazo_participants WHERE tapazo_id = t.id AND status != 'pending') as joined_count
        FROM tapazos t
        JOIN admin_users u ON t.created_by = u.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $tapazos = $stmt->fetchAll();

    Response::success($tapazos);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al procesar tapazos');
}
