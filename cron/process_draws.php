<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';
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
               r.draw_date, r.ticket_price, r.whatsapp_contact, r.image_url, r.auto_notify,
               r.draw_rescheduled_count,
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
        } catch (Throwable $e) {
            // Throwable, no Exception: un TypeError en una rifa con datos
            // rotos no debe impedir que las DEMÁS rifas se procesen.
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
    $attempt = (int)$raffle['draw_rescheduled_count'] + 1;

    // §12.1: el desenlace depende del ESTADO del ticket cuyo espacio de
    // oportunidades contiene el número — se busca entre TODOS los tickets,
    // no solo los pagados. Invariante 2.4: solo un 'paid' puede ganar.
    $stmt = $db->prepare("
        SELECT t.id, t.raffle_id, t.user_id, t.ticket_number, t.opportunities, t.status,
               u.name AS buyer_name, u.phone_whatsapp AS buyer_phone, u.email AS buyer_email
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.raffle_id = ?
    ");
    $stmt->execute([$raffle['id']]);

    $matching = null;
    $paidTickets = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ticket) {
        $opportunities = json_decode($ticket['opportunities'], true);
        if ($matching === null && is_array($opportunities) && in_array($digitsToMatch, $opportunities, true)) {
            $matching = $ticket;
        }
        if ($ticket['status'] === 'paid') {
            $paidTickets[] = $ticket;
        }
    }

    $stmt = $db->prepare("SELECT id, business_name, phone, email FROM vendors WHERE id = ?");
    $stmt->execute([$raffle['vendor_id']]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) {
        // Rifa huérfana (vendedor borrado): antes esto reventaba el cron
        // ENTERO con un TypeError y ninguna otra rifa se procesaba.
        Logger::error('Rifa con vendedor inexistente — se omite', [
            'raffle_id' => $raffle['id'], 'vendor_id' => $raffle['vendor_id'],
        ]);
        return ['has_winner' => false, 'reason' => 'Vendedor inexistente (rifa huérfana)'];
    }

    $scheduledAt = date('Y-m-d') . ' 05:00:00';

    // ── Desenlace 1: hay ganador (ticket PAGADO) ──
    if ($matching && $matching['status'] === 'paid') {
        registrarIntento($db, $raffle, $attempt, $digitsToMatch, 'winner', 'paid', null);
        registerWinner($raffle, $matching, $digitsToMatch, $vendor, $scheduledAt, $paidTickets);
        return ['has_winner' => true];
    }

    // ── Desenlaces 2 y 3: sin ganador ──
    $outcome = $matching ? 'no_winner' : 'not_sold';
    $ticketStatus = $matching['status'] ?? null;

    // §12.3: máximo 3 reprogramaciones — al CUARTO desenlace sin ganador la
    // rifa se cancela y arranca el flujo de devolución (§12.4).
    if ((int)$raffle['draw_rescheduled_count'] >= 3) {
        registrarIntento($db, $raffle, $attempt, $digitsToMatch, $outcome, $ticketStatus, null);
        cancelarPorTope($db, $raffle, $vendor, $paidTickets, $digitsToMatch, $scheduledAt);
        return ['has_winner' => false, 'reason' => 'Cancelada por tope de reprogramaciones'];
    }

    // Modo configurable (decisión aprobada): 'auto' reagenda el sistema;
    // 'manual' deja la rifa en pending_reschedule y decide el VENDEDOR.
    // Las guardas de §12.3 aplican a ambos.
    $modeStmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reschedule_mode'");
    $modeStmt->execute();
    $mode = (string)$modeStmt->fetchColumn() === 'manual' ? 'manual' : 'auto';

    if ($mode === 'manual') {
        registrarIntento($db, $raffle, $attempt, $digitsToMatch, $outcome, $ticketStatus, null);
        $db->prepare("UPDATE raffles SET status = 'pending_reschedule' WHERE id = ?")
           ->execute([$raffle['id']]);
        avisarVendedorReprogramar($db, $raffle, $vendor, $digitsToMatch, $ticketStatus, $scheduledAt);
        return ['has_winner' => false, 'reason' => 'pending_reschedule (modo manual)'];
    }

    $nextDate = getNextDrawDate((int)$raffle['lottery_id'], (string)$raffle['draw_date']);
    registrarIntento($db, $raffle, $attempt, $digitsToMatch, $outcome, $ticketStatus, $nextDate);
    scheduleResorteo($raffle, $vendor, $paidTickets, $digitsToMatch, $scheduledAt, $nextDate);
    return ['has_winner' => false, 'reason' => 'Sin ganador'];
}

/**
 * §12.2: TODO intento de sorteo queda registrado en raffle_draws — es el
 * historial público que hace confiable la reprogramación.
 */
function registrarIntento(PDO $db, array $raffle, int $attempt, string $digits, string $outcome, ?string $ticketStatus, ?string $rescheduledTo): void
{
    $db->prepare("
        INSERT INTO raffle_draws
            (raffle_id, attempt, lottery_id, draw_date, winning_number, ticket_status, outcome, rescheduled_to)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE winning_number = VALUES(winning_number),
            ticket_status = VALUES(ticket_status), outcome = VALUES(outcome),
            rescheduled_to = VALUES(rescheduled_to)
    ")->execute([
        $raffle['id'], $attempt, $raffle['lottery_id'], $raffle['draw_date'],
        $digits, $ticketStatus, $outcome, $rescheduledTo,
    ]);
}

/**
 * §12.4: cancelación por agotar reprogramaciones. La plataforma no devuelve
 * plata (nunca la tuvo): genera al vendedor la lista de compradores pagados
 * para que él devuelva, y avisa a los compradores para que nadie quede
 * atrapado en una rifa que nunca juega.
 */
function cancelarPorTope(PDO $db, array $raffle, array $vendor, array $paidTickets, string $digits, string $scheduledAt): void
{
    $db->prepare("UPDATE raffles SET status = 'cancelled' WHERE id = ?")->execute([$raffle['id']]);
    Logger::activity('raffle_cancelled_cap', (int)$raffle['vendor_id'], [
        'raffle_id' => $raffle['id'], 'paid_tickets' => count($paidTickets),
    ]);

    // Lista de devoluciones para el vendedor.
    $lineas = '';
    foreach ($paidTickets as $t) {
        $lineas .= '- #' . $t['ticket_number'] . ' · ' . ($t['buyer_name'] ?: 'Sin nombre')
            . ' · ' . ($t['buyer_phone'] ?: 's/cel') . ' · ' . ($t['buyer_email'] ?: 's/correo')
            . ' · $' . number_format((float)$raffle['ticket_price'], 0, ',', '.') . "\n";
    }
    enqueueMessage((int)$raffle['id'], (int)$vendor['id'], null, $vendor['phone'], $vendor['email'], [
        'message_type' => 'no_winner',
        'subject' => 'Rifa "' . $raffle['name'] . '" CANCELADA — lista de devoluciones',
        'body_text' => "La rifa \"{$raffle['name']}\" agotó sus 3 reprogramaciones sin ganador y quedó CANCELADA públicamente.\n\n"
            . "Debes devolver el dinero a estos compradores:\n\n" . ($lineas ?: "(sin boletos pagados)\n")
            . "\nLa cancelación queda registrada en tu historial. — " . plataforma('nombre'),
        'body_html' => null,
    ], $scheduledAt);

    // Aviso a cada comprador pagado.
    foreach ($paidTickets as $t) {
        enqueueMessage((int)$raffle['id'], (int)$vendor['id'], (int)$t['user_id'], $t['buyer_phone'], $t['buyer_email'], [
            'message_type' => 'no_winner',
            'subject' => 'La rifa "' . $raffle['name'] . '" fue cancelada',
            'body_text' => 'Hola ' . ($t['buyer_name'] ?: '') . ", la rifa \"{$raffle['name']}\" se canceló tras 4 sorteos sin ganador (número que salió: {$digits}).\n"
                . 'El organizador (' . $vendor['business_name'] . ', cel ' . $vendor['phone'] . ') debe devolverte $'
                . number_format((float)$raffle['ticket_price'], 0, ',', '.') . ' de tu boleto #' . $t['ticket_number'] . ".\n"
                . 'La cancelación es pública en la página de la rifa. — ' . plataforma('nombre'),
            'body_html' => null,
        ], $scheduledAt);
    }
}

/**
 * Modo manual: el vendedor decide la nueva fecha desde su panel.
 */
function avisarVendedorReprogramar(PDO $db, array $raffle, array $vendor, string $digits, ?string $ticketStatus, string $scheduledAt): void
{
    $restantes = 3 - (int)$raffle['draw_rescheduled_count'];
    $estado = $ticketStatus ? "cayó en un boleto en estado '{$ticketStatus}'" : 'no cayó en ningún boleto vendido';
    enqueueMessage((int)$raffle['id'], (int)$vendor['id'], null, $vendor['phone'], $vendor['email'], [
        'message_type' => 'no_winner',
        'subject' => 'Tu rifa "' . $raffle['name'] . '" necesita reprogramación',
        'body_text' => "Tu rifa \"{$raffle['name']}\" jugó: el número {$digits} {$estado}, así que NO hay ganador (solo un boleto pagado gana).\n\n"
            . "Entra a tu panel y elige la nueva fecha del sorteo (misma lotería). Te quedan {$restantes} reprogramación(es); si se agotan, la rifa se cancela y deberás devolver lo cobrado.\n"
            . 'Los boletos pagados se conservan tal cual. — ' . plataforma('nombre'),
        'body_html' => null,
    ], $scheduledAt);
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

    // auto_notify=0: el organizador eligió avisar él mismo a sus compradores;
    // el ganador igual queda registrado y el organizador SÍ es notificado.
    $notifyBuyers = !isset($raffle['auto_notify']) || (int)$raffle['auto_notify'] === 1;

    if ($notifyBuyers) {
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
    }

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
    if (!$notifyBuyers) {
        return;
    }
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

function scheduleResorteo(array $raffle, array $vendor, array $tickets, string $digitsToMatch, string $scheduledAt, ?string $nextDate = null): void
{
    $db = Database::getInstance()->getConnection();

    $nextDate = $nextDate ?? getNextDrawDate((int)$raffle['lottery_id'], (string)$raffle['draw_date']);

    // §12.2: los paid se conservan; cutoff_at se recalcula contra la nueva
    // fecha; y el PNG cacheado de cada boleta se invalida (mostraba la fecha
    // vieja — la página pública siempre fue dinámica).
    $stmt = $db->prepare("
        UPDATE raffles
        SET draw_date = ?, cutoff_at = DATE_SUB(?, INTERVAL 2 DAY),
            draw_rescheduled_count = draw_rescheduled_count + 1
        WHERE id = ?
    ");
    $stmt->execute([$nextDate, $nextDate, $raffle['id']]);

    require_once __DIR__ . '/../api/services/BoletaImage.php';
    $codes = $db->prepare("SELECT ticket_code FROM tickets WHERE raffle_id = ? AND ticket_code IS NOT NULL");
    $codes->execute([$raffle['id']]);
    foreach ($codes->fetchAll(PDO::FETCH_COLUMN) as $code) {
        BoletaImage::invalidateCache((string)$code);
    }

    Logger::info("Rifa re-programada para: $nextDate", ['raffle_id' => $raffle['id']]);

    $lottery = ['name' => $raffle['lottery_name']];

    // auto_notify=0: el organizador avisa por su cuenta.
    if (isset($raffle['auto_notify']) && (int)$raffle['auto_notify'] === 0) {
        return;
    }

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
