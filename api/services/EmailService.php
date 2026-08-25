<?php
/**
 * Service: Email Notifications (BillionMail)
 */

namespace api\services;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

use Exception;
use Database;

class EmailService
{
    private static $instance = null;
    private $serviceUrl;

    private function __construct()
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'billionmail_url'");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->serviceUrl = $config['setting_value'] ?? 'http://mail-service:8080';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function sendEmail($toEmail, $toName, $subject, $contentHtml)
    {
        $url = $this->serviceUrl . '/send';
        $payload = json_encode([
            'to' => $toEmail,
            'subject' => $subject,
            'html' => $contentHtml
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Logger::error('EmailService send failed', [
                'to' => $toEmail,
                'http_code' => $httpCode,
                'response' => $response
            ]);
            throw new Exception('Email send failed: HTTP ' . $httpCode);
        }

        Logger::info('Email sent', ['to' => $toEmail, 'subject' => $subject]);
        return true;
    }
}
