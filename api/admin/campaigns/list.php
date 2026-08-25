<?php
/**
 * API: Listar Campañas de Email
 * GET /api/admin/campaigns/list.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../api/utils/Auth.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../config/database.php';

try {
    $user = Auth::requireAdmin();
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT * FROM email_campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success($campaigns);

} catch (Exception $e) {
    Response::serverError('Error al listar campañas');
}
