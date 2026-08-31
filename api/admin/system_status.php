<?php

declare(strict_types=1);

/**
 * API: Auto-diagnóstico del sistema (solo super_admin)
 * GET /api/admin/system_status.php → checks en vivo de SMTP, OTP, WhatsApp,
 * SMS, storage y cron. NADA se asume: cada estado sale de una verificación
 * real (conexión de sockets, binarios instalados, settings en BD).
 * Lo consume la tarjeta "Comunicaciones y notificaciones" del panel.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../services/SystemStatus.php';

try {
    $admin = Auth::requireAdmin();
    if (($admin['role'] ?? '') !== 'super_admin') {
        Response::error('Solo el super administrador ve el diagnóstico', null, 403);
    }
    $db = Database::getInstance()->getConnection();
    Response::success(['checks' => SystemStatus::checks($db), 'generado' => date('Y-m-d H:i:s')]);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al generar el diagnóstico');
}
