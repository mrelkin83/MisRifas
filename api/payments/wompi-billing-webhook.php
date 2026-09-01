<?php
/**
 * Webhook de Wompi para el COBRO DE LA PLATAFORMA (comisión/talonario).
 * POST /api/payments/wompi-billing-webhook.php
 *
 * Registrar esta URL en el panel de Wompi (Eventos). La firma del evento se
 * verifica con wompi_platform_events_secret ANTES de tocar nada: sin firma
 * válida, 403 seco. Con firma válida siempre 200 (Wompi reintenta los no-200)
 * y la acción solo ocurre si el estado es APPROVED y el monto coincide.
 * La activación es EXACTAMENTE la del botón manual del super_admin — que
 * sigue existiendo como plan de contingencia.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/services/WompiBilling.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $cfg = WompiBilling::config($db);
    if ($cfg['events_secret'] === '') {
        // Sin secreto configurado no hay forma de verificar NADA: se rechaza.
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }

    $evento = json_decode(file_get_contents('php://input'), true);
    if (!is_array($evento)) {
        http_response_code(400);
        echo json_encode(['ok' => false]);
        exit;
    }

    if (!WompiBilling::firmaValida($evento, $cfg['events_secret'])) {
        Logger::warning('Wompi billing webhook: firma INVÁLIDA', [
            'ref' => $evento['data']['transaction']['reference'] ?? null,
        ]);
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }

    $r = WompiBilling::procesarEvento($db, $evento);
    Logger::info('Wompi billing webhook', $r);
    echo json_encode(['ok' => true, 'nota' => $r['nota'] ?? '']);
} catch (Throwable $e) {
    Logger::exception($e);
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
