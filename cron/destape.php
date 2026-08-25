<?php
/**
 * CRON: Destape automático de Tapazos
 * Ejecutar cada minuto: * * * * * php /path/to/cron_destape.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== CRON Destape Tapazo - " . date('Y-m-d H:i:s') . " ===\n";
    
    // Buscar tapazos en estado 'esperando' cuya hora ya pasó
    $stmt = $db->prepare("
        SELECT id, titulo, cantidad_jugadores 
        FROM tapazos 
        WHERE estado = 'esperando' 
        AND fecha_hora_destape <= NOW()
    ");
    $stmt->execute();
    $tapazos = $stmt->fetchAll();
    
    if (empty($tapazos)) {
        echo "No hay tapazos pendientes de destape.\n";
        exit;
    }
    
    foreach ($tapazos as $tapazo) {
        echo "Procesando: {$tapazo['titulo']} (ID: {$tapazo['id']})\n";
        
        // Obtener jugadores
        $stmt = $db->prepare("SELECT id FROM tapazo_jugadores WHERE tapazo_id = ?");
        $stmt->execute([$tapazo['id']]);
        $jugadores = $stmt->fetchAll();
        
        if (empty($jugadores)) {
            echo "  [WARN] Sin jugadores, saltando.\n";
            continue;
        }
        
        // Generar números aleatorios únicos
        $maxTapas = 999;
        $numeros = range(1, $maxTapas);
        shuffle($numeros);
        
        // Generar orden aleatorio
        $ordenes = range(1, count($jugadores));
        shuffle($ordenes);
        
        // Asignar números y orden a cada jugador
        foreach ($jugadores as $idx => $jugador) {
            $stmt = $db->prepare("
                UPDATE tapazo_jugadores 
                SET numero_tapa = ?, orden_destape = ? 
                WHERE id = ?
            ");
            $stmt->execute([$numeros[$idx], $ordenes[$idx], $jugador['id']]);
        }
        
        // Cambiar estado a destapando
        $stmt = $db->prepare("UPDATE tapazos SET estado = 'destapando' WHERE id = ?");
        $stmt->execute([$tapazo['id']]);
        
        echo "  [OK] Destape iniciado para {$tapazo['id']}\n";
    }
    
    echo "=== FIN ===\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
