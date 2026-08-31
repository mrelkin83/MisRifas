<?php

declare(strict_types=1);

/**
 * CLI: Auto-diagnóstico del sistema — el "looping" contra afirmaciones falsas.
 * Verifica EN VIVO cada canal (SMTP real por socket, Evolution por API, gammu
 * por binario, settings por BD) en lugar de asumir que funcionan.
 *
 *   php tools/diagnostico.php            → reporte legible
 *   php tools/diagnostico.php --json     → JSON (para scripts)
 *
 * Sale con código 1 si hay algún FAIL (útil para correrlo tras cada deploy).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/services/SystemStatus.php';
restore_exception_handler(); // el handler web silencia fatales en CLI

$checks = SystemStatus::checks(Database::getInstance()->getConnection());

if (in_array('--json', $argv, true)) {
    echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} else {
    $icons = ['ok' => '✅', 'warn' => '⚠️ ', 'fail' => '❌'];
    echo "═══ Diagnóstico MisRifas — " . date('Y-m-d H:i:s') . " ═══\n\n";
    foreach ($checks as $c) {
        echo $icons[$c['estado']] . ' ' . $c['nombre'] . "\n";
        echo '   ' . $c['detalle'] . "\n";
        if ($c['arreglo'] !== '') {
            echo '   → Arreglo: ' . $c['arreglo'] . "\n";
        }
        echo "\n";
    }
    $fails = count(array_filter($checks, fn($c) => $c['estado'] === 'fail'));
    $warns = count(array_filter($checks, fn($c) => $c['estado'] === 'warn'));
    echo "Resumen: " . count($checks) . " checks, $fails fallo(s), $warns advertencia(s).\n";
}

exit(count(array_filter($checks, fn($c) => $c['estado'] === 'fail')) > 0 ? 1 : 0);
