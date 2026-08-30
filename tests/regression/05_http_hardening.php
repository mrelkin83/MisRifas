<?php
/** Directorios sensibles no deben ser servibles por HTTP (.htaccess). */

section('Hardening HTTP — rutas sensibles bloqueadas');
foreach ([
    '/api/cron/process_emails.php'    => 'cron de correos',
    '/api/workers/process_queue.php'  => 'worker de cola',
    '/api/utils/Auth.php'             => 'utilidades internas',
    '/config/database.php'            => 'config',
    '/tests/bootstrap.php'            => 'harness de tests',
] as $path => $label) {
    $res = httpGet($path);
    check($res['code'] === 403 || $res['code'] === 404,
        "«$label» no es accesible por HTTP ($path)", "HTTP {$res['code']}");
}
