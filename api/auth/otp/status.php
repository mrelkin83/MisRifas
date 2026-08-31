<?php
/**
 * API: Estado de verificación (para el polling de la pantalla de espera)
 * GET /api/auth/otp/status.php → { verified: bool }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/otp_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', null, 405);
}

try {
    $acct = otpAccount();
    Response::success(['verified' => $acct['verified']]);
} catch (Exception $e) {
    Response::serverError('Error al consultar el estado');
}
