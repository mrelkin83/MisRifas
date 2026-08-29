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
require_once __DIR__ . '/_destape_helper.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $codigo = trim($input['codigo'] ?? '');
    $db = Database::getInstance()->getConnection();

    // Verificación previa rápida (mensajes de error claros); la asignación real
    // es atómica dentro del helper (SELECT ... FOR UPDATE), idempotente ante
    // clicks/llamadas simultáneas.
    $stmt = $db->prepare("SELECT estado FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $estado = $stmt->fetchColumn();

    if ($estado === false) Response::error('Tapazo no encontrado');
    if ($estado === 'finalizado') Response::error('El tapazo ya finalizó');
    if ($estado === 'destapando') Response::error('El destape ya está en progreso');

    // force = true: iniciar aunque no haya llegado la hora ("Iniciar ahora").
    $tapazo = iniciarDestapeAtomico($db, $codigo, true);

    if ($tapazo === null) Response::error('Tapazo no encontrado');
    if ($tapazo['estado'] !== 'destapando') Response::error('No hay jugadores');

    Response::success(['message' => 'Destape iniciado']);

} catch (Exception $e) {
    Response::serverError('Error al iniciar destape');
}
