<?php
/**
 * Script: Inicializar Tablas para el Sistema de Correo
 * run: php init_email_system.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}

require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // 1. Tabla de Campañas
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255) NOT NULL,
            body_html LONGTEXT NOT NULL,
            status ENUM('draft', 'queued', 'sending', 'completed', 'paused') DEFAULT 'draft',
            total_recipients INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            error_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Tabla de Cola de Envío (Email Queue)
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255) NULL,
            subject VARCHAR(255) NOT NULL,
            body_html LONGTEXT NOT NULL,
            status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            last_error TEXT NULL,
            priority INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 3. Inicializar Configuraciones SMTP
    $smtp_settings = [
        ['mailing_smtp_host', 'localhost', 'string'],
        ['mailing_smtp_port', '587', 'string'],
        ['mailing_smtp_user', '', 'string'],
        ['mailing_smtp_pass', '', 'string'],
        // Vacíos a propósito: sin valor, MailService usa la identidad
        // administrable (platform_name/platform_email) — nunca un dominio supuesto.
        ['mailing_smtp_from', '', 'string'],
        ['mailing_from_name', '', 'string'],
        ['mailing_batch_size', '50', 'string'], // Cuántos correos por minuto
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, data_type) VALUES (?, ?, ?)");
    foreach ($smtp_settings as $s) {
        $stmt->execute($s);
    }

    echo "Tablas de correo inicializadas correctamente.\n";

} catch (Exception $e) {
    die("Error al inicializar tablas: " . $e->getMessage());
}
