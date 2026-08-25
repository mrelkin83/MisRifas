<?php
/**
 * Cron Job: Procesar Destapes Automáticos de Tapazos
 * Frecuencia: Cada 10 segundos
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if ($cronSecret !== $config['cron']['secret_key']) {
        http_response_code(403);
        die('Forbidden');
    }
}

try {
    $db = Database::getInstance()->getConnection();
    $now = time();
    
    // Buscar tapazos en estado 'creado' o 'esperando' donde ya pasó la fecha de destape
    $stmt = $db->prepare("
        SELECT id, cantidad_jugadores 
        FROM tapazos 
        WHERE estado IN ('creado', 'esperando') 
        AND fecha_hora_destape <= NOW()
    ");
    $stmt->execute();
    $tapazos = $stmt->fetchAll();
    
    foreach ($tapazos as $tapazo) {
        try {
            $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ?");
            $stmt->execute([$tapazo['id']]);
            $jugadores = $stmt->fetchAll();
            
            if (empty($jugadores)) {
                continue;
            }
            
            // Asignar números aleatorios a las tapas
            $maxTapas = 999;
            $numeros = range(1, $maxTapas);
            shuffle($numeros);
            
            $ordenes = range(1, count($jugadores));
            shuffle($ordenes);
            
            foreach ($jugadores as $idx => $jugador) {
                $stmt = $db->prepare("UPDATE tapazo_jugadores SET numero_tapa = ?, orden_destape = ? WHERE id = ?");
                $stmt->execute([$numeros[$idx], $ordenes[$idx], $jugador['id']]);
            }
            
            // Iniciar destape automáticamente
            $stmt = $db->prepare("UPDATE tapazos SET estado = 'destapando', ultimo_revelado = '' WHERE id = ?");
            $stmt->execute([$tapazo['id']]);
            
            echo "Destape iniciado para tapazo ID: {$tapazo['id']}\n";
            
        } catch (Exception $e) {
            echo "Error en tapazo {$tapazo['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    if (empty($tapazos)) {
        echo "No hay tapazos para destapar\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
