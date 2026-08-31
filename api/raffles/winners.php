<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

try {
    $db = Database::getInstance()->getConnection();

    $sql = "
        SELECT
            rw.id,
            r.name as raffle_name,
            r.draw_date,
            rw.winning_number AS winning_ticket_number,
            r.image_url,
            r.ticket_price,
            l.name as lottery_name,
            u.name as winner_name,
            u.phone_whatsapp as winner_phone,
            u.city as winner_city,
            rw.acceptance_status,
            rw.accepted_at,
            rw.delivery_status,
            rw.delivery_confirmed_at,
            r.draw_rescheduled_count,
            r.id AS raffle_id
        FROM raffle_winners rw
        INNER JOIN raffles r ON rw.raffle_id = r.id
        INNER JOIN lotteries l ON r.lottery_id = l.id
        LEFT JOIN users u ON rw.user_id = u.id
        WHERE r.status IN ('completed', 'active')
        ORDER BY rw.created_at DESC
        LIMIT 20
    ";

    $stmt = $db->query($sql);
    $winners = $stmt->fetchAll();

    Response::success($winners);

} catch (Exception $e) {
    Response::serverError('Error al obtener ganadores');
}
