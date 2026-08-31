<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/utils/Logger.php';
require_once __DIR__ . '/../api/services/MessageBuilderService.php';

if (php_sapi_name() !== 'cli') {
    $cronSecret = $_GET['secret'] ?? '';
    $config = require __DIR__ . '/../config/app.php';
    if (empty($cronSecret) || $cronSecret !== ($config['cron']['secret_key'] ?? '')) {
        http_response_code(403);
        die('Forbidden');
    }
}

$startTime = microtime(true);
Logger::info("=== Iniciando: Process Raffle Draws ===");

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query("
        SELECT r.id, r.name, r.lottery_id, r.vendor_id, r.winning_mode,
               r.draw_date, r.ticket_price, r.whatsapp_contact,
               lr.winning_number,
               l.name AS lottery_name
        FROM raffles r
        INNER JOIN lotteries l ON r.lottery_id = l.id
        INNER JOIN lottery_results lr ON l.id = lr.lottery_id AND lr.draw_date = CURDATE()
        WHERE r.status = 'active'
          AND r.draw_date <= NOW()
          AND lr.winning_number IS NOT NULL
          AND lr.verified = 1
    ");
    $raffles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $winnersFound = 0;
    $resorteos = 0;

    foreach ($raffles as $raffle) {
        try {
            $result = processRaffleDraw($raffle);

            if ($result['has_winner']) {
                $winnersFound++;
            } else {
                $resorteos++;
            }
            $processed++;
        } catch (Exception $e) {
            Logger::error("Error procesando rifa: " . $raffle['id'], [
                'error' => $e->getMessage()
            ]);
        }
    }

    $executionTime = round(microtime(true) - $startTime, 2);

    Logger::cron('process_raffle_draws', true, [
        'raffles_processed' => $processed,
        'winners_found' => $winnersFound,
        'resorteos' => $resorteos,
        'execution_time' => $executionTime . 's'
    ]);

    echo "Rifas procesadas: {$processed}\n";
    echo "Ganadores encontrados: {$winnersFound}\n";
    echo "Re-sorteos: {$resorteos}\n";
    echo "Tiempo: {$executionTime}s\n";

} catch (Exception $e) {
    Logger::exception($e);
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function processRaffleDraw(array $raffle): array
{
    $db = Database::getInstance()->getConnection();

    $digitsToMatch = getWinningDigits($raffle['winning_number'], $raffle['winning_mode']);

    $stmt = $db->prepare("
        SELECT t.id, t.raffle_id, t.user_id, t.ticket_number, t.opportunities,
               u.name AS buyer_name, u.phone_whatsapp AS buyer_phone, u.email AS buyer_email
        FROM tickets t
        INNER JOIN users u ON t.user_id = u.id
        WHERE t.raffle_id = ?
          AND t.status = 'paid'
    ");
    $stmt->execute([$raffle['id']]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $winningTicket = null;
    foreach ($tickets as $ticket) {
        $opportunities = json_decode($ticket['opportunities'], true);
        if (is_array($opportunities) && in_array($digitsToMatch, $opportunities, true)) {
            $winningTicket = $ticket;
            break;
        }
    }

    $stmt = $db->prepare("
        SELECT id, business_name, phone, email, wa_config
        FROM vendors
        WHERE id = ?
    ");
    $stmt->execute([$raffle['vendor_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    $scheduledAt = date('Y-m-d') . ' 05:00:00';

    if ($winningTicket) {
        registerWinner($raffle, $winningTicket, $digitsToMatch, $vendor, $scheduledAt, $tickets);
        return ['has_winner' => true];
    }

    scheduleResorteo($raffle, $vendor, $tickets, $digitsToMatch, $scheduledAt);
    return ['has_winner' => false, 'reason' => 'Sin ganador'];
}

function getWinningDigits(string $number, string $mode): string
{
    switch ($mode) {
        case 'last_2':
            return substr($number, -2);
        case 'first_2':
            return substr($number, 0, 2);
        case 'last_3':
            return substr($number, -3);
        case 'first_3':
            return substr($number, 0, 3);
        case 'last_4':
            return strlen($number) <= 4 ? $number : substr($number, -4);
        default:
            return substr($number, -2);
    }
}

function registerWinner(array $raffle, array $ticket, string $matchedDigits, array $vendor, string $scheduledAt, array $allTickets = []): void
{
    $db = Database::getInstance()->getConnection();

    // Token para la confirmación pública de aceptación del premio (sin login).
    $acceptanceToken = bin2hex(random_bytes(24));

    $stmt = $db->prepare("
        INSERT INTO raffle_winners
            (raffle_id, ticket_id, user_id, winning_number, matched_opportunity, prize_description, acceptance_token)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $raffle['id'],
        $ticket['id'],
        $ticket['user_id'],
        $raffle['winning_number'],
        $matchedDigits,
        $raffle['name'],
        $acceptanceToken
    ]);

    $db->prepare("UPDATE raffles SET status = 'completed' WHERE id = ?")
        ->execute([$raffle['id']]);

    Logger::activity('winner_found', $ticket['user_id'], [
        'raffle_id' => $raffle['id'],
        'ticket_number' => $ticket['ticket_number'],
        'winning_number' => $matchedDigits
    ]);

    // Enlace público de confirmación de aceptación (transparencia del sorteo).
    $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $confirmUrl = $appUrl . '/public/ganador-confirmar.php?t=' . $acceptanceToken;

    $lottery = ['name' => $raffle['lottery_name']];
    $winner = [
        'name' => $ticket['buyer_name'],
        'phone_whatsapp' => $ticket['buyer_phone'],
        'email' => $ticket['buyer_email'],
        'ticket_number' => $ticket['ticket_number'],
        'confirm_url' => $confirmUrl,
    ];

    $msg = MessageBuilderService::buildWinnerMessage($raffle, $ticket, $winner, $lottery, $matchedDigits);
    enqueueMessage(
        $raffle['id'],
        $vendor['id'],
        $ticket['user_id'],
        $ticket['buyer_phone'],
        $ticket['buyer_email'],
        $msg,
        $scheduledAt
    );

    $vendorMsg = MessageBuilderService::buildVendorWinnerNotification($raffle, $winner, $matchedDigits);
    enqueueMessage(
        $raffle['id'],
        $vendor['id'],
        null,
        $vendor['phone'],
        $vendor['email'],
        $vendorMsg,
        $scheduledAt
    );

    // Agradecimiento a los demás participantes de ESTA rifa: resultado,
    // ganador y sus boletos. Agrupado por comprador (una persona con varios
    // boletos recibe UN mensaje que los lista todos), excluyendo al ganador
    // (ya recibió su felicitación).
    $byUser = [];
    foreach ($allTickets as $t) {
        if ((int)$t['user_id'] === (int)$ticket['user_id']) {
            continue;
        }
        $uid = (int)$t['user_id'];
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [
                'name' => $t['buyer_name'],
                'phone' => $t['buyer_phone'],
                'email' => $t['buyer_email'],
                'tickets' => [],
            ];
        }
        $byUser[$uid]['tickets'][] = $t['ticket_number'];
    }
    foreach ($byUser as $uid => $participant) {
        $msg = MessageBuilderService::buildParticipantResultMessage(
            $raffle,
            $participant['tickets'],
            ['name' => $participant['name']],
            $lottery,
            $matchedDigits,
            $ticket['buyer_name'],
            $ticket['ticket_number']
        );
        enqueueMessage(
            $raffle['id'],
            $vendor['id'],
            $uid,
            $participant['phone'],
            $participant['email'],
            $msg,
            $scheduledAt
        );
    }
}

function scheduleResorteo(array $raffle, array $vendor, array $tickets, string $digitsToMatch, string $scheduledAt): void
{
    $db = Database::getInstance()->getConnection();

    $nextDate = getNextDrawDate($raffle['lottery_id'], $raffle['draw_date']);

    $stmt = $db->prepare("
        UPDATE raffles
        SET draw_date = ?, draw_rescheduled_count = draw_rescheduled_count + 1
        WHERE id = ?
    ");
    $stmt->execute([$nextDate, $raffle['id']]);

    Logger::info("Rifa re-programada para: $nextDate", ['raffle_id' => $raffle['id']]);

    $lottery = ['name' => $raffle['lottery_name']];

    // Un mensaje por comprador (agrupando sus boletos), avisando que sus
    // boletos SIGUEN participando y la nueva fecha del sorteo.
    $byUser = [];
    foreach ($tickets as $ticket) {
        $uid = (int)$ticket['user_id'];
        if (!isset($byUser[$uid])) {
            $byUser[$uid] = [
                'name' => $ticket['buyer_name'],
                'phone' => $ticket['buyer_phone'],
                'email' => $ticket['buyer_email'],
                'tickets' => [],
            ];
        }
        $byUser[$uid]['tickets'][] = $ticket['ticket_number'];
    }
    foreach ($byUser as $uid => $participant) {
        $msg = MessageBuilderService::buildResorteoMessage(
            $raffle,
            $participant['tickets'],
            ['name' => $participant['name']],
            $lottery,
            $digitsToMatch,
            $nextDate
        );
        enqueueMessage(
            $raffle['id'],
            $vendor['id'],
            $uid,
            $participant['phone'],
            $participant['email'],
            $msg,
            $scheduledAt
        );
    }
}

function enqueueMessage(
    int $raffleId,
    int $vendorId,
    ?int $recipientUserId,
    ?string $recipientPhone,
    ?string $recipientEmail,
    array $msg,
    string $scheduledAt
): void {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        INSERT INTO message_queue
            (raffle_id, vendor_id, recipient_user_id, recipient_phone, recipient_email,
             channel, message_type, subject, body_text, body_html, variables, status, scheduled_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
    ");

    // El email es el canal POR DEFECTO de resultados: se encola siempre que
    // el destinatario tenga correo. WhatsApp es el canal adicional (depende
    // de que el vendedor tenga su instancia Evolution vinculada). Cada canal
    // va en su propia fila para que el fallo de uno no bloquee al otro.
    $channels = [];
    if ($recipientEmail && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $channels[] = 'email';
    }
    if ($recipientPhone) {
        $channels[] = 'whatsapp';
    }

    foreach ($channels as $channel) {
        $stmt->execute([
            $raffleId,
            $vendorId,
            $recipientUserId,
            $recipientPhone,
            $recipientEmail,
            $channel,
            $msg['message_type'] ?? 'winner',
            $msg['subject'] ?? null,
            $msg['body_text'] ?? '',
            $msg['body_html'] ?? null,
            isset($msg['variables']) ? json_encode($msg['variables']) : null,
            $scheduledAt,
        ]);
    }
}

function getNextDrawDate(int $lotteryId, string $currentDate): string
{
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT day_of_week FROM lotteries WHERE id = ?");
    $stmt->execute([$lotteryId]);
    $lottery = $stmt->fetch(PDO::FETCH_ASSOC);

    $daysMap = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0
    ];

    $targetDay = $daysMap[$lottery['day_of_week']] ?? 6;
    $current = new DateTime($currentDate);
    $current->modify('+1 week');

    while ((int)$current->format('w') !== $targetDay) {
        $current->modify('+1 day');
    }

    return $current->format('Y-m-d 22:30:00');
}
