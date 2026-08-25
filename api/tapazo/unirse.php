<?php
/**
 * API: Unirse al tapazo
 * POST /api/tapazo/unirse.php
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
    $db = Database::getInstance()->getConnection();

    $codigo = trim($input['codigo'] ?? '');
    $nombre = trim($input['nombre'] ?? '');
    $cerveza_numero = intval($input['cerveza_numero'] ?? 0);

    if (empty($codigo) || empty($nombre)) {
        Response::error('Código y nombre son requeridos');
    }

    // Obtener tapazo
    $stmt = $db->prepare("SELECT * FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) Response::error('Tapazo no encontrado');
    if ($tapazo['estado'] !== 'creado') Response::error('El tapazo ya está lleno o en progreso');

    // Verificar nombre duplicado
    $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ? AND nombre = ?");
    $stmt->execute([$tapazo['id'], $nombre]);
    if ($stmt->fetch()) Response::error('Ese nombre ya está en uso en este tapazo');

    // Si el usuario elige número específico
    if ($cerveza_numero > 0) {
        // Verificar que esté disponible
        if ($cerveza_numero < 1 || $cerveza_numero > $tapazo['cantidad_jugadores']) {
            Response::error('Número de botella inválido');
        }
        
        $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ? AND cerveza_numero = ?");
        $stmt->execute([$tapazo['id'], $cerveza_numero]);
        if ($stmt->fetch()) {
            Response::error('Ese número de botella ya está ocupado');
        }
        
        $disponible = $cerveza_numero;
    } else {
        // Buscar slot automático disponible
        $stmt = $db->prepare("
            SELECT cerveza_numero FROM tapazo_jugadores WHERE tapazo_id = ? ORDER BY cerveza_numero ASC
        ");
        $stmt->execute([$tapazo['id']]);
        $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $disponible = null;
        for ($i = 1; $i <= $tapazo['cantidad_jugadores']; $i++) {
            if (!in_array($i, $ocupados)) {
                $disponible = $i;
                break;
            }
        }

        if ($disponible === null) {
            Response::error('No hay cervezas disponibles');
        }
    }

    // Registrar jugador
    $stmt = $db->prepare("
        INSERT INTO tapazo_jugadores (tapazo_id, nombre, cerveza_numero)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$tapazo['id'], $nombre, $disponible]);

    // Verificar si está lleno
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM tapazo_jugadores WHERE tapazo_id = ?");
    $stmt->execute([$tapazo['id']]);
    $count = $stmt->fetch()['total'];

    if ($count >= $tapazo['cantidad_jugadores']) {
        $stmt = $db->prepare("UPDATE tapazos SET estado = 'lleno' WHERE id = ?");
        $stmt->execute([$tapazo['id']]);
    }

    Response::success([
        'cerveza_numero' => $disponible,
        'estado' => $count >= $tapazo['cantidad_jugadores'] ? 'lleno' : 'creado'
    ], 'Te has unido al tapazo');

} catch (Exception $e) {
    Response::serverError('Error al unirse al tapazo');
}
