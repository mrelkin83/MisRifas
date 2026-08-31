<?php
/**
 * Cron diario: vinculación de WhatsApp del organizador.
 *
 * 1) RECORDATORIO (D-5 a D-3): a cada organizador con una rifa activa que
 *    juega en 3-5 días, con notificaciones automáticas activadas y SIN
 *    WhatsApp vinculado, se le envía UN correo invitándolo a escanear el QR
 *    para que el día del sorteo el sistema avise a sus compradores también
 *    por WhatsApp (el correo va siempre).
 *
 * 2) DESVINCULACIÓN (D+1): si un organizador quedó con el WhatsApp conectado
 *    y ya no tiene rifas activas pendientes de sorteo (la última jugó hace
 *    al menos un día), su instancia se desconecta sola. Cada rifa nueva
 *    repite el flujo de vinculación. Los super_admin quedan exentos (su
 *    instancia corre el módulo de IA de la plataforma).
 *
 * Programación sugerida: 0 8 * * * (una vez al día).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';

if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if (empty($cronSecret) || $cronSecret !== ($config['cron']['secret_key'] ?? '')) {
        http_response_code(403);
        die('Forbidden');
    }
}

$db = Database::getInstance()->getConnection();
$recordatorios = 0;
$desvinculados = 0;

// ── 1. Recordatorios de vinculación ─────────────────────────────────────────
try {
    $stmt = $db->query("
        SELECT r.id AS raffle_id, v.id, v.business_name, v.email, r.name AS raffle_name,
               DATE(r.draw_date) AS draw_day
        FROM raffles r
        JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
        WHERE r.status = 'active'
          AND r.auto_notify = 1
          AND r.draw_date BETWEEN DATE_ADD(NOW(), INTERVAL 3 DAY) AND DATE_ADD(NOW(), INTERVAL 5 DAY)
          AND v.email IS NOT NULL AND v.email <> ''
          AND NOT EXISTS (
              SELECT 1 FROM wa_config wc
              WHERE wc.vendor_id = v.id AND wc.estado_conexion = 'conectado'
          )
          AND NOT EXISTS (
              SELECT 1 FROM message_queue mq
              WHERE mq.vendor_id = v.id
                AND mq.message_type = 'draw_reminder'
                AND mq.recipient_email = v.email
                AND mq.created_at > DATE_SUB(NOW(), INTERVAL 4 DAY)
          )
    ");
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $perfilUrl = $appUrl . BASE_PATH . '/public/vendor/index.php#mi-perfil';

    $ins = $db->prepare("
        INSERT INTO message_queue
            (raffle_id, vendor_id, recipient_user_id, recipient_phone, recipient_email,
             channel, message_type, subject, body_text, body_html, variables, status, scheduled_at, created_at)
        VALUES (?, ?, NULL, NULL, ?, 'email', 'draw_reminder', ?, ?, NULL, NULL, 'pending', NOW(), NOW())
    ");

    $yaAvisados = [];
    foreach ($pendientes as $v) {
        // Un solo recordatorio por organizador aunque tenga varias rifas en la ventana.
        if (isset($yaAvisados[$v['id']])) {
            continue;
        }
        $yaAvisados[$v['id']] = true;
        $fecha = date('d/m/Y', strtotime($v['draw_day']));
        $texto = "Hola {$v['business_name']},\n\n"
            . "Tu rifa \"{$v['raffle_name']}\" juega el {$fecha}. Los resultados se enviaran a tus compradores por correo automaticamente.\n\n"
            . "¿Quieres que ADEMAS les llegue por WhatsApp desde TU numero? Solo tienes que vincularlo escaneando un codigo QR (2 minutos):\n"
            . "{$perfilUrl}\n\n"
            . "El dia del sorteo, el sistema verificara el numero ganador y enviara a todos tus clientes el resultado, felicitando al ganador y agradeciendo la participacion. Despues del sorteo puedes desvincularlo (o se desvincula solo).\n\n"
            . "— MisRifas";
        $ins->execute([
            $v['raffle_id'],
            $v['id'],
            $v['email'],
            '📱 Vincula tu WhatsApp para el sorteo del ' . $fecha,
            $texto,
        ]);
        $recordatorios++;
    }
} catch (Exception $e) {
    Logger::error('wa_link_reminders (recordatorios): ' . $e->getMessage());
}

// ── 1b. Recordatorios de cartera de apartados (§8.5: a 7, 3 y 1 días del corte) ──
$recordatoriosHolds = 0;
try {
    $stmt = $db->query("
        SELECT r.id AS raffle_id, r.name AS raffle_name, r.cutoff_at, r.ticket_price,
               COALESCE(r.vendor_id, r.created_by) AS vendor_id,
               v.business_name, v.email, v.phone,
               COUNT(t.id) AS cantidad,
               GROUP_CONCAT(CONCAT('#', t.ticket_number, ' (', t.holder_name, ')') SEPARATOR ', ') AS lista
        FROM raffles r
        JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
        JOIN tickets t ON t.raffle_id = r.id AND t.status = 'held'
        WHERE r.status = 'active' AND r.cutoff_at IS NOT NULL
          AND DATEDIFF(r.cutoff_at, NOW()) IN (7, 3, 1)
          AND NOT EXISTS (
              SELECT 1 FROM message_queue mq
              WHERE mq.raffle_id = r.id AND mq.vendor_id = COALESCE(r.vendor_id, r.created_by)
                AND mq.message_type = 'draw_reminder'
                AND mq.subject LIKE 'Apartados sin cobrar%'
                AND mq.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
          )
        GROUP BY r.id
    ");
    $ins = $db->prepare("
        INSERT INTO message_queue
            (raffle_id, vendor_id, recipient_phone, recipient_email, channel, message_type, subject, body_text, status, scheduled_at, created_at)
        VALUES (?, ?, ?, ?, ?, 'draw_reminder', ?, ?, 'pending', NOW(), NOW())
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $dias = (int)floor((strtotime((string)$c['cutoff_at']) - time()) / 86400) + 1;
        $total = number_format((float)$c['ticket_price'] * (int)$c['cantidad'], 0, ',', '.');
        $subject = 'Apartados sin cobrar en "' . $c['raffle_name'] . '" — corte en ' . $dias . ' día(s)';
        $texto = "Hola {$c['business_name']},\n\n"
            . "Tienes {$c['cantidad']} numero(s) APARTADOS sin cobrar en \"{$c['raffle_name']}\" por un total de \${$total}:\n"
            . $c['lista'] . "\n\n"
            . 'El corte es el ' . date('d/m/Y', strtotime((string)$c['cutoff_at'])) . ': lo que siga sin cobrar volvera a la venta.'
            . "\nDesde tu panel puedes marcar pagados, enviar recordatorios o liberar numeros.\n\n— MisRifas";
        // Email siempre; WhatsApp además si hay teléfono (lo entrega
        // process_notifications por la instancia del vendedor).
        if (!empty($c['email'])) {
            $ins->execute([$c['raffle_id'], $c['vendor_id'], null, $c['email'], 'email', $subject, $texto]);
            $recordatoriosHolds++;
        }
        if (!empty($c['phone'])) {
            $ins->execute([$c['raffle_id'], $c['vendor_id'], $c['phone'], null, 'whatsapp', $subject, $texto]);
        }
    }
} catch (Exception $e) {
    Logger::error('wa_link_reminders (cartera holds): ' . $e->getMessage());
}

// ── 2. Desvinculación automática post-sorteo ────────────────────────────────
try {
    $stmt = $db->query("
        SELECT wc.vendor_id
        FROM wa_config wc
        JOIN vendors v ON v.id = wc.vendor_id
        WHERE wc.estado_conexion = 'conectado'
          AND v.role <> 'super_admin'
          AND NOT EXISTS (
              SELECT 1 FROM raffles r
              WHERE COALESCE(r.vendor_id, r.created_by) = wc.vendor_id
                AND r.status = 'active'
          )
          AND EXISTS (
              SELECT 1 FROM raffles r2
              WHERE COALESCE(r2.vendor_id, r2.created_by) = wc.vendor_id
                AND r2.draw_date < DATE_SUB(NOW(), INTERVAL 1 DAY)
          )
    ");
    $paraDesvincular = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($paraDesvincular) {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../api/whatsapp/MisRifasDb.php';
        require_once __DIR__ . '/../api/whatsapp/MisRifasTenant.php';
        require_once __DIR__ . '/../api/whatsapp/MisRifasSecret.php';
        require_once __DIR__ . '/../api/whatsapp/MisRifasStorage.php';
        require_once __DIR__ . '/../api/whatsapp/RaffleDomainAdapter.php';
    }

    foreach ($paraDesvincular as $vendorId) {
        $vendorId = (int)$vendorId;
        try {
            \ElkinLinan\WhatsappAiEngine\Engine::reiniciar();
            \ElkinLinan\WhatsappAiEngine\Engine::arrancar([
                'db' => new MisRifasDb(),
                'dominio' => new RaffleDomainAdapter($vendorId),
                'archivo' => new MisRifasStorage($vendorId),
                'secreto' => new MisRifasSecret(),
                'negocio' => new MisRifasTenant($vendorId),
                'formato' => new \ElkinLinan\WhatsappAiEngine\Defecto\PesosColombianos(),
                'funcion' => new \ElkinLinan\WhatsappAiEngine\Defecto\TodoPermitido(),
                'config' => new \ElkinLinan\WhatsappAiEngine\Defecto\ConfigDeEntorno(),
            ]);
            $engineDb = \ElkinLinan\WhatsappAiEngine\Engine::db();
            $cli = \ElkinLinan\WhatsappAiEngine\Channel\EvolutionClient::desdeConfig($engineDb);
            if ($cli) {
                $cli->desconectar();
            }
            \ElkinLinan\WhatsappAiEngine\Core\WaConfig::guardar($engineDb, ['estado_conexion' => 'desconectado']);
            Logger::activity('wa_auto_unlink', $vendorId, ['motivo' => 'sin rifas activas, sorteo hace 1+ dia']);
            $desvinculados++;
        } catch (\Throwable $e) {
            Logger::error('wa_link_reminders (desvincular vendor ' . $vendorId . '): ' . $e->getMessage());
        }
    }
} catch (Exception $e) {
    Logger::error('wa_link_reminders (desvinculación): ' . $e->getMessage());
}

Logger::cron('wa_link_reminders', true, [
    'recordatorios' => $recordatorios,
    'cartera_holds' => $recordatoriosHolds,
    'desvinculados' => $desvinculados,
]);
echo "Recordatorios: {$recordatorios} | Cartera: {$recordatoriosHolds} | Desvinculados: {$desvinculados}\n";
