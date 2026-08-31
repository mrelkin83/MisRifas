<?php

class MessageBuilderService
{
    public static function buildWinnerMessage(array $raffle, array $ticket, array $winner, array $lottery, string $winningDigits): array
    {
        $vars = [
            'nombre' => $winner['name'] ?? 'Participante',
            'raffle_name' => $raffle['name'],
            'ticket_number' => str_pad($ticket['ticket_number'], 4, '0', STR_PAD_LEFT),
            'lottery_name' => $lottery['name'] ?? '',
            'winning_number' => $winningDigits,
            'full_number' => $winningDigits,
            'draw_date' => date('d/m/Y', strtotime($raffle['draw_date'])),
        ];

        $confirmUrl = $winner['confirm_url'] ?? '';
        $vars['confirm_url'] = $confirmUrl;

        $text = "Felicitaciones {nombre}! Ganaste la rifa *{raffle_name}* con el numero *{ticket_number}*. "
              . "El numero ganador de la {lottery_name} del {draw_date} fue *{full_number}*. ";
        // Para transparencia: el ganador confirma la aceptacion del premio.
        if ($confirmUrl !== '') {
            $text .= "Confirma que aceptas tu premio aqui: {confirm_url} . ";
        }
        $text .= "Pronto te contactaremos para la entrega del premio.";

        $text = self::replaceVars($text, $vars);

        $confirmBlockHtml = $confirmUrl !== ''
            ? "<p style='margin:24px 0;'><a href='{confirm_url}' style='background:#22c55e;color:#052e13;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;'>Confirmar aceptacion del premio</a></p>"
              . "<p style='font-size:12px;color:#94a3b8;'>Al confirmar dejas constancia publica de que aceptas el premio y el resultado del sorteo.</p>"
            : '';

        $html = self::buildEmailHtml(
            'Felicitaciones - Ganaste la rifa!',
            "<h2 style='color:#fbbf24;'>Felicitaciones {nombre}!</h2>"
            . "<p>Ganaste la rifa <strong>{raffle_name}</strong> con el boleto <strong>{ticket_number}</strong>.</p>"
            . "<p>El numero ganador de la {lottery_name} del {draw_date} fue <strong style='color:#22c55e;font-size:1.5em;'>{full_number}</strong></p>"
            . $confirmBlockHtml
            . "<p>Pronto te contactaremos para la entrega del premio.</p>",
            $vars
        );

        return [
            'channel' => 'whatsapp',
            'message_type' => 'winner',
            'subject' => 'Felicitaciones - Ganaste la rifa ' . $raffle['name'],
            'body_text' => $text,
            'body_html' => $html,
            'variables' => $vars,
        ];
    }

    public static function buildNoWinnerMessage(array $raffle, array $ticket, array $buyer, array $lottery, string $winningDigits): array
    {
        $vars = [
            'nombre' => $buyer['name'] ?? 'Participante',
            'raffle_name' => $raffle['name'],
            'ticket_number' => str_pad($ticket['ticket_number'], 4, '0', STR_PAD_LEFT),
            'lottery_name' => $lottery['name'] ?? '',
            'winning_number' => $winningDigits,
            'draw_date' => date('d/m/Y', strtotime($raffle['draw_date'])),
        ];

        $text = "Hola {nombre}, la rifa *{raffle_name}* ya tuvo sorteo. "
              . "El numero ganador de la {lottery_name} fue *{winning_number}*. "
              . "Tu boleto fue *{ticket_number}*. Sigue participando en misrifas.com!";

        $text = self::replaceVars($text, $vars);

        $html = self::buildEmailHtml(
            'Resultado de la rifa ' . $raffle['name'],
            "<h2>Resultado de la rifa {raffle_name}</h2>"
            . "<p>El numero ganador de la {lottery_name} fue <strong>{winning_number}</strong>.</p>"
            . "<p>Tu boleto fue <strong>{ticket_number}</strong>. Esta vez no fue, pero sigue participando!</p>",
            $vars
        );

        return [
            'channel' => 'whatsapp',
            'message_type' => 'no_winner',
            'subject' => 'Resultado rifa ' . $raffle['name'],
            'body_text' => $text,
            'body_html' => $html,
            'variables' => $vars,
        ];
    }

    public static function buildVendorWinnerNotification(array $raffle, array $winner, string $winningDigits): array
    {
        $vars = [
            'raffle_name' => $raffle['name'],
            'winner_name' => $winner['name'] ?? 'Participante',
            'winner_phone' => $winner['phone_whatsapp'] ?? '',
            'ticket_number' => str_pad($winner['ticket_number'] ?? '0000', 4, '0', STR_PAD_LEFT),
            'winning_number' => $winningDigits,
        ];

        $text = "Tu rifa *{raffle_name}* tuvo ganador! "
              . "Ganador: *{winner_name}* ({winner_phone}) con boleto *{ticket_number}*. "
              . "Numero ganador: *{winning_number}*. Contacta al ganador para entregar el premio.";

        $text = self::replaceVars($text, $vars);

        return [
            'channel' => 'whatsapp',
            'message_type' => 'winner',
            'subject' => 'Tu rifa ' . $raffle['name'] . ' tuvo ganador!',
            'body_text' => $text,
            'body_html' => null,
            'variables' => $vars,
        ];
    }

    public static function buildReservationMessage(array $raffle, array $ticket, array $buyer): array
    {
        $vars = [
            'nombre' => $buyer['name'] ?? 'Participante',
            'raffle_name' => $raffle['name'],
            'ticket_number' => str_pad($ticket['ticket_number'], 4, '0', STR_PAD_LEFT),
            'price' => '$' . number_format($raffle['ticket_price'], 0, ',', '.'),
            'whatsapp' => $raffle['whatsapp_contact'],
        ];

        $text = "Hola {nombre}, tu boleto *{ticket_number}* para la rifa *{raffle_name}* esta reservado. "
              . "Valor: {price}. Envía el comprobante de pago al WhatsApp {whatsapp}. "
              . "Reserva valida por 4 horas.";

        $text = self::replaceVars($text, $vars);

        return [
            'channel' => 'whatsapp',
            'message_type' => 'reservation',
            'subject' => 'Boleto reservado - ' . $raffle['name'],
            'body_text' => $text,
            'body_html' => null,
            'variables' => $vars,
        ];
    }

    public static function buildPaymentConfirmedMessage(array $raffle, array $ticket, array $buyer): array
    {
        $vars = [
            'nombre' => $buyer['name'] ?? 'Participante',
            'raffle_name' => $raffle['name'],
            'ticket_number' => str_pad($ticket['ticket_number'], 4, '0', STR_PAD_LEFT),
            'draw_date' => date('d/m/Y', strtotime($raffle['draw_date'])),
        ];

        $text = "Hola {nombre}, tu pago para la rifa *{raffle_name}* fue confirmado. "
              . "Boleto: *{ticket_number}*. Sorteo: {draw_date}. Mucha suerte!";

        $text = self::replaceVars($text, $vars);

        return [
            'channel' => 'whatsapp',
            'message_type' => 'payment_confirmed',
            'subject' => 'Pago confirmado - ' . $raffle['name'],
            'body_text' => $text,
            'body_html' => null,
            'variables' => $vars,
        ];
    }

    /**
     * Mensaje para los participantes que NO ganaron cuando SÍ hubo ganador:
     * agradecimiento + resultado + quién ganó. $ticketNumbers agrupa todos
     * los boletos del comprador en esa rifa (un solo mensaje por persona).
     */
    public static function buildParticipantResultMessage(array $raffle, array $ticketNumbers, array $buyer, array $lottery, string $winningDigits, string $winnerName, string $winnerTicket): array
    {
        $tickets = implode(', ', array_map(
            fn($n) => str_pad($n, 4, '0', STR_PAD_LEFT),
            $ticketNumbers
        ));
        $vars = [
            'nombre' => $buyer['name'] ?? 'Participante',
            'raffle_name' => $raffle['name'],
            'tickets' => $tickets,
            'lottery_name' => $lottery['name'] ?? '',
            'winning_number' => $winningDigits,
            'draw_date' => date('d/m/Y', strtotime($raffle['draw_date'])),
            'winner_name' => $winnerName,
            'winner_ticket' => str_pad($winnerTicket, 4, '0', STR_PAD_LEFT),
        ];
        $text = "Hola {nombre}, gracias por participar en la rifa *{raffle_name}*. "
              . "El numero ganador de la {lottery_name} del {draw_date} fue *{winning_number}*. "
              . "Felicitaciones a *{winner_name}*, quien gano con el boleto *{winner_ticket}*. "
              . "Tu participacion: boleto(s) {tickets}. Esta vez no fue, pero sigue participando en misrifas.online!";
        $text = self::replaceVars($text, $vars);
        $html = self::buildEmailHtml(
            'Resultado de la rifa ' . $raffle['name'],
            "<h2>Gracias por participar, {nombre}!</h2>"
            . "<p>La rifa <strong>{raffle_name}</strong> ya tuvo sorteo con la {lottery_name} del {draw_date}.</p>"
            . "<p>Numero ganador: <strong style='color:#22c55e;font-size:1.5em;'>{winning_number}</strong></p>"
            . "<p>Felicitaciones a <strong>{winner_name}</strong>, quien gano con el boleto <strong>{winner_ticket}</strong>.</p>"
            . "<p>Tu participacion: boleto(s) <strong>{tickets}</strong>. Esta vez no fue, pero sigue participando!</p>",
            self::escapeVars($vars)
        );
        return [
            'channel' => 'email',
            'message_type' => 'no_winner',
            'subject' => 'Resultado de la rifa ' . $raffle['name'],
            'body_text' => $text,
            'body_html' => $html,
            'variables' => $vars,
        ];
    }

    /**
     * Mensaje de re-sorteo: nadie ganó, los boletos siguen participando y el
     * sorteo se reprograma. Incluye la nueva fecha (la versión anterior decía
     * "esta vez no fue" sin aclarar que la rifa continuaba).
     */
    public static function buildResorteoMessage(array $raffle, array $ticketNumbers, array $buyer, array $lottery, string $winningDigits, string $nextDrawDate): array
    {
        $tickets = implode(', ', array_map(
            fn($n) => str_pad($n, 4, '0', STR_PAD_LEFT),
            $ticketNumbers
        ));
        $vars = [
            'nombre' => $buyer['name'] ?? 'Participante',
            'raffle_name' => $raffle['name'],
            'tickets' => $tickets,
            'lottery_name' => $lottery['name'] ?? '',
            'winning_number' => $winningDigits,
            'draw_date' => date('d/m/Y', strtotime($raffle['draw_date'])),
            'next_date' => date('d/m/Y', strtotime($nextDrawDate)),
        ];
        $text = "Hola {nombre}, la rifa *{raffle_name}* jugo el {draw_date} con la {lottery_name}: "
              . "el numero fue *{winning_number}* y ningun boleto vendido resulto ganador. "
              . "Tu(s) boleto(s) {tickets} SIGUEN participando: el sorteo se reprogramo para el *{next_date}*. Mucha suerte!";
        $text = self::replaceVars($text, $vars);
        $html = self::buildEmailHtml(
            'Re-sorteo de la rifa ' . $raffle['name'],
            "<h2>La rifa {raffle_name} se reprogramo</h2>"
            . "<p>El numero de la {lottery_name} del {draw_date} fue <strong>{winning_number}</strong> y ningun boleto vendido resulto ganador.</p>"
            . "<p>Tu(s) boleto(s) <strong>{tickets}</strong> siguen participando.</p>"
            . "<p>Nueva fecha de sorteo: <strong style='color:#fbbf24;font-size:1.2em;'>{next_date}</strong>. Mucha suerte!</p>",
            self::escapeVars($vars)
        );
        return [
            'channel' => 'email',
            'message_type' => 'no_winner',
            'subject' => 'Re-sorteo: la rifa ' . $raffle['name'] . ' se reprogramó',
            'body_text' => $text,
            'body_html' => $html,
            'variables' => $vars,
        ];
    }

    /** Escapa los valores (nombres de compradores, etc.) para plantillas HTML. */
    private static function escapeVars(array $vars): array
    {
        return array_map(
            fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'),
            $vars
        );
    }

    private static function replaceVars(string $template, array $vars): string
    {
        return str_replace(
            array_map(fn($k) => '{' . $k . '}', array_keys($vars)),
            array_values($vars),
            $template
        );
    }

    private static function buildEmailHtml(string $subject, string $bodyTemplate, array $vars): string
    {
        $body = self::replaceVars($bodyTemplate, $vars);

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#0f172a;font-family:sans-serif;">'
            . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#2563eb;padding:24px;text-align:center;">'
            . '<h1 style="color:#fff;margin:0;font-size:24px;">MisRifas</h1>'
            . '</div>'
            . '<div style="padding:32px;">'
            . '<h2 style="color:#f1f5f9;margin:0 0 16px;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<div style="color:#94a3b8;line-height:1.6;">' . $body . '</div>'
            . '</div>'
            . '<div style="padding:16px;text-align:center;color:#64748b;font-size:12px;">'
            . 'MisRifas - Rifas Digitales Colombia'
            . '</div></div></body></html>';
    }
}
