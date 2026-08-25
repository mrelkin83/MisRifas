<?php
/**
 * API: Obtener datos del tapazo
 * GET /api/tapazo/info.php?codigo=XXXX
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

try {
    $codigo = trim($_GET['codigo'] ?? '');
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT t.*, 
            (SELECT COUNT(*) FROM tapazo_jugadores WHERE tapazo_id = t.id) as jugadores_count
        FROM tapazos t WHERE t.codigo_unico = ?
    ");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) {
        Response::error('Tapazo no encontrado', null, 404);
    }

    // Obtener jugadores
    $stmt = $db->prepare("
        SELECT id, nombre, cerveza_numero, numero_tapa, orden_destape, created_at
        FROM tapazo_jugadores WHERE tapazo_id = ? ORDER BY cerveza_numero ASC
    ");
    $stmt->execute([$tapazo['id']]);
    $jugadores = $stmt->fetchAll();

    // En estado 'creado' o 'lleno', NO mostrar numero_tapa
    if (in_array($tapazo['estado'], ['creado', 'lleno', 'esperando'])) {
        foreach ($jugadores as &$j) {
            $j['numero_tapa'] = null;
        }
    }

    Response::success([
        'tapazo' => $tapazo,
        'jugadores' => $jugadores
    ]);

} catch (Exception $e) {
    Response::serverError('Error al cargar tapazo');
}
