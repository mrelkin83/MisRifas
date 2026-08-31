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
    // Código de UNA boleta (Crockford 12): con él se responde SOLO ese boleto
    // (poseer el código autoriza ver ese boleto, no el historial completo).
    $ticketCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['ticket_code'] ?? '')));

    if (empty($phone) && empty($uniqueId) && $ticketCode === '') {
        Response::error('Teléfono, código único o código de boleta requerido', null, 400);
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

    // El SELECT incluye el RESULTADO del sorteo por boleto (§ buscador de
    // resultados): si es GANADOR (fila en raffle_winners), el número ganador
    // del último intento registrado (raffle_draws) y el estado de la rifa.
    $baseSelect = "
        SELECT t.*, r.name as raffle_name, r.draw_date, r.image_url, r.ticket_price,
               r.status AS raffle_status,
               rw.id AS winner_id, rw.winning_number AS winner_number,
               rw.acceptance_status, rw.delivery_status,
               (SELECT d.winning_number FROM raffle_draws d
                 WHERE d.raffle_id = r.id ORDER BY d.attempt DESC LIMIT 1) AS last_winning_number
        FROM tickets t
        INNER JOIN raffles r ON t.raffle_id = r.id
        LEFT JOIN raffle_winners rw ON rw.ticket_id = t.id
    ";

    if ($ticketCode !== '') {
        $stmt = $db->prepare($baseSelect . " WHERE t.ticket_code = ? LIMIT 1");
        $stmt->execute([$ticketCode]);
        $tickets = $stmt->fetchAll();
        if (!$tickets) {
            Response::notFound('No existe una boleta con ese código');
        }
        $user = ['unique_id' => '', 'name' => '', 'phone_whatsapp' => ''];
    } else {
        $userRepo = new UserRepository();
        $user = $phone ? $userRepo->findByPhone($phone) : $userRepo->findByUniqueId($uniqueId);
        if (!$user) {
            Response::notFound('Usuario no encontrado');
        }
        $stmt = $db->prepare($baseSelect . "
            WHERE t.user_id = ? AND t.status IN ('reserved', 'paid')
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $tickets = $stmt->fetchAll();
    }

    $formatted = array_map(function($ticket) {
        // Desenlace por boleto: ganador / no_ganador / pendiente / cancelada.
        if (!empty($ticket['winner_id'])) {
            $resultado = 'ganador';
        } elseif ($ticket['raffle_status'] === 'cancelled') {
            $resultado = 'cancelada';
        } elseif ($ticket['raffle_status'] === 'completed') {
            $resultado = 'no_ganador';
        } elseif (!empty($ticket['last_winning_number']) && in_array($ticket['raffle_status'], ['active', 'pending_reschedule'], true)) {
            // Hubo intento sin ganador y la rifa sigue viva → reprogramada.
            $resultado = 'reprogramada';
        } else {
            $resultado = 'pendiente';
        }
        return [
            'id' => $ticket['id'],
            'raffle_id' => $ticket['raffle_id'],
            'ticket_code' => $ticket['ticket_code'] ?? null,
            'ticket_number' => $ticket['ticket_number'],
            'opportunities' => json_decode($ticket['opportunities']),
            'status' => $ticket['status'],
            'raffle_name' => $ticket['raffle_name'],
            'draw_date' => $ticket['draw_date'],
            'image_url' => $ticket['image_url'],
            'ticket_price' => $ticket['ticket_price'],
            'reserved_until' => $ticket['reserved_until'],
            'raffle_status' => $ticket['raffle_status'],
            'resultado' => $resultado,
            'winning_number' => $ticket['winner_number'] ?: ($ticket['last_winning_number'] ?: null),
            'acceptance_status' => $ticket['acceptance_status'],
            'delivery_status' => $ticket['delivery_status'],
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
