<?php
/**
 * API: Destape - Server-Sent Events
 * GET /api/tapazo/destape.php?codigo=XXXX
 * 
 * Envía estado inicial y wait. El cliente consulta siguiente jugador por API.
 */
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('X-Accel-Buffering: no');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $codigo = trim($_GET['codigo'] ?? '');
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM tapazos WHERE codigo_unico = ?");
    $stmt->execute([$codigo]);
    $tapazo = $stmt->fetch();

    if (!$tapazo) {
        echo "data: " . json_encode(['error' => 'Tapazo no encontrado']) . "\n\n";
        flush();
        exit;
    }

    $tapazoId = $tapazo['id'];
    $destapeTime = strtotime($tapazo['fecha_hora_destape']);
    $now = time();

    // Si estado es 'lleno', cambiar a 'esperando'
    if ($tapazo['estado'] === 'lleno') {
        $stmt = $db->prepare("UPDATE tapazos SET estado = 'esperando' WHERE id = ?");
        $stmt->execute([$tapazoId]);
        $tapazo['estado'] = 'esperando';
    }

    // Si ya está en estado 'creado' o 'esperando' y hay suficientes jugadores y pasó el tiempo, iniciar automáticamente
    if (in_array($tapazo['estado'], ['creado', 'esperando']) && $now >= $destapeTime) {
        $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ?");
        $stmt->execute([$tapazoId]);
        $jugadores = $stmt->fetchAll();
        
        if (!empty($jugadores)) {
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
            $stmt->execute([$tapazoId]);
            $tapazo['estado'] = 'destapando';
            
            echo "data: " . json_encode([
                'type' => 'init',
                'estado' => 'destapando',
                'regla' => $tapazo['regla']
            ]) . "\n\n";
            flush();
            exit;
        }
    }

    // Si falta 60 segundos o menos, cambiar a 'esperando' para mostrar countdown
    if ($tapazo['estado'] === 'creado' && $now >= ($destapeTime - 60)) {
        $stmt = $db->prepare("UPDATE tapazos SET estado = 'esperando' WHERE id = ?");
        $stmt->execute([$tapazoId]);
        $tapazo['estado'] = 'esperando';
    }

    // Enviar estado inicial
    echo "data: " . json_encode([
        'type' => 'init',
        'estado' => $tapazo['estado'],
        'fecha_destape' => $tapazo['fecha_hora_destape'],
        'regla' => $tapazo['regla']
    ]) . "\n\n";
    flush();

    // Si ya está destapando o finalizado
    if (in_array($tapazo['estado'], ['destapando', 'finalizado'])) {
        // No enviar todos los jugadores de una vez - el cliente hace polling
        echo "data: " . json_encode([
            'type' => 'init',
            'estado' => $tapazo['estado'],
            'regla' => $tapazo['regla']
        ]) . "\n\n";
        flush();
        
        // Mantener conexión viva para recibir actualizaciones
        $maxWait = 3600;
        $waited = 0;
        
        while ($waited < $maxWait) {
            $stmt = $db->prepare("SELECT estado FROM tapazos WHERE id = ?");
            $stmt->execute([$tapazoId]);
            $currentEstado = $stmt->fetchColumn();

            if ($currentEstado === 'finalizado') {
                echo "data: " . json_encode(['type' => 'finalizado', 'regla' => $tapazo['regla']]) . "\n\n";
                flush();
                exit;
            }

            sleep(2);
            $waited += 2;

            if (connection_aborted()) exit;
        }
        exit;
    }

    // Mantener conexión viva para countdown
    $maxWait = 3600;
    $waited = 0;

    while ($waited < $maxWait) {
        $stmt = $db->prepare("SELECT estado FROM tapazos WHERE id = ?");
        $stmt->execute([$tapazoId]);
        $currentEstado = $stmt->fetchColumn();

        if ($currentEstado === 'destapando' || $currentEstado === 'finalizado') {
            echo "data: " . json_encode(['type' => 'init', 'estado' => $currentEstado]) . "\n\n";
            flush();
            exit;
        }

        $now = time();
        $remaining = max(0, $destapeTime - $now);
        
        // Si llegó la hora, iniciar destape automáticamente
        if ($remaining <= 0 && $currentEstado === 'esperando') {
            $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ?");
            $stmt->execute([$tapazoId]);
            $jugadores = $stmt->fetchAll();
            
            if (!empty($jugadores)) {
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
                $stmt->execute([$tapazoId]);
                
                echo "data: " . json_encode([
                    'type' => 'init',
                    'estado' => 'destapando',
                    'regla' => $tapazo['regla']
                ]) . "\n\n";
                flush();
                exit;
            }
        }
        
        echo "data: " . json_encode([
            'type' => 'countdown',
            'remaining' => $remaining
        ]) . "\n\n";
        flush();

        sleep(2);
        $waited += 2;

        if (connection_aborted()) exit;
    }

} catch (Exception $e) {
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    flush();
}
