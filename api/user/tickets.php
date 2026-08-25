<?php
/**
 * API: Tickets del Usuario
 * GET /api/user/tickets.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/repositories/UserRepository.php';
require_once __DIR__ . '/../../api/utils/RateLimiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', null, 405);
}

try {
    $phone = $_GET['phone'] ?? '';
    $uniqueId = $_GET['unique_id'] ?? '';

    if (empty($phone) && empty($uniqueId)) {
        Response::error('Teléfono o código único requerido', null, 400);
    }

    // Consulta de invitado deliberada (sin cuenta), pero el telefono es de
    // baja entropia y enumerable - sin limite, cualquiera puede recorrer
    // numeros colombianos y cosechar nombre + historial de compras
    // completo (cross-vendor, por diseno: "mis boletos" es global) de cada
    // uno que exista. El limite no rompe el uso legitimo (una persona
    // consultando sus propios boletos), solo la cosecha masiva.
    if (!RateLimiter::check('lookup_tickets_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 15, 10)) {
        Response::rateLimitExceeded('Demasiadas consultas. Intenta de nuevo en unos minutos.');
    }

    $db = Database::getInstance()->getConnection();
    $userRepo = new UserRepository();

    if ($phone) {
        $user = $userRepo->findByPhone($phone);
    } else {
        $user = $userRepo->findByUniqueId($uniqueId);
    }

    if (!$user) {
        Response::notFound('Usuario no encontrado');
    }

    $stmt = $db->prepare("
        SELECT t.*, r.name as raffle_name, r.draw_date, r.image_url, r.ticket_price
        FROM tickets t
        INNER JOIN raffles r ON t.raffle_id = r.id
        WHERE t.user_id = ? AND t.status IN ('reserved', 'paid')
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $tickets = $stmt->fetchAll();

    $formatted = array_map(function($ticket) {
        return [
            'id' => $ticket['id'],
            'ticket_number' => $ticket['ticket_number'],
            'opportunities' => json_decode($ticket['opportunities']),
            'status' => $ticket['status'],
            'raffle_name' => $ticket['raffle_name'],
            'draw_date' => $ticket['draw_date'],
            'image_url' => $ticket['image_url'],
            'ticket_price' => $ticket['ticket_price'],
            'reserved_until' => $ticket['reserved_until'],
        ];
    }, $tickets);

    Response::success([
        'tickets' => $formatted,
        'user' => [
            'unique_id' => $user['unique_id'],
            'name' => $user['name'],
            'phone' => $user['phone_whatsapp'],
        ]
    ]);

} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al buscar boletos');
}
