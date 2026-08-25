<?php
/**
 * Worker: Procesador de Cola de Correo
 * run: php api/workers/process_queue.php
 * Se recomienda configurar como cron cada minuto.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/MailService.php';
require_once __DIR__ . '/../utils/Logger.php';

try {
    $db = Database::getInstance()->getConnection();
    $mailService = new MailService();

    // Obtener tamaño de lote desde settings
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'mailing_batch_size'");
    $stmt->execute();
    $batchSize = (int)($stmt->fetchColumn() ?: 50);

    // 1. Obtener correos pendientes
    $stmt = $db->prepare("
        SELECT * FROM email_queue 
        WHERE status = 'pending' 
        ORDER BY priority DESC, created_at ASC 
        LIMIT ?
    ");
    $stmt->execute([$batchSize]);
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queueItems)) {
        // echo "No hay correos pendientes.\n";
        exit;
    }

    // 2. Procesar lote
    foreach ($queueItems as $item) {
        $db->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?")->execute([$item['id']]);

        $success = $mailService->sendDirect($item['recipient_email'], $item['subject'], $item['body_html']);

        if ($success) {
            $db->prepare("UPDATE email_queue SET status = 'sent', processed_at = NOW() WHERE id = ?")->execute([$item['id']]);
            
            // Actualizar contador en campaña si aplica
            if ($item['campaign_id']) {
                $db->prepare("UPDATE email_campaigns SET sent_count = sent_count + 1 WHERE id = ?")->execute([$item['campaign_id']]);
            }
        } else {
            $db->prepare("UPDATE email_queue SET status = 'failed', last_error = 'SMTP Error' WHERE id = ?")->execute([$item['id']]);
            
            if ($item['campaign_id']) {
                $db->prepare("UPDATE email_campaigns SET error_count = error_count + 1 WHERE id = ?")->execute([$item['campaign_id']]);
            }
        }
    }

    // 3. Marcar campañas como completadas si ya no hay pendientes
    $db->exec("
        UPDATE email_campaigns c
        SET c.status = 'completed'
        WHERE c.status = 'queued' 
        AND NOT EXISTS (SELECT 1 FROM email_queue q WHERE q.campaign_id = c.id AND q.status IN ('pending', 'processing'))
    ");

} catch (Exception $e) {
    Logger::error("Worker Email Error: " . $e->getMessage());
}
