<?php
/**
 * Cron: Procesar la cola de correos electrónicos
 * Se recomienda correr este cron cada minuto.
 */

namespace api\cron;

require_once __DIR__ . '/../../api/services/EmailService.php';
require_once __DIR__ . '/../../config/database.php';

use api\services\EmailService;
use Database;
use PDO;
use Exception;

$db = Database::getInstance()->getConnection();

// Traer 50 correos pendientes (límite para no sobrecargar el proceso)
$stmt = $db->prepare("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 LIMIT 50");
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$emails) {
    exit("No hay correos pendientes.\n");
}

$service = EmailService::getInstance();

foreach ($emails as $email) {
    echo "Enviando a: {$email['to_email']}... ";
    
    try {
        $service->sendEmail($email['to_email'], $email['to_name'], $email['subject'], $email['content_html']);
        
        // Actualizar estado a enviado
        $updateStmt = $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), attempts = attempts + 1 WHERE id = ?");
        $updateStmt->execute([$email['id']]);
        echo "OK\n";
    } catch (Exception $e) {
        // Actualizar con error y sumar intento
        $updateStmt = $db->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, error_log = ? WHERE id = ?");
        $updateStmt->execute([$e->getMessage(), $email['id']]);
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
