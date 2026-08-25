<?php
/**
 * CRON: Revelar siguiente jugador en tapazos activos
 * Ejecutar cada 3 segundos para ritmo de revelación sincronizado
 *
 * En Linux: * * * * * php /path/to/cron/revelar_siguiente.php
 * En Windows con Task Scheduler: ejecutar cada 3 segundos
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // Buscar tapazos en estado 'destapando'
    $stmt = $db->prepare("
        SELECT id, titulo, regla, ultimo_revelado
        FROM tapazos
        WHERE estado = 'destapando'
    ");
    $stmt->execute();
    $tapazos = $stmt->fetchAll();

    if (empty($tapazos)) {
        // No hay tapazos en destape activo
        exit;
    }

    foreach ($tapazos as $tapazo) {
        $tapazoId = $tapazo['id'];

        // Obtener todos los jugadores ordenados por cerveza_numero
        $stmt = $db->prepare("
            SELECT id, nombre, cerveza_numero, numero_tapa
            FROM tapazo_jugadores
            WHERE tapazo_id = ? AND numero_tapa IS NOT NULL
            ORDER BY cerveza_numero ASC
        ");
        $stmt->execute([$tapazoId]);
        $todosJugadores = $stmt->fetchAll();

        if (empty($todosJugadores)) {
            continue;
        }

        // Obtener lista de revelados
        $ultimoRevelado = $tapazo['ultimo_revelado'] ? explode(',', $tapazo['ultimo_revelado']) : [];
        $revelados = array_filter($ultimoRevelado);

        // Si ya se revelaron todos, cambiar a finalizado
        if (count($revelados) >= count($todosJugadores)) {
            $stmt = $db->prepare("UPDATE tapazos SET estado = 'finalizado' WHERE id = ?");
            $stmt->execute([$tapazoId]);
            echo "Tapazo {$tapazoId} finalizado.\n";
            continue;
        }

        // Buscar el siguiente jugador que no ha sido revelado
        $siguienteJugador = null;
        foreach ($todosJugadores as $jugador) {
            if (!in_array($jugador['id'], $revelados)) {
                $siguienteJugador = $jugador;
                break;
            }
        }

        if (!$siguienteJugador) {
            continue;
        }

        // Agregar a la lista de revelados
        $revelados[] = $siguienteJugador['id'];
        $nuevoUltimo = implode(',', $revelados);

        // Actualizar en base de datos
        $stmt = $db->prepare("UPDATE tapazos SET ultimo_revelado = ? WHERE id = ?");
        $stmt->execute([$nuevoUltimo, $tapazoId]);

        echo "Revelado: {$siguienteJugador['nombre']} (#{$siguienteJugador['cerveza_numero']}) = {$siguienteJugador['numero_tapa']} en tapazo {$tapazoId}\n";

        // Si este fue el último, marcar como finalizado
        if (count($revelados) >= count($todosJugadores)) {
            $stmt = $db->prepare("UPDATE tapazos SET estado = 'finalizado' WHERE id = ?");
            $stmt->execute([$tapazoId]);
            echo "Tapazo {$tapazoId} completado y finalizado.\n";
        }
    }

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
