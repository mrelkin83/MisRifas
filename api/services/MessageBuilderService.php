<?php

require_once __DIR__ . '/../api/services/MessageBuilderService.php';

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

        $text = "Felicitaciones {nombre}! Ganaste la rifa *{raffle_name}* con el numero *{ticket_number}*. "
              . "El numero ganador de la {lottery_name} del {draw_date} fue *{full_number}*. "
              . "Pronto te contactaremos para la entrega del premio.";

        $text = self::replaceVars($text, $vars);

        $html = self::buildEmailHtml(
            'Felicitaciones - Ganaste la rifa!',
            "<h2 style='color:#fbbf24;'>Felicitaciones {nombre}!</h2>"
            . "<p>Ganaste la rifa <strong>{raffle_name}</strong> con el boleto <strong>{ticket_number}</strong>.</p>"
            . "<p>El numero ganador de la {lottery_name} del {draw_date} fue <strong style='color:#22c55e;font-size:1.5em;'>{full_number}</strong></p>"
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
