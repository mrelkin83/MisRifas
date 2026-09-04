<?php

declare(strict_types=1);

require_once __DIR__ . '/TicketCode.php';

/**
 * Consulta y presentación de la boleta digital (promt2.md §9.4).
 */
final class Boleta
{
    public const MODE_LABELS = [
        'last_2'  => 'Últimas 2 cifras',
        'first_2' => 'Primeras 2 cifras',
        'last_3'  => 'Últimas 3 cifras',
        'first_3' => 'Primeras 3 cifras',
        'last_4'  => 'Últimas 4 cifras',
    ];

    /** Boleta VÁLIDA por código (ticket paid con ese código). */
    public static function buscar(PDO $db, string $codigo): ?array
    {
        $code = TicketCode::normalize($codigo);
        if (!TicketCode::isValid($code)) {
            return null;
        }
        $stmt = $db->prepare("
            SELECT t.id, t.ticket_number, t.ticket_code, t.issued_at, t.status,
                   t.opportunities, t.buyer_name, t.buyer_phone, t.payment_method,
                   r.id AS raffle_id, r.name AS raffle_name, r.digits, r.winning_mode,
                   r.draw_date, r.status AS raffle_status, r.ticket_price,
                   l.name AS lottery_name,
                   v.business_name AS vendor_name, v.slug AS vendor_slug,
                   COALESCE(p.amount, r.ticket_price) AS amount
            FROM tickets t
            JOIN raffles r  ON r.id = t.raffle_id
            JOIN lotteries l ON l.id = r.lottery_id
            JOIN vendors v  ON v.id = COALESCE(r.vendor_id, r.created_by)
            LEFT JOIN payments p ON p.ticket_id = t.id AND p.transaction_status = 'completed'
            WHERE t.ticket_code = ? AND t.status = 'paid'
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ¿El código perteneció a una boleta que fue ANULADA por vía
     * administrativa? (al liberarse, el código se invalida y queda solo en la
     * bitácora — §9.3).
     */
    public static function fueAnulada(PDO $db, string $codigo): bool
    {
        $code = TicketCode::normalize($codigo);
        if (!TicketCode::isValid($code)) {
            return false;
        }
        // JSON_EXTRACT y no LIKE: MySQL normaliza el JSON almacenado (agrega
        // espacio tras los dos puntos) y el LIKE literal no coincide.
        $stmt = $db->prepare("
            SELECT 1 FROM ticket_events
            WHERE JSON_UNQUOTE(JSON_EXTRACT(detail, '$.ticket_code_invalidado')) = ?
            LIMIT 1
        ");
        $stmt->execute([$code]);
        return (bool)$stmt->fetchColumn();
    }

    /** "Juan Pablo Pérez" → "Juan P." (§9.4: la URL es compartible). */
    public static function nombreEnmascarado(?string $nombre): string
    {
        $partes = preg_split('/\s+/', trim((string)$nombre)) ?: [];
        if (!$partes || $partes[0] === '') {
            return 'Comprador';
        }
        $out = $partes[0];
        if (count($partes) > 1) {
            $out .= ' ' . mb_strtoupper(mb_substr($partes[1], 0, 1)) . '.';
        }
        return $out;
    }

    /** "3001234567" → "300****567". */
    public static function celularEnmascarado(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string)$phone);
        if (strlen($d) < 7) {
            return '';
        }
        return substr($d, 0, 3) . '****' . substr($d, -3);
    }

    public static function urlPublica(string $codigo): string
    {
        $base = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
        return $base . BASE_PATH . '/public/boleta.php?c=' . TicketCode::format($codigo);
    }

    /** Igual que buscar(), pero por id de ticket (para el envío al emitir). */
    public static function buscarPorTicketId(PDO $db, int $ticketId): ?array
    {
        $stmt = $db->prepare("SELECT ticket_code FROM tickets WHERE id = ? AND status = 'paid' AND ticket_code IS NOT NULL");
        $stmt->execute([$ticketId]);
        $code = (string)$stmt->fetchColumn();
        return $code !== '' ? self::buscar($db, $code) : null;
    }

    /**
     * Envía la boleta PNG por WhatsApp al comprador (§9.6), por la instancia
     * del VENDEDOR. Best-effort y SIEMPRE después del commit — jamás dentro
     * de la transacción.
     */
    public static function enviarPorWhatsApp(PDO $db, int $ticketId, int $vendorId): bool
    {
        try {
            $b = self::buscarPorTicketId($db, $ticketId);
            if (!$b || empty($b['buyer_phone'])) {
                return false;
            }
            require_once __DIR__ . '/BoletaImage.php';
            require_once __DIR__ . '/../whatsapp/notify.php';
            $path = BoletaImage::ensure(self::datosImagen($b));
            $nums = json_decode((string)($b['opportunities'] ?? ''), true);
            $nums = (is_array($nums) && $nums) ? $nums : [(string)$b['ticket_number']];
            // Al comprador JAMÁS se le menciona el consecutivo del boleto
            // (regla de producto): solo sus números en juego.
            $caption = '🎟️ Tu boleta de "' . $b['raffle_name'] . '"'
                . "\n🍀 Juega con: " . implode(' · ', $nums)
                . "\nCompruébala cuando quieras: " . self::urlPublica($b['ticket_code']);
            return notificarImagenVendor($vendorId, (string)$b['buyer_phone'], base64_encode((string)file_get_contents($path)), $caption);
        } catch (\Throwable $e) {
            Logger::error('Envío de boleta WA falló: ' . $e->getMessage(), ['ticket_id' => $ticketId]);
            return false;
        }
    }

    /** Datos listos para BoletaImage::ensure(). */
    public static function datosImagen(array $b): array
    {
        // Los números QUE JUEGAN en el sorteo (oportunidades) son lo relevante
        // para el comprador y el sistema; el consecutivo del boleto es solo un
        // identificador y NO participa en el sorteo.
        $numeros = json_decode((string)($b['opportunities'] ?? ''), true);
        if (!is_array($numeros) || !$numeros) {
            $numeros = [(string)$b['ticket_number']];
        }
        return [
            'ticket_code'   => $b['ticket_code'],
            'ticket_number' => $b['ticket_number'],
            'numeros'       => array_map('strval', $numeros),
            'raffle_name'   => $b['raffle_name'],
            'lottery_name'  => $b['lottery_name'],
            'draw_date'     => $b['draw_date'],
            'mode_label'    => self::MODE_LABELS[$b['winning_mode']] ?? $b['winning_mode'],
            'buyer_masked'  => self::nombreEnmascarado($b['buyer_name']),
            'vendor_name'   => $b['vendor_name'],
            'amount'        => $b['amount'],
            'issued_at'     => $b['issued_at'],
            'url'           => self::urlPublica($b['ticket_code']),
        ];
    }
}
