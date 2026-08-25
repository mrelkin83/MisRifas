<?php

/**
 * API: Webhook de Nequi
 *
 * Endpoint para recibir notificaciones de pago desde Nequi
 * Este endpoint es llamado automáticamente por Nequi cuando un pago es procesado
 *
 * POST /api/payments/webhook.php
 *
 * IMPORTANTE:
 * - Este endpoint debe estar públicamente accesible
 * - Debe usar HTTPS en producción
 * - La URL debe ser registrada en el panel de Nequi
 * - Valida firma HMAC para seguridad
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/services/PaymentService.php';

try {
    // Solo aceptar POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Logger::error('Webhook recibió método no permitido', ['method' => $_SERVER['REQUEST_METHOD']]);
        Response::error('Método no permitido', null, 405);
    }

    // Obtener payload
    $rawPayload = file_get_contents('php://input');
    $webhookData = json_decode($rawPayload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Logger::error('Webhook recibió JSON inválido', ['raw' => $rawPayload]);
        Response::error('JSON inválido', null, 400);
    }

    // Obtener firma del header
    $signature = $_SERVER['HTTP_X_NEQUI_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';

    if (empty($signature)) {
        Logger::error('Webhook sin firma', ['headers' => getallheaders()]);
        Response::error('Firma requerida', null, 401);
    }

    // Log del webhook recibido
    Logger::debug('Webhook recibido', [
        'signature' => $signature,
        'payload' => $webhookData,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // Procesar webhook
    $paymentService = new PaymentService();
    $result = $paymentService->processNequiWebhook($webhookData, $signature);

    if ($result['success']) {
        Logger::debug('Webhook procesado exitosamente', $result);

        // Nequi espera respuesta 200 OK
        Response::success($result, 'Webhook procesado', 200);
    } else {
        Logger::error('Webhook procesado con error', $result);

        // Aún devolver 200 para que Nequi no reintente
        Response::success($result, 'Webhook recibido pero con errores', 200);
    }

} catch (Exception $e) {
    // Log del error
    Logger::error('Error procesando webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'payload' => $webhookData ?? null
    ]);

    // Importante: Devolver 200 aunque haya error para evitar reintentos infinitos de Nequi
    // El error ya quedó registrado en logs para análisis
    Response::success([
        'success' => false,
        'message' => 'Error procesando webhook',
        'logged' => true
    ], 'Error procesando webhook', 200);
}
