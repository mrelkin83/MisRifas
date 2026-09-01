<?php
/**
 * API: Link de pago Wompi para el cobro de la plataforma sobre UNA rifa.
 * POST /api/vendor/pagar-cobro.php {raffle_id}
 *
 * Solo el DUEÑO de la rifa (o el super_admin) obtiene el link; el monto y la
 * firma los pone el servidor — el cliente jamás decide cuánto paga.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/services/WompiBilling.php';

try {
    $user = Auth::requireVendor();
    $db = Database::getInstance()->getConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Método no permitido', null, 405);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $raffleId = (int)($input['raffle_id'] ?? 0);
    if (!$raffleId) {
        Response::error('Falta raffle_id', null, 422);
    }

    if (!WompiBilling::configurado($db)) {
        Response::error('El pago en línea no está habilitado todavía. Sigue las instrucciones de pago manual del aviso.', 'WOMPI_NO_CONFIGURADO', 409);
    }

    $stmt = $db->prepare('SELECT id, name, created_by, commission_amount, commission_paid FROM raffles WHERE id = ?');
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$raffle) {
        Response::error('Rifa no encontrada', null, 404);
    }
    if ((int)$raffle['created_by'] !== (int)$user['id'] && ($user['role'] ?? '') !== 'super_admin') {
        Response::error('Solo el organizador de la rifa puede pagar su cobro', null, 403);
    }
    if ((int)$raffle['commission_paid'] === 1) {
        Response::error('Este cobro ya está pagado', null, 409);
    }
    if ((float)$raffle['commission_amount'] <= 0) {
        Response::error('Esta rifa no tiene cobro pendiente', null, 409);
    }

    $link = WompiBilling::linkPago($db, $raffle);
    Logger::activity('commission_paylink_generated', (int)$user['id'], [
        'raffle_id' => $raffleId, 'reference' => $link['reference'],
    ]);
    Response::success($link + ['message' => 'Abre el link para pagar; al aprobarse, tu cuenta se reactiva sola.']);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error generando el link de pago');
}
