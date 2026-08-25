<?php
/**
 * API: Forzar inicio de destape (manual)
 * POST /api/tapazo/iniciar_destape.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {     header('Access-Control-Allow-Origin: *');
 http_response_code(200); exit; }

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $codigo = trim($input['codigo'] ?? '');
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) Response::error('Tapazo no encontrado');
    if ($tapazo['estado'] === 'finalizado') Response::error('El tapazo ya finalizó');
    if ($tapazo['estado'] === 'destapando') Response::error('El destape ya está en progreso');

    $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ?");
    $stmt->execute([$tapazo['id']]);
    $jugadores = $stmt->fetchAll();

    if (empty($jugadores)) Response::error('No hay jugadores');

    $maxTapas = 999;
    $numeros = range(1, $maxTapas);
    shuffle($numeros);

    $ordenes = range(1, count($jugadores));
    shuffle($ordenes);

    foreach ($jugadores as $idx => $jugador) {
        $stmt = $db->prepare("UPDATE tapazo_jugadores SET numero_tapa = ?, orden_destape = ? WHERE id = ?");
        $stmt->execute([$numeros[$idx], $ordenes[$idx], $jugador['id']]);
    }

    $stmt = $db->prepare("UPDATE tapazos SET estado = 'destapando', ultimo_revelado = '' WHERE id = ?");
    $stmt->execute([$tapazo['id']]);

    Response::success(['message' => 'Destape iniciado']);

} catch (Exception $e) {
    Response::serverError('Error al iniciar destape');
}
