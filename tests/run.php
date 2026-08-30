<?php
/**
 * Runner de la suite de regresión.  Uso:  php tests/run.php
 *
 * Descubre y ejecuta tests/regression/*.php contra el sitio LOCAL, luego
 * limpia todos los fixtures y reporta un resumen. Sale con código != 0 si
 * algún test falla (apto para CI).
 */

require_once __DIR__ . '/bootstrap.php';

// Red de seguridad: pase lo que pase (incluido un fatal), limpiar fixtures.
register_shutdown_function('runTeardown');

echo "MisRifas — suite de regresión\n";
echo "Objetivo: " . TEST_BASE_URL . "\n";

$files = glob(__DIR__ . '/regression/*.php');
sort($files);

foreach ($files as $file) {
    try {
        require $file;
    } catch (\Throwable $e) {
        fail('Excepción en ' . basename($file) . ': ' . $e->getMessage());
    }
}

runTeardown();

$r = $GLOBALS['__tests'];
echo "\n" . str_repeat('─', 48) . "\n";
echo "RESULTADO: \033[32m{$r['pass']} PASS\033[0m, "
   . ($r['fail'] > 0 ? "\033[31m{$r['fail']} FAIL\033[0m" : "0 FAIL") . "\n";
if ($r['fail'] > 0) {
    echo "\nFallos:\n";
    foreach ($r['fails'] as $f) echo "  - $f\n";
}
exit($r['fail'] > 0 ? 1 : 0);
