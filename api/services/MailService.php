<?php
/**
 * Service: Mailing Engine
 * Handles direct sending and queuing of emails using SMTP via fsockopen.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

class MailService {
    
    private $db;
    private $settings = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadSettings();
    }

    private function loadSettings() {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'mailing_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    /**
     * Encola un correo para envío masivo
     */
    public function queue($to, $subject, $body, $campaignId = null, $name = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (campaign_id, recipient_email, recipient_name, subject, body_html, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            return $stmt->execute([$campaignId, $to, $name, $subject, $body]);
        } catch (Exception $e) {
            Logger::error("Failed to queue email to $to: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envío Directo (Sincrónico) - Úsalo solo para transacciones críticas
     */
    public function sendDirect($to, $subject, $body) {
        $host = $this->settings['mailing_smtp_host'] ?? '';
        $port = (int)($this->settings['mailing_smtp_port'] ?? 587);
        $user = $this->settings['mailing_smtp_user'] ?? '';
        $pass = $this->settings['mailing_smtp_pass'] ?? '';
        $from = $this->settings['mailing_smtp_from'] ?? 'no-reply@misrifas.online';
        $fromName = $this->settings['mailing_from_name'] ?? 'MisRifas';

        if (empty($host) || empty($user)) {
            Logger::error("SMTP settings missing. Cannot send email to $to");
            return false;
        }

        // --- Custom Light SMTP Logic (Minimalistic PHPMailer Equivalent) ---
        return $this->smtpSend($host, $port, $user, $pass, $from, $fromName, $to, $subject, $body);
    }

    private function smtpSend($host, $port, $user, $pass, $from, $fromName, $to, $subject, $body) {
        try {
            $timeout = 15;
            $socket = fsockopen($host, $port, $errno, $errstr, $timeout);
            if (!$socket) throw new Exception("Could not connect to $host: $errstr");

            $this->expect($socket, '220');
            fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $this->expect($socket, '250');

            // STARTTLS if 587 or 465 logic
            if ($port == 587) {
                fwrite($socket, "STARTTLS\r\n");
                $this->expect($socket, '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("STARTTLS failed");
                }
                fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
                $this->expect($socket, '250');
            }

            // Auth
            fwrite($socket, "AUTH LOGIN\r\n");
            $this->expect($socket, '334');
            fwrite($socket, base64_encode($user) . "\r\n");
            $this->expect($socket, '334');
            fwrite($socket, base64_encode($pass) . "\r\n");
            $this->expect($socket, '235');

            // Mail From
            fwrite($socket, "MAIL FROM:<$from>\r\n");
            $this->expect($socket, '250');

            // Rcpt To
            fwrite($socket, "RCPT TO:<$to>\r\n");
            $this->expect($socket, '250');

            // Data
            fwrite($socket, "DATA\r\n");
            $this->expect($socket, '354');

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: $fromName <$from>\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: <" . time() . "." . md5($to) . "@" . $_SERVER['SERVER_NAME'] . ">\r\n";
            $headers .= "\r\n";

            fwrite($socket, $headers . $body . "\r\n.\r\n");
            $this->expect($socket, '250');

            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return true;
        } catch (Exception $e) {
            Logger::error("SMTP Error sending to $to: " . $e->getMessage());
            return false;
        }
    }

    private function expect($socket, $code) {
        $res = fgets($socket, 512);
        if (substr($res, 0, 3) !== $code) {
            throw new Exception("Expected code $code, got: " . $res);
        }
    }

    /**
     * Notifica a todos los participantes de una rifa cuando hay resultados.
     */
    public function notifyRaffleResults($raffleId, $winningNumber) {
        $stmt = $this->db->prepare("SELECT name FROM raffles WHERE id = ?");
        $stmt->execute([$raffleId]);
        $raffle = $stmt->fetch();
        if (!$raffle) return;

        // Obtener todos los compradores pagados
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.email, u.name, t.ticket_number
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.raffle_id = ? AND t.status = 'paid'
        ");
        $stmt->execute([$raffleId]);
        $buyers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($buyers as $buyer) {
            $isWinner = (trim($buyer['ticket_number']) === trim($winningNumber));
            $subject = $isWinner ? "¡FELICIDADES! Eres Ganador de {$raffle['name']}" : "Resultados de la Rifa: {$raffle['name']}";
            
            $body = "
                <div style='font-family: sans-serif; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                    <h2 style='color: #2563eb;'>Resultados de MisRifas</h2>
                    <p>Hola <strong>{$buyer['name']}</strong>,</p>
                    <p>Se han publicado los resultados de la rifa: <strong>{$raffle['name']}</strong>.</p>
                    
                    <div style='background: #f1f5f9; padding: 30px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 0; color: #64748b; font-size: 14px;'>NÚMERO GANADOR</p>
                        <h1 style='margin: 10px 0; font-size: 48px; color: #1e293b;'>{$winningNumber}</h1>
                        <p style='margin: 0; color: #64748b; font-size: 14px;'>Tu número: <strong>{$buyer['ticket_number']}</strong></p>
                    </div>";

            if ($isWinner) {
                $body .= "
                    <div style='background: #dcfce7; padding: 15px; border-radius: 8px; color: #166534; text-align: center; border: 1px solid #bbf7d0;'>
                        <p style='margin: 0; font-weight: bold;'>🎉 ¡FELICITACIONES! HAS GANADO EL PREMIO.</p>
                        <p style='margin: 5px 0 0 0; font-size: 13px;'>El organizador se pondrá en contacto contigo pronto.</p>
                    </div>";
            } else {
                $body .= "<p style='text-align: center; color: #94a3b8;'>Gracias por participar. Esté atento a nuevas rifas.</p>";
            }

            $body .= "
                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                    <p style='font-size: 11px; color: #94a3b8; text-align: center;'>MisRifas Colombia - Rifa Digital Segura</p>
                </div>";

            $this->queue($buyer['email'], $subject, $body, null, $buyer['name']);
        }
    }
}
