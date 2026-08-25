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
        "SELECT mq.*, v.wa_config, v.business_name as vendor_name
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

function processWhatsApp($msg) {
    $waConfig = json_decode($msg['wa_config'] ?? '{}', true);

    $instance = $waConfig['evo_instance'] ?? '';
    $apiUrl = $waConfig['evo_api_url'] ?? '';
    $apiKey = $waConfig['evo_api_key'] ?? '';

    if (empty($instance) || empty($apiUrl) || empty($apiKey)) {
        Logger::error('WhatsApp: vendor sin configuracion EvolutionAPI', ['vendor_id' => $msg['vendor_id']]);
        return false;
    }

    $type = $msg['type'] ?? 'generic';
    $data = json_decode($msg['data'] ?? '{}', true);
    $success = false;

    switch ($type) {
        case 'reservation':
            $templateName = $data['template_name'] ?? 'ticket_reserved_v1';
            $params = $data['params'] ?? [];
            $success = sendTemplateWhatsApp($apiUrl, $instance, $apiKey, $msg['recipient_phone'], $templateName, $params);
            break;

        case 'payment_confirmed':
            $templateName = $data['template_name'] ?? 'payment_confirmed_v1';
            $params = $data['params'] ?? [];
            $success = sendTemplateWhatsApp($apiUrl, $instance, $apiKey, $msg['recipient_phone'], $templateName, $params);
            break;

        case 'winner':
            $templateName = $data['template_name'] ?? 'winner_notification_v1';
            $params = $data['params'] ?? [];
            $success = sendTemplateWhatsApp($apiUrl, $instance, $apiKey, $msg['recipient_phone'], $templateName, $params);
            break;

        case 'no_winner':
            $templateName = $data['template_name'] ?? 'no_winner_v1';
            $params = $data['params'] ?? [];
            $success = sendTemplateWhatsApp($apiUrl, $instance, $apiKey, $msg['recipient_phone'], $templateName, $params);
            break;

        case 'vendor_winner':
            $templateName = $data['template_name'] ?? 'vendor_winner_v1';
            $params = $data['params'] ?? [];
            $success = sendTemplateWhatsApp($apiUrl, $instance, $apiKey, $msg['recipient_phone'], $templateName, $params);
            break;

        default:
            $success = sendTextWhatsApp($apiUrl, $instance, $apiKey, $msg['recipient_phone'], $msg['body_text']);
            break;
    }

    if ($success) {
        Logger::info('WhatsApp sent', [
            'number' => $msg['recipient_phone'],
            'type' => $type,
            'message_id' => $msg['id']
        ]);
    }

    return $success;
}

function sendTemplateWhatsApp($apiUrl, $instance, $apiKey, $number, $templateName, $params) {
    $url = "{$apiUrl}/message/sendText/{$instance}";
    $payload = json_encode([
        'number' => $number,
        'options' => [
            'delay' => 1200,
            'presence' => 'composing'
        ],
        'textMessage' => [
            'template' => $templateName,
            'templateParams' => $params
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    Logger::error('WhatsApp template send failed', [
        'number' => $number,
        'template' => $templateName,
        'http_code' => $httpCode,
        'response' => $response
    ]);
    return false;
}

function sendTextWhatsApp($apiUrl, $instance, $apiKey, $number, $text) {
    $url = "{$apiUrl}/message/sendText/{$instance}";
    $payload = json_encode([
        'number' => $number,
        'options' => [
            'delay' => 1200,
            'presence' => 'composing'
        ],
        'textMessage' => [
            'text' => $text
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    Logger::error('WhatsApp text send failed', [
        'number' => $number,
        'http_code' => $httpCode,
        'response' => $response
    ]);
    return false;
}

function processEmail($msg) {
    $toEmail = $msg['recipient'];
    $toName = $msg['subject'] ?? 'Usuario';
    $subject = $msg['subject'];
    $body = $msg['message'];

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $html = buildEmailHtml($subject, $body);

    try {
        $emailService = \api\services\EmailService::getInstance();
        $emailService->sendEmail($toEmail, $toName, $subject, $html);
        Logger::info('Email sent via BillionMail', ['to' => $toEmail, 'subject' => $subject]);
        return true;
    } catch (Exception $e) {
        Logger::error('Email send failed (BillionMail)', [
            'to' => $toEmail,
            'error' => $e->getMessage()
        ]);
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
