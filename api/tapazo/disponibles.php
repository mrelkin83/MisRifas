<?php
/**
 * API: Obtener números disponibles
 * GET /api/tapazo/disponibles.php?codigo=XXXX
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

try {
    $codigo = trim($_GET['codigo'] ?? '');
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, cantidad_jugadores FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) {
        Response::error('Tapazo no encontrado', null, 404);
    }

    $stmt = $db->prepare("SELECT cerveza_numero FROM tapazo_jugadores WHERE tapazo_id = ?");
    $stmt->execute([$tapazo['id']]);
    $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $disponibles = [];
    for ($i = 1; $i <= $tapazo['cantidad_jugadores']; $i++) {
        if (!in_array($i, $ocupados)) {
            $disponibles[] = $i;
        }
    }

    Response::success([
        'disponibles' => $disponibles,
        'total' => count($disponibles)
    ]);

} catch (Exception $e) {
    Response::serverError('Error al cargar números');
}
