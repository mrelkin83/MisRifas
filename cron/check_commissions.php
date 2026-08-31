<?php

declare(strict_types=1);

/**
 * Cron diario: cobro de la plataforma al vendedor (promt2.md §15).
 *
 * 1) CALENDARIO DE AVISOS (§15.2): antes de commission_due_date, a 7, 3, 2 y
 *    1 días. Idempotente: cada aviso queda en message_queue (type
 *    payment_reminder) con su umbral en el subject; un umbral consumido no se
 *    repite aunque el cron corra dos veces. Si la rifa nació con menos de 7
 *    días de margen, se envía de inmediato el primer aviso aplicable y los
 *    umbrales ya vencidos se consumen sin enviarse.
 *
 * 2) CONSECUENCIA DEL IMPAGO (§15.3): al vencer commission_due_date sin pago:
 *    - raffles.sales_blocked = 1 (solo VENTAS NUEVAS de esa rifa)
 *    - se le bloquea al vendedor crear rifas nuevas (gate en create.php)
 *    - la rifa muestra públicamente el saldo pendiente
 *    Lo que JAMÁS se bloquea: el sorteo y su reprogramación, los tickets
 *    pagados y sus boletas, el registro del ganador y la entrega. El castigo
 *    recae en el vendedor, nunca en el comprador. (La versión anterior de
 *    este cron ponía status='blocked' — eso congelaba el sorteo y atrapaba a
 *    quienes ya pagaron; este cron además repara esas rifas.)
 *
 * 3) §15.4: con commission_enabled = 0 no se encola nada.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';
require_once __DIR__ . '/../api/utils/Logger.php';

if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if ($cronSecret !== ($config['cron']['secret_key'] ?? '')) {
        http_response_code(403);
        die('Forbidden');
    }
}

$startTime = microtime(true);
Logger::info('=== Iniciando: Cobro de plataforma (avisos + mora) ===');

try {
    $db = Database::getInstance()->getConnection();

    $setting = function (string $key, string $default = '') use ($db): string {
        $s = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return $v === false ? $default : (string)$v;
    };

    // §15.4: interruptor maestro apagado → nada que cobrar ni avisar.
    if (!(int)$setting('commission_enabled', '0')) {
        echo "Cobro desactivado (commission_enabled=0): sin avisos.\n";
        exit(0);
    }

    $mode = $setting('billing_mode', 'commission') === 'talonario' ? 'talonario' : 'commission';
    $concepto = $mode === 'talonario' ? 'tarifa por talonario' : 'comisión de la plataforma';
    $comoPagar = $setting('billing_payment_instructions', 'Contacta al administrador de la plataforma.');

    $ins = $db->prepare("
        INSERT INTO message_queue
            (raffle_id, vendor_id, recipient_phone, recipient_email, channel, message_type, subject, body_text, status, scheduled_at, created_at)
        VALUES (?, ?, ?, ?, ?, 'payment_reminder', ?, ?, 'pending', NOW(), NOW())
    ");
    $enviar = function (array $r, string $subject, string $texto) use ($ins) {
        if (!empty($r['vendor_email'])) {
            $ins->execute([$r['id'], $r['owner_id'], null, $r['vendor_email'], 'email', $subject, $texto]);
        }
        if (!empty($r['vendor_phone'])) {
            $ins->execute([$r['id'], $r['owner_id'], $r['vendor_phone'], null, 'whatsapp', $subject, $texto]);
        }
    };

    // ── 0) Reparar rifas congeladas por la versión anterior del cron ──
    $reparadas = $db->exec("
        UPDATE raffles SET status = 'active', sales_blocked = 1
        WHERE status = 'blocked' AND blocked_reason = 'Comisión no pagada'
          AND commission_paid = 0
    ");

    // ── 1) Calendario de avisos 7/3/2/1 ──
    $stmt = $db->query("
        SELECT r.id, r.name, r.commission_amount, r.commission_due_date,
               COALESCE(r.vendor_id, r.created_by) AS owner_id,
               v.business_name, v.email AS vendor_email, v.phone AS vendor_phone,
               DATEDIFF(r.commission_due_date, NOW()) AS days_left
        FROM raffles r
        JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
        WHERE r.commission_paid = 0
          AND r.commission_amount > 0
          AND r.commission_due_date IS NOT NULL
          AND r.commission_due_date > NOW()
          AND r.status IN ('active', 'pending_reschedule')
          AND DATEDIFF(r.commission_due_date, NOW()) <= 7
    ");
    $avisos = 0;
    $umbrales = [7, 3, 2, 1];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $daysLeft = max(0, (int)$r['days_left']);

        // Umbrales ya consumidos (registrados en la cola con su tag).
        $q = $db->prepare("
            SELECT subject FROM message_queue
            WHERE raffle_id = ? AND message_type = 'payment_reminder' AND subject LIKE '%[cobro:%'
        ");
        $q->execute([$r['id']]);
        $consumidos = [];
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $subj) {
            if (preg_match('/\[cobro:([0-9,t]+)\]/', (string)$subj, $m)) {
                foreach (explode(',', $m[1]) as $tag) {
                    $consumidos[(int)ltrim($tag, 't')] = true;
                }
            }
        }

        // Umbrales que aplican hoy (>= días restantes) y no se han consumido;
        // se envía UN aviso y se consumen todos a la vez — así una rifa creada
        // con poco margen recibe de inmediato el primero aplicable sin
        // acumular avisos atrasados.
        $pendientes = array_values(array_filter($umbrales, fn($t) => $t >= $daysLeft && empty($consumidos[$t])));
        if (!$pendientes) {
            continue;
        }
        $tag = '[cobro:' . implode(',', array_map(fn($t) => 't' . $t, $pendientes)) . ']';
        $monto = number_format((float)$r['commission_amount'], 0, ',', '.');
        $fecha = date('d/m/Y', strtotime((string)$r['commission_due_date']));
        $texto = "Hola {$r['business_name']},\n\n"
            . "Recordatorio de pago a la plataforma por tu rifa \"{$r['name']}\":\n"
            . "• Concepto: {$concepto}\n• Monto: \${$monto}\n• Fecha límite: {$fecha} (en {$daysLeft} día(s))\n\n"
            . "Cómo pagar: {$comoPagar}\n\n"
            . "Si no se paga antes de la fecha límite, se suspenden las ventas nuevas de la rifa y la creación de rifas nuevas. El sorteo y los boletos ya pagados NUNCA se afectan.\n\n— " . plataforma('nombre') . " {$tag}";
        $enviar($r, "Pago a la plataforma — \"{$r['name']}\" vence el {$fecha} {$tag}", $texto);
        $avisos++;
    }

    // ── 2) Mora: vencidas sin pagar → sales_blocked (§15.3) ──
    $stmt = $db->query("
        SELECT r.id, r.name, r.commission_amount, r.commission_due_date, r.sales_blocked,
               COALESCE(r.vendor_id, r.created_by) AS owner_id,
               v.business_name, v.email AS vendor_email, v.phone AS vendor_phone
        FROM raffles r
        JOIN vendors v ON v.id = COALESCE(r.vendor_id, r.created_by)
        WHERE r.commission_paid = 0
          AND r.commission_amount > 0
          AND r.commission_due_date IS NOT NULL
          AND r.commission_due_date <= NOW()
          AND r.status IN ('active', 'pending_reschedule')
    ");
    $bloqueadas = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ((int)$r['sales_blocked'] === 1) {
            continue; // ya en mora, avisada
        }
        $db->prepare('UPDATE raffles SET sales_blocked = 1 WHERE id = ?')->execute([$r['id']]);
        $bloqueadas++;
        Logger::warning('Rifa en mora: ventas nuevas suspendidas (el sorteo sigue)', [
            'raffle_id' => $r['id'], 'vendor_id' => $r['owner_id'],
        ]);
        $monto = number_format((float)$r['commission_amount'], 0, ',', '.');
        $texto = "⚠️ Tu rifa \"{$r['name']}\" entró en MORA con la plataforma ({$concepto}: \${$monto}).\n\n"
            . "Desde hoy:\n• Las VENTAS NUEVAS de esa rifa quedan suspendidas\n• No puedes crear rifas nuevas\n• La rifa muestra públicamente el saldo pendiente\n\n"
            . "El sorteo se realizará igual y los boletos ya pagados no se afectan.\n\nCómo pagar: {$comoPagar}\nAl pagar, todo se reactiva. — " . plataforma('nombre') . " [cobro:mora]";
        $enviar($r, "⚠️ Rifa \"{$r['name']}\" en mora — ventas suspendidas [cobro:mora]", $texto);
    }

    $executionTime = round(microtime(true) - $startTime, 2);
    Logger::cron('check_commissions', true, [
        'avisos' => $avisos, 'en_mora' => $bloqueadas,
        'reparadas_legacy' => (int)$reparadas, 'time' => $executionTime . 's',
    ]);
    echo "Avisos: {$avisos} | En mora: {$bloqueadas} | Reparadas: " . (int)$reparadas . " | {$executionTime}s\n";
} catch (Exception $e) {
    Logger::exception($e);
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
