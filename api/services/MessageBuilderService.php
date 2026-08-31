<?php

class MessageBuilderService
{
    /**
     * Registro de plantillas EDITABLES (v4.13). El texto por defecto vive
     * aquí; message_templates guarda solo las personalizadas (override).
     * Las variables {así} se reemplazan al enviar. El editor del panel usa
     * este registro como fuente de verdad.
     */
    public const PLANTILLAS = [
        'winner' => [
            'nombre' => '🏆 Al ganador del sorteo',
            'descripcion' => 'WhatsApp y correo al ganador. El enlace de aceptación {confirm_url} es obligatorio: si lo quitas, el sistema lo agrega al final.',
            'vars' => ['nombre', 'raffle_name', 'ticket_number', 'lottery_name', 'winning_number', 'draw_date', 'confirm_url'],
            'default' => "Felicitaciones {nombre}! Ganaste la rifa *{raffle_name}* con el numero *{ticket_number}*. El numero ganador de la {lottery_name} del {draw_date} fue *{full_number}*. Confirma que aceptas tu premio aqui: {confirm_url} . Pronto te contactaremos para la entrega del premio.",
        ],
        'participant_result' => [
            'nombre' => '🎟️ A los participantes (hubo ganador)',
            'descripcion' => 'A cada comprador que no ganó: resultado, ganador y sus boletos.',
            'vars' => ['nombre', 'raffle_name', 'tickets', 'lottery_name', 'winning_number', 'draw_date', 'winner_name', 'winner_ticket'],
            'default' => "Hola {nombre}, gracias por participar en la rifa *{raffle_name}*. El numero ganador de la {lottery_name} del {draw_date} fue *{winning_number}*. Felicitaciones a *{winner_name}*, quien gano con el boleto *{winner_ticket}*. Tu participacion: boleto(s) {tickets}. Esta vez no fue, pero sigue participando en misrifas.online!",
        ],
        'resorteo' => [
            'nombre' => '🔁 Reprogramación (nadie ganó)',
            'descripcion' => 'El número no estaba vendido/pagado: los boletos siguen y hay nueva fecha {next_date} (si la quitas, el sistema la agrega).',
            'vars' => ['nombre', 'raffle_name', 'tickets', 'lottery_name', 'winning_number', 'draw_date', 'next_date'],
            'default' => "Hola {nombre}, la rifa *{raffle_name}* jugo el {draw_date} con la {lottery_name}: el numero fue *{winning_number}* y ningun boleto vendido resulto ganador. Tu(s) boleto(s) {tickets} SIGUEN participando: el sorteo se reprogramo para el *{next_date}*. Mucha suerte!",
        ],
        'vendor_winner' => [
            'nombre' => '📢 Al organizador (su rifa tuvo ganador)',
            'descripcion' => 'Aviso al organizador con los datos del ganador para coordinar la entrega.',
            'vars' => ['raffle_name', 'winner_name', 'winner_phone', 'ticket_number', 'winning_number'],
            'default' => "Tu rifa *{raffle_name}* tuvo ganador! Ganador: *{winner_name}* ({winner_phone}) con boleto *{ticket_number}*. Numero ganador: *{winning_number}*. Contacta al ganador para entregar el premio.",
        ],
        'no_winner' => [
            'nombre' => '📄 Resultado individual (sin ganador para ese boleto)',
            'descripcion' => 'Resultado a un comprador puntual.',
            'vars' => ['nombre', 'raffle_name', 'ticket_number', 'lottery_name', 'winning_number', 'draw_date'],
            'default' => "Hola {nombre}, la rifa *{raffle_name}* ya tuvo sorteo. El numero ganador de la {lottery_name} fue *{winning_number}*. Tu boleto fue *{ticket_number}*. Sigue participando en misrifas.com!",
        ],
        'reservation' => [
            'nombre' => '⏳ Boleto reservado',
            'descripcion' => 'Confirmación de reserva con el valor y el WhatsApp del organizador.',
            'vars' => ['nombre', 'raffle_name', 'ticket_number', 'price', 'whatsapp'],
            'default' => "Hola {nombre}, tu boleto *{ticket_number}* para la rifa *{raffle_name}* esta reservado. Valor: {price}. Envía el comprobante de pago al WhatsApp {whatsapp}. Reserva valida por 4 horas.",
        ],
        'payment_confirmed' => [
            'nombre' => '✅ Pago confirmado',
            'descripcion' => 'Al comprador cuando el organizador confirma su pago.',
            'vars' => ['nombre', 'raffle_name', 'ticket_number', 'draw_date'],
            'default' => "Hola {nombre}, tu pago para la rifa *{raffle_name}* fue confirmado. Boleto: *{ticket_number}*. Sorteo: {draw_date}. Mucha suerte!",
        ],
    ];

    /** Overrides de BD, cacheados por request. */
    private static ?array $overrides = null;

    private static function overrides(): array
    {
        if (self::$overrides === null) {
            self::$overrides = [];
            try {
                $db = Database::getInstance()->getConnection();
                foreach ($db->query('SELECT template_key, body_text FROM message_templates') as $r) {
                    self::$overrides[$r['template_key']] = (string)$r['body_text'];
                }
            } catch (\Throwable $e) {
                // Sin tabla o sin BD: se usan los textos por defecto.
            }
        }
        return self::$overrides;
    }

    /** Texto vigente de una plantilla (override de BD o el default del código). */
    public static function plantilla(string $key): string
    {
        $ov = self::overrides();
        $txt = isset($ov[$key]) && trim($ov[$key]) !== '' ? $ov[$key] : (self::PLANTILLAS[$key]['default'] ?? '');
        // Guardas: las variables CRÍTICAS no se pueden perder al editar.
        if ($key === 'winner' && strpos($txt, '{confirm_url}') === false) {
            $txt .= " Confirma que aceptas tu premio aqui: {confirm_url}";
        }
        if ($key === 'resorteo' && strpos($txt, '{next_date}') === false) {
            $txt .= " Nueva fecha del sorteo: {next_date}.";
        }
        return $txt;
    }

    public static function esPersonalizada(string $key): bool
    {
        $ov = self::overrides();
        return isset($ov[$key]) && trim($ov[$key]) !== '';
    }

    /** Reinicia el caché (para tests y tras guardar en el editor). */
    public static function recargarPlantillas(): void
    {
        self::$overrides = null;
    }

    /** Cuerpo HTML genérico para plantillas personalizadas: el mismo texto
     *  editado, escapado y con saltos de línea, dentro del cascarón estándar. */
    private static function htmlDePlantilla(string $subject, string $tpl, array $raffle, array $vars, string $extraHtml = ''): string
    {
        $body = self::raffleImageHtml($raffle)
            . "<p style='font-size:15px;line-height:1.6;'>" . nl2br(htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8')) . '</p>'
            . $extraHtml;
        return self::buildEmailHtml($subject, $body, $vars);
    }
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

        $tpl = self::plantilla('winner');
        $text = self::replaceVars($tpl, $vars);

        $confirmBlockHtml = $confirmUrl !== ''
            ? "<p style='margin:24px 0;'><a href='{confirm_url}' style='background:#22c55e;color:#052e13;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:bold;display:inline-block;'>Confirmar aceptacion del premio</a></p>"
              . "<p style='font-size:12px;color:#94a3b8;'>Al confirmar dejas constancia publica de que aceptas el premio y el resultado del sorteo.</p>"
            : '';

        $html = self::esPersonalizada('winner')
            ? self::htmlDePlantilla('Felicitaciones - Ganaste la rifa!', $tpl, $raffle, $vars, $confirmBlockHtml)
            : self::buildEmailHtml(
                'Felicitaciones - Ganaste la rifa!',
                self::raffleImageHtml($raffle)
                . "<h2 style='color:#fbbf24;'>Felicitaciones {nombre}!</h2>"
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

        $tpl = self::plantilla('no_winner');
        $text = self::replaceVars($tpl, $vars);

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

        $text = self::replaceVars(self::plantilla('vendor_winner'), $vars);

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

        $text = self::replaceVars(self::plantilla('reservation'), $vars);

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

        $text = self::replaceVars(self::plantilla('payment_confirmed'), $vars);

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
        $tpl = self::plantilla('participant_result');
        $text = self::replaceVars($tpl, $vars);
        $html = self::esPersonalizada('participant_result')
            ? self::htmlDePlantilla('Resultado de la rifa ' . $raffle['name'], $tpl, $raffle, self::escapeVars($vars))
            : self::buildEmailHtml(
            'Resultado de la rifa ' . $raffle['name'],
            self::raffleImageHtml($raffle)
            . "<h2>Gracias por participar, {nombre}!</h2>"
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
        $tpl = self::plantilla('resorteo');
        $text = self::replaceVars($tpl, $vars);
        $html = self::esPersonalizada('resorteo')
            ? self::htmlDePlantilla('Re-sorteo de la rifa ' . $raffle['name'], $tpl, $raffle, self::escapeVars($vars))
            : self::buildEmailHtml(
            'Re-sorteo de la rifa ' . $raffle['name'],
            self::raffleImageHtml($raffle)
            . "<h2>La rifa {raffle_name} se reprogramo</h2>"
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

    /**
     * <img> con la imagen de la rifa para los emails (los clientes de correo
     * cargan imágenes por URL absoluta; no se adjunta nada al mensaje).
     * Devuelve '' si la rifa no tiene imagen.
     */
    private static function raffleImageHtml(array $raffle): string
    {
        $url = trim((string)($raffle['image_url'] ?? ''));
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $base = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
            $url = $base . '/public/' . ltrim($url, '/');
        }
        $esc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return "<img src='{$esc}' alt='' width='536' style='width:100%;max-width:536px;border-radius:10px;display:block;margin:0 0 16px;'>";
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
