<?php
/**
 * API: Estado de revelaciones en tiempo real
 * GET /api/tapazo/estado_revelaciones.php?codigo=XXXX
 *
 * Devuelve todos los jugadores revelados hasta el momento
 * para sincronización en tiempo real entre todos los clientes
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

try {
    $codigo = trim($_GET['codigo'] ?? '');
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, estado, regla, ultimo_revelado FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) {
        Response::error('Tapazo no encontrado', null, 404);
    }

    // Si no está destapando o finalizado, devolver estado sin revelaciones
    if (!in_array($tapazo['estado'], ['destapando', 'finalizado'])) {
        Response::success([
            'estado' => $tapazo['estado'],
            'revelados' => [],
            'total_revelados' => 0,
            'finalizado' => false,
            'regla' => $tapazo['regla']
        ]);
    }

    // Obtener lista de IDs revelados
    $ultimoRevelado = $tapazo['ultimo_revelado'] ? explode(',', $tapazo['ultimo_revelado']) : [];
    $revelados = array_filter($ultimoRevelado);

    // Si no hay revelados aún, devolver vacío
    if (empty($revelados)) {
        Response::success([
            'estado' => $tapazo['estado'],
            'revelados' => [],
            'total_revelados' => 0,
            'finalizado' => $tapazo['estado'] === 'finalizado',
            'regla' => $tapazo['regla']
        ]);
    }

    // Obtener datos de los jugadores revelados EN EL ORDEN EN QUE FUERON REVELADOS
    $placeholders = implode(',', array_fill(0, count($revelados), '?'));
    $stmt = $db->prepare("
        SELECT id, nombre, cerveza_numero, numero_tapa
        FROM tapazo_jugadores
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($revelados);
    $jugadoresMap = [];
    foreach ($stmt->fetchAll() as $j) {
        $jugadoresMap[$j['id']] = $j;
    }

    // Ordenar según el orden de revelación (orden en el array revelados)
    $jugadoresRevelados = [];
    foreach ($revelados as $id) {
        if (isset($jugadoresMap[$id])) {
            $jugadoresRevelados[] = $jugadoresMap[$id];
        }
    }

    // Obtener total de jugadores
    $stmt = $db->prepare("SELECT COUNT(*) FROM tapazo_jugadores WHERE tapazo_id = ?");
    $stmt->execute([$tapazo['id']]);
    $totalJugadores = $stmt->fetchColumn();

    Response::success([
        'estado' => $tapazo['estado'],
        'revelados' => $jugadoresRevelados,
        'total_revelados' => count($jugadoresRevelados),
        'total_jugadores' => (int)$totalJugadores,
        'finalizado' => $tapazo['estado'] === 'finalizado',
        'regla' => $tapazo['regla'],
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    Response::serverError('Error al obtener estado: ' . $e->getMessage());
}
