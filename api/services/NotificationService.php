<?php

require_once __DIR__ . '/../repositories/NotificationRepository.php';

/**
 * NotificationService
 *
 * Procesa y envía notificaciones a través de múltiples canales:
 * - WhatsApp (usando Twilio o Meta WhatsApp Business API)
 * - Email (usando PHPMailer)
 *
 * Procesa la cola de notificaciones y maneja reintentos
 */
class NotificationService
{
    private $notificationRepo;
    private $whatsappProvider;
    private $whatsappToken;
    private $whatsappPhoneId;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpFromEmail;
    private $smtpFromName;

    public function __construct()
    {
        $this->notificationRepo = new NotificationRepository();

        // Configuración WhatsApp (Twilio o Meta)
        $this->whatsappProvider = $_ENV['WHATSAPP_PROVIDER'] ?? 'twilio'; // 'twilio' o 'meta'
        $this->whatsappToken = $_ENV['WHATSAPP_API_TOKEN'] ?? '';
        $this->whatsappPhoneId = $_ENV['WHATSAPP_PHONE_ID'] ?? '';

        // Configuración SMTP
        $this->smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtpPort = intval($_ENV['SMTP_PORT'] ?? 587);
        $this->smtpUsername = $_ENV['SMTP_USERNAME'] ?? '';
        $this->smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->smtpFromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@misrifas.com';
        $this->smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? 'MisRifas Colombia';
    }

    /**
     * Procesar cola de notificaciones
     * Llamado por cron cada X minutos
     */
    public function processQueue(int $batchSize = 50): array
    {
        $notifications = $this->notificationRepo->getPendingNotifications($batchSize);
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($notifications as $notification) {
            $results['processed']++;

            try {
                $sent = false;

                if ($notification['channel'] === NOTIFICATION_CHANNEL_WHATSAPP) {
                    $sent = $this->sendWhatsApp($notification);
                } elseif ($notification['channel'] === NOTIFICATION_CHANNEL_EMAIL) {
                    $sent = $this->sendEmail($notification);
                }

                if ($sent) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'notification_id' => $notification['id'],
                    'error' => $e->getMessage()
                ];

                Logger::error('Error enviando notificación', [
                    'notification_id' => $notification['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        Logger::debug('Cola de notificaciones procesada', $results);

        return $results;
    }

    /**
     * Enviar mensaje por WhatsApp (público para usar desde cron)
     */
    public function sendWhatsAppMessage(string $phone, string $message): bool
    {
        $notification = [
            'id' => 0,
            'user_id' => 0,
            'channel' => NOTIFICATION_CHANNEL_WHATSAPP,
            'recipient' => $phone,
            'subject' => '',
            'message' => $message,
        ];
        
        return $this->sendWhatsApp($notification);
    }

    /**
     * Enviar mensaje por WhatsApp
     */
    private function sendWhatsApp(array $notification): bool
    {
        if ($this->whatsappProvider === 'meta') {
            return $this->sendWhatsAppMeta($notification);
        } else {
            return $this->sendWhatsAppTwilio($notification);
        }
    }

    /**
     * Enviar WhatsApp usando Meta WhatsApp Business API
     */
    private function sendWhatsAppMeta(array $notification): bool
    {
        try {
            $url = "https://graph.facebook.com/v18.0/{$this->whatsappPhoneId}/messages";

            $phone = $this->formatPhoneForWhatsApp($notification['recipient']);

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'body' => $notification['message']
                ]
            ];

            $response = $this->makeHttpRequest($url, $payload, [
                'Authorization: Bearer ' . $this->whatsappToken,
                'Content-Type: application/json'
            ]);

            if (isset($response['messages']) && count($response['messages']) > 0) {
                $this->notificationRepo->markAsSent($notification['id'], $response);
                return true;
            } else {
                $error = $response['error']['message'] ?? 'Error desconocido';
                $this->notificationRepo->markAsFailed($notification['id'], $error);
                return false;
            }

        } catch (Exception $e) {
            $this->notificationRepo->markAsFailed($notification['id'], $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar WhatsApp usando Twilio
     */
    private function sendWhatsAppTwilio(array $notification): bool
    {
        try {
            $accountSid = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
            $authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
            $fromPhone = $_ENV['TWILIO_WHATSAPP_FROM'] ?? '';

            $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

            $phone = $this->formatPhoneForWhatsApp($notification['recipient']);

            $payload = [
                'From' => "whatsapp:{$fromPhone}",
                'To' => "whatsapp:{$phone}",
                'Body' => $notification['message']
            ];

            $response = $this->makeHttpRequest(
                $url,
                http_build_query($payload),
                [
                    'Authorization: Basic ' . base64_encode("{$accountSid}:{$authToken}"),
                    'Content-Type: application/x-www-form-urlencoded'
                ],
                false
            );

            if (isset($response['sid'])) {
                $this->notificationRepo->markAsSent($notification['id'], $response);
                return true;
            } else {
                $error = $response['message'] ?? 'Error desconocido';
                $this->notificationRepo->markAsFailed($notification['id'], $error);
                return false;
            }

        } catch (Exception $e) {
            $this->notificationRepo->markAsFailed($notification['id'], $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar Email usando SMTP nativo de PHP
     */
    private function sendEmail(array $notification): bool
    {
        try {
            $to = $notification['recipient'];
            $subject = $notification['subject'];
            $message = $this->formatEmailMessage($notification['message']);

            // Headers
            $headers = [
                'From' => "{$this->smtpFromName} <{$this->smtpFromEmail}>",
                'Reply-To' => $this->smtpFromEmail,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Mailer' => 'PHP/' . phpversion()
            ];

            $headersString = implode("\r\n", array_map(
                fn($k, $v) => "{$k}: {$v}",
                array_keys($headers),
                $headers
            ));

            // Configurar SMTP
            ini_set('SMTP', $this->smtpHost);
            ini_set('smtp_port', $this->smtpPort);

            // Enviar email
            $sent = mail($to, $subject, $message, $headersString);

            if ($sent) {
                $this->notificationRepo->markAsSent($notification['id'], ['sent_at' => date('Y-m-d H:i:s')]);
                return true;
            } else {
                $this->notificationRepo->markAsFailed($notification['id'], 'mail() retornó false');
                return false;
            }

        } catch (Exception $e) {
            $this->notificationRepo->markAsFailed($notification['id'], $e->getMessage());
            return false;
        }
    }

    /**
     * Formatear mensaje de email a HTML
     */
    private function formatEmailMessage(string $plainText): string
    {
        $escapedText = htmlspecialchars($plainText);
        $htmlText = nl2br($escapedText);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎟️ MisRifas Colombia</h1>
        </div>
        <div class="content">
            {$htmlText}
        </div>
        <div class="footer">
            <p>&copy; 2026 MisRifas Colombia. Todos los derechos reservados.</p>
            <p>Este es un correo automático, por favor no responder.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Formatear número de teléfono para WhatsApp
     * Formato: +57XXXXXXXXXX (código país + número sin espacios)
     */
    private function formatPhoneForWhatsApp(string $phone): string
    {
        // Eliminar espacios, guiones, paréntesis
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Si empieza con 0, quitarlo
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        // Si no tiene código de país, agregar +57 (Colombia)
        if (strlen($phone) === 10) {
            $phone = '57' . $phone;
        }

        // Agregar + si no lo tiene
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Realizar petición HTTP
     */
    private function makeHttpRequest(string $url, $payload, array $headers, bool $jsonPayload = true): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload ? json_encode($payload) : $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Error en petición HTTP: {$error}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Respuesta no es JSON válido: {$response}");
        }

        return $decoded;
    }

    /**
     * Enviar notificación inmediata (bypass queue)
     * Útil para notificaciones críticas
     */
    public function sendImmediate(int $userId, string $channel, string $recipient, string $subject, string $message, array $data = []): bool
    {
        $notification = [
            'id' => 0, // No existe en BD aún
            'user_id' => $userId,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $message,
            'data' => json_encode($data)
        ];

        if ($channel === NOTIFICATION_CHANNEL_WHATSAPP) {
            return $this->sendWhatsApp($notification);
        } elseif ($channel === NOTIFICATION_CHANNEL_EMAIL) {
            return $this->sendEmail($notification);
        }

        return false;
    }

    /**
     * Test de conectividad WhatsApp
     */
    public function testWhatsAppConnection(): array
    {
        try {
            $testNotification = [
                'id' => 0,
                'recipient' => $_ENV['TEST_WHATSAPP_NUMBER'] ?? '',
                'message' => 'Test de conexión - MisRifas'
            ];

            if (empty($testNotification['recipient'])) {
                return ['success' => false, 'error' => 'TEST_WHATSAPP_NUMBER no configurado'];
            }

            $sent = $this->sendWhatsApp($testNotification);

            return [
                'success' => $sent,
                'message' => $sent ? 'WhatsApp conectado correctamente' : 'Error al enviar test'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test de conectividad Email
     */
    public function testEmailConnection(): array
    {
        try {
            $testNotification = [
                'id' => 0,
                'recipient' => $_ENV['TEST_EMAIL'] ?? '',
                'subject' => 'Test de Email - MisRifas',
                'message' => 'Este es un correo de prueba del sistema MisRifas.'
            ];

            if (empty($testNotification['recipient'])) {
                return ['success' => false, 'error' => 'TEST_EMAIL no configurado'];
            }

            $sent = $this->sendEmail($testNotification);

            return [
                'success' => $sent,
                'message' => $sent ? 'Email conectado correctamente' : 'Error al enviar test'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
