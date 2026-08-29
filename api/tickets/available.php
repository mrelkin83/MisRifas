<?php
/**
 * API: Boletos de la Rifa (todos los estados)
 * GET /api/tickets/available.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');

    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

try {
    $raffleId = (int)($_GET['raffle_id'] ?? 0);

    if (!$raffleId) {
        Response::error('ID de rifa requerido', null, 400);
    }

    $db = Database::getInstance()->getConnection();

    // Devolver TODOS los boletos: disponibles, reservados y pagados
    $stmt = $db->prepare(
        "SELECT id, ticket_number, opportunities, status, reserved_until
         FROM tickets
         WHERE raffle_id = ?
         ORDER BY CAST(ticket_number AS UNSIGNED) ASC"
    );
    $stmt->execute([$raffleId]);
    $tickets = $stmt->fetchAll();

    // Una reserva cuyo reserved_until ya pasó cuenta como disponible aunque el
    // cron de liberación no haya corrido todavía: así la disponibilidad es
    // auto-sanable y coincide con lo que permite create-reservation.php.
    $now = time();
    $formatted = array_map(function ($ticket) use ($now) {
        $status = $ticket['status'];
        if ($status === 'reserved' && !empty($ticket['reserved_until'])
            && strtotime($ticket['reserved_until']) < $now) {
            $status = 'available';
        }
        return [
            'id'             => $ticket['id'],
            'ticket_number'  => $ticket['ticket_number'],
            'opportunities'  => json_decode($ticket['opportunities']),
            'status'         => $status,
            'reserved_until' => $status === 'available' ? null : $ticket['reserved_until'],
        ];
    }, $tickets);

    Response::success($formatted);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al cargar los boletos');
}
