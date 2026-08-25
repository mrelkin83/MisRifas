<?php
/**
 * API: Crear y Encolar Campaña de Email
 * POST /api/admin/campaigns/create.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../../api/utils/Auth.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/services/MailService.php';
require_once __DIR__ . '/../../../config/database.php';

try {
    $user = Auth::requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $subject = trim($data['subject'] ?? '');
    $body = trim($data['body'] ?? '');
    $segment = trim($data['segment'] ?? 'all'); // all, buyers, sellers

    if (empty($subject) || empty($body)) {
        Response::error('Asunto y cuerpo del mensaje son obligatorios');
    }

    $db = Database::getInstance()->getConnection();
    
    // 1. Crear Campaña
    $stmt = $db->prepare("INSERT INTO email_campaigns (subject, body_html, status) VALUES (?, ?, 'queued')");
    $stmt->execute([$subject, $body]);
    $campaignId = $db->lastInsertId();

    // 2. Obtener destinatarios según segmento
    $sql = "SELECT id, name, email FROM users";
    if ($segment === 'buyers') $sql .= " WHERE role = 'buyer'";
    if ($segment === 'sellers') $sql .= " WHERE role = 'seller'";
    
    $users = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Encolar correos
    $mailService = new MailService();
    $queuedCount = 0;
    foreach ($users as $u) {
        // Reemplazar placeholders en el cuerpo (ej: [NAME])
        $personalizedBody = str_replace('[NOMBRE]', $u['name'], $body);
        $personalizedBody = str_replace('[EMAIL]', $u['email'], $personalizedBody);
        
        $success = $mailService->queue($u['email'], $subject, $personalizedBody, $campaignId, $u['name']);
        if ($success) $queuedCount++;
    }

    // 4. Actualizar total en campaña
    $db->prepare("UPDATE email_campaigns SET total_recipients = ? WHERE id = ?")->execute([$queuedCount, $campaignId]);

    Response::success(['message' => "Campaña creada con éxito. $queuedCount correos en cola."], 'Campaña iniciada');

} catch (Exception $e) {
    Response::serverError('Error al crear campaña: ' . $e->getMessage());
}
