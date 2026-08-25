<?php
/**
 * API: Obtener siguiente jugador a destapar
 * GET /api/tapazo/siguiente.php?codigo=XXXX
 * 
 * Orden por número de cerveza (cerveza_numero)
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

    $stmt = $db->prepare("SELECT id, estado, regla FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) {
        Response::error('Tapazo no encontrado', null, 404);
    }

    if ($tapazo['estado'] !== 'destapando') {
        Response::success([
            'siguiente' => null,
            'estado' => $tapazo['estado'],
            'finalizado' => $tapazo['estado'] === 'finalizado'
        ]);
    }

    // Obtener jugadores ordenados por numero de cerveza
    $stmt = $db->prepare("
        SELECT id, nombre, cerveza_numero, numero_tapa
        FROM tapazo_jugadores 
        WHERE tapazo_id = ? AND numero_tapa IS NOT NULL
        ORDER BY cerveza_numero ASC
    ");
    $stmt->execute([$tapazo['id']]);
    $todos = $stmt->fetchAll();

    if (empty($todos)) {
        Response::success([
            'siguiente' => null,
            'estado' => 'destapando',
            'finalizado' => false,
            'regla' => $tapazo['regla']
        ]);
    }

    // Contar los ya revelados (usando la tabla tapazo_jugadores_revelados o un flag)
    // Simplificado: usamos el ultimo_revelado en tapazos
    $stmt = $db->prepare("SELECT ultimo_revelado FROM tapazos WHERE id = ?");
    $stmt->execute([$tapazo['id']]);
    $ultimoRevelado = $stmt->fetchColumn();
    $ultimoRevelado = $ultimoRevelado ? explode(',', $ultimoRevelado) : [];
    
    $revelados = array_filter($ultimoRevelado);

    if (count($revelados) >= count($todos)) {
        $stmt = $db->prepare("UPDATE tapazos SET estado = 'finalizado' WHERE id = ?");
        $stmt->execute([$tapazo['id']]);
        
        Response::success([
            'siguiente' => null,
            'estado' => 'finalizado',
            'finalizado' => true,
            'regla' => $tapazo['regla']
        ]);
    }

    foreach ($todos as $j) {
        if (!in_array($j['id'], $revelados)) {
            // Agregar a lista de revelados
            $revelados[] = $j['id'];
            $nuevoUltimo = implode(',', $revelados);
            
            $stmt = $db->prepare("UPDATE tapazos SET ultimo_revelado = ? WHERE id = ?");
            $stmt->execute([$nuevoUltimo, $tapazo['id']]);
            
            Response::success([
                'siguiente' => $j,
                'estado' => 'destapando',
                'finalizado' => false,
                'total_jugadores' => count($todos),
                'revelados' => count($revelados),
                'regla' => $tapazo['regla']
            ]);
        }
    }

    Response::success([
        'siguiente' => null,
        'estado' => 'destapando',
        'finalizado' => false,
        'regla' => $tapazo['regla']
    ]);

} catch (Exception $e) {
    Response::serverError('Error al obtener siguiente');
}
