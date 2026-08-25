<?php
/**
 * Actualizar loterías en la BD - Ejecutar SOLO UNA VEZ
 * URL: http://localhost/MisRifas/update_lotteries.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

try {
    $db = Database::getInstance()->getConnection();

    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    $db->exec("TRUNCATE TABLE `lotteries`");
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    $lotteries = [
        ['Lotería de Cundinamarca', 'monday',    '22:30:00'],
        ['Lotería de Tolima',       'monday',    '23:00:00'],
        ['Lotería Cruz Roja',       'tuesday',   '22:30:00'],
        ['Lotería de Huila',        'tuesday',   '22:30:00'],
        ['Lotería de Manizales',    'wednesday', '22:30:00'],
        ['Lotería del Meta',        'wednesday', '22:30:00'],
        ['Lotería del Valle',       'wednesday', '22:30:00'],
        ['Lotería Quindío',         'thursday',  '22:30:00'],
        ['Lotería de Bogotá',       'thursday',  '22:30:00'],
        ['Lotería de Santander',    'friday',    '23:00:00'],
        ['Lotería de Medellín',     'friday',    '23:00:00'],
        ['Lotería Risaralda',       'friday',    '23:00:00'],
        ['Lotería de Boyacá',       'saturday',  '22:40:00'],
        ['Lotería de Cauca',        'saturday',  '21:40:00'],
        ['Extra de Colombia',       'saturday',  '23:00:00'],
    ];

    $stmt = $db->prepare("INSERT INTO `lotteries` (`name`, `day_of_week`, `draw_time`, `active`) VALUES (?, ?, ?, 1)");
    foreach ($lotteries as $l) {
        $stmt->execute($l);
    }

    echo "<h2 style='color:green;font-family:sans-serif'>✅ ¡Loterías actualizadas correctamente! (" . count($lotteries) . " loterías)</h2>";
    echo "<p style='font-family:sans-serif'><a href='/MisRifas/public/admin/index.php'>Ir al Panel Admin</a></p>";
    echo "<p style='color:red;font-family:sans-serif'><strong>Elimina este archivo después de ejecutarlo.</strong></p>";

} catch (Exception $e) {
    echo "<h2 style='color:red;font-family:sans-serif'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
