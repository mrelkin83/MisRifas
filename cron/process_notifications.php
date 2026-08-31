<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/services/SMSService.php';

if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if (empty($cronSecret) || $cronSecret !== ($config['cron']['secret_key'] ?? '')) {
        http_response_code(403);
        die('Forbidden');
    }
}

$startTime = microtime(true);
Logger::info("=== Iniciando: Process Message Queue ===");

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query(
        "SELECT mq.*, v.business_name as vendor_name
         FROM message_queue mq
         JOIN vendors v ON mq.vendor_id = v.id
         WHERE mq.status = 'pending'
         AND mq.scheduled_at <= NOW()
         AND mq.attempts < 3
         ORDER BY mq.vendor_id, mq.scheduled_at ASC
         LIMIT 100"
    );
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0;
    $failed = 0;

    foreach ($messages as $msg) {
        try {
            $success = false;

            if ($msg['channel'] === 'whatsapp') {
                $success = processWhatsApp($msg);
            } elseif ($msg['channel'] === 'email') {
                $success = processEmail($msg);
            } elseif ($msg['channel'] === 'sms') {
                $success = processSMS($msg);
            }

            if ($success) {
                $upd = $db->prepare("UPDATE message_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?");
                $upd->execute([$msg['id']]);
                $sent++;
            } else {
                $upd = $db->prepare("UPDATE message_queue SET status = 'failed', last_error = 'Delivery failed', attempts = attempts + 1 WHERE id = ?");
                $upd->execute([$msg['id']]);
                $failed++;
            }
        } catch (Exception $e) {
            $upd = $db->prepare("UPDATE message_queue SET status = 'failed', last_error = ?, attempts = attempts + 1 WHERE id = ?");
            $upd->execute([$e->getMessage(), $msg['id']]);
            $failed++;
            Logger::error('Message queue error: ' . $e->getMessage(), ['id' => $msg['id']]);
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('process_notifications', true, [
        'pending' => count($messages),
        'sent' => $sent,
        'failed' => $failed,
        'time' => $executionTime . 's'
    ]);

    echo "Cola: " . count($messages) . " | Enviados: {$sent} | Fallidos: {$failed} | Tiempo: {$executionTime}s\n";

} catch (Exception $e) {
    Logger::exception($e);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Envia via el motor whatsapp-engine (Channel\EvolutionClient), leyendo la
 * config del vendor desde wa_config - reemplaza el cURL manual a Evolution
 * que tenia este archivo antes (duplicaba lo que ya hace el motor).
 *
 * NOTA: la version anterior de esta funcion leia $msg['type']/$msg['data']/
 * $msg['wa_config'] - ninguna de esas 3 columnas existe en message_queue
 * (las reales son message_type/variables, y wa_config vive en su propia
 * tabla ahora, no en un JOIN a vendors). Bug preexistente, nunca antes
 * hizo un envio real exitoso. Corregido de paso.
 */
function processWhatsApp($msg) {
    require_once __DIR__ . '/../api/whatsapp/notify.php';

    $vendorId = (int)$msg['vendor_id'];
    $texto = whatsAppTextoDeMensaje($msg);
    $enviado = notificarWhatsAppVendor($vendorId, $msg['recipient_phone'], $texto);

    if ($enviado) {
        Logger::info('WhatsApp sent', [
            'number' => $msg['recipient_phone'],
            'type' => $msg['message_type'] ?? 'generic',
            'message_id' => $msg['id']
        ]);
    } else {
        Logger::error('WhatsApp send failed', ['number' => $msg['recipient_phone'], 'message_id' => $msg['id']]);
    }
    return $enviado;
}

/**
 * body_text ya viene compuesto por MessageBuilderService al encolar el
 * mensaje - no hace falta plantillas aparte (esas eran plantillas de
 * WhatsApp Business Cloud API, que no aplican: Evolution/Baileys manda
 * texto libre, no plantillas pre-aprobadas por Meta).
 */
function whatsAppTextoDeMensaje($msg) {
    if (!empty($msg['body_text'])) {
        return $msg['body_text'];
    }
    $vars = json_decode($msg['variables'] ?? '{}', true) ?: [];
    return $vars['texto'] ?? 'Tienes una notificacion de MisRifas.';
}

/**
 * Envía por el motor SMTP real (MailService, settings mailing_* — Postal en
 * prod). La versión anterior leía $msg['recipient']/$msg['message'] —
 * columnas que no existen en message_queue (las reales son recipient_email/
 * body_text) y usaba EmailService, que apunta a un servicio HTTP
 * ("mail-service:8080") que no está desplegado. Nunca envió un correo.
 */
function processEmail($msg) {
    $toEmail = $msg['recipient_email'] ?? '';
    $subject = !empty($msg['subject']) ? $msg['subject'] : 'Notificación de MisRifas';

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Los mensajes de resultado traen body_html ya armado por
    // MessageBuilderService; el resto usa la plantilla genérica.
    $html = !empty($msg['body_html'])
        ? $msg['body_html']
        : buildEmailHtml($subject, $msg['body_text'] ?? '');

    try {
        require_once __DIR__ . '/../api/services/MailService.php';
        $mail = new MailService();
        // body_text va como alternativa de texto plano (multipart/alternative).
        $ok = $mail->sendDirect($toEmail, $subject, $html, $msg['body_text'] ?? null);
        if ($ok) {
            Logger::info('Email sent', ['to' => $toEmail, 'subject' => $subject, 'message_id' => $msg['id']]);
        } else {
            Logger::error('Email send failed', ['to' => $toEmail, 'message_id' => $msg['id']]);
        }
        return (bool)$ok;
    } catch (Exception $e) {
        Logger::error('Email send failed', ['to' => $toEmail, 'error' => $e->getMessage()]);
        return false;
    }
}

function buildEmailHtml($subject, $body) {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#0f172a;font-family:sans-serif;">'
        . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;">'
        . '<div style="background:#2563eb;padding:24px;text-align:center;">'
        . '<h1 style="color:#fff;margin:0;font-size:24px;">MisRifas</h1>'
        . '</div>'
        . '<div style="padding:32px;">'
        . '<h2 style="color:#f1f5f9;margin:0 0 16px;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<div style="color:#94a3b8;line-height:1.6;">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '</div>'
        . '<div style="padding:16px;text-align:center;color:#64748b;font-size:12px;">'
        . 'MisRifas - Rifas Digitales Colombia'
        . '</div></div></body></html>';
}

function processSMS($msg) {
    $number = $msg['recipient_phone'] ?? '';
    $body = $msg['body_text'] ?? '';

    if (empty($number) || empty($body)) {
        Logger::error('SMS: recipient_phone o body_text vacios', ['id' => $msg['id']]);
        return false;
    }

    try {
        sendSMS($number, $body);
        Logger::info('SMS sent', ['number' => $number, 'message_id' => $msg['id']]);
        return true;
    } catch (Exception $e) {
        Logger::error('SMS send failed', ['error' => $e->getMessage(), 'id' => $msg['id']]);
        return false;
    }
}
