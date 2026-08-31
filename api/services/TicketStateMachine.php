<?php

declare(strict_types=1);

require_once __DIR__ . '/DomainExceptions.php';

/**
 * Máquina de estados de tickets (promt2.md §7).
 *
 * ÚNICO punto del sistema autorizado a escribir tickets.status. Cualquier
 * `UPDATE tickets SET status` fuera de esta clase es un bug.
 *
 * Garantías:
 *  - Toda transición valida contra la tabla de transiciones legales; lo demás
 *    lanza InvalidTransition (nunca se corrige el estado en silencio).
 *  - Toda transición bloquea la fila (SELECT ... FOR UPDATE) dentro de una
 *    transacción — la del llamador si ya hay una abierta, o una propia.
 *  - Toda transición escribe su fila en ticket_events EN LA MISMA transacción.
 *  - paid → available SOLO con actor 'admin' y motivo — y al liberarse, el
 *    código de boleta se invalida (queda en el evento, nunca se reutiliza).
 *
 * Nunca hagas llamadas HTTP, envíos de WhatsApp ni generación de imágenes
 * dentro de la transacción: encola el efecto y despáchalo tras el commit.
 */
final class TicketStateMachine
{
    /** from => [to, ...] — la tabla de §7.2. */
    private const TRANSITIONS = [
        'available'      => ['reserved', 'held', 'paid'],
        'reserved'       => ['pending_review', 'available'],
        'pending_review' => ['paid', 'available'],
        'held'           => ['paid', 'available'],
        'paid'           => ['available'], // solo admin, ver guardas
    ];

    /** Columnas extra que una transición puede escribir (whitelist). */
    private const FIELD_WHITELIST = [
        'user_id', 'reserved_at', 'reserved_until', 'paid_at', 'payment_id',
        'payment_method', 'ticket_code', 'issued_at', 'buyer_name', 'buyer_phone',
        'held_by_vendor_id', 'holder_name', 'holder_phone', 'held_at', 'held_note',
    ];

    /**
     * Bloquea un ticket por rifa+número. DEBE llamarse dentro de una
     * transacción abierta (si no, el lock no sobrevive al retorno).
     */
    public static function lockByNumber(PDO $db, int $raffleId, string $number): array
    {
        if (!$db->inTransaction()) {
            throw new LogicException('lockByNumber requiere una transacción abierta');
        }
        $stmt = $db->prepare(
            'SELECT * FROM tickets WHERE raffle_id = :raffle AND ticket_number = :number FOR UPDATE'
        );
        $stmt->execute(['raffle' => $raffleId, 'number' => $number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new TicketNotFound($raffleId, $number);
        }
        return $row;
    }

    /**
     * Transición por id de ticket. Abre su propia transacción si el llamador
     * no tiene una; si ya hay una, se suma a ella (multi-ticket atómico).
     *
     * $ctx:
     *  - actor:    'buyer' | 'vendor' | 'system' | 'admin'   (requerido)
     *  - source:   'web' | 'whatsapp' | 'dashboard' | 'cron' | 'admin' (requerido)
     *  - actor_id: int|null
     *  - reason:   string|null (≤120)
     *  - detail:   array|null  (se guarda como JSON)
     *  - fields:   array columna => valor (solo FIELD_WHITELIST)
     *
     * Devuelve la fila del ticket ya actualizada.
     */
    public static function transition(PDO $db, int $ticketId, string $to, array $ctx): array
    {
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $stmt = $db->prepare('SELECT * FROM tickets WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $ticketId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new TicketNotFound(0, (string)$ticketId);
            }
            $row = self::apply($db, $row, $to, $ctx);
            if ($ownTx) {
                $db->commit();
            }
            return $row;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transición sobre una fila YA bloqueada por lockByNumber()/FOR UPDATE.
     * El llamador es dueño de la transacción. Devuelve la fila actualizada.
     */
    public static function apply(PDO $db, array $row, string $to, array $ctx): array
    {
        if (!$db->inTransaction()) {
            throw new LogicException('apply() requiere una transacción abierta');
        }
        $from = (string)$row['status'];
        $ticketId = (int)$row['id'];

        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidTransition($ticketId, $from, $to);
        }

        $actor  = (string)($ctx['actor'] ?? '');
        $source = (string)($ctx['source'] ?? '');
        if (!in_array($actor, ['buyer', 'vendor', 'system', 'admin'], true)) {
            throw new LogicException("actor inválido: '{$actor}'");
        }
        if (!in_array($source, ['web', 'whatsapp', 'dashboard', 'cron', 'admin'], true)) {
            throw new LogicException("source inválido: '{$source}'");
        }

        // Guarda §2.3: un pagado solo lo revierte un admin, con motivo.
        if ($from === 'paid') {
            if ($actor !== 'admin' || $source !== 'admin' || empty($ctx['reason'])) {
                throw new InvalidTransition($ticketId, $from, $to);
            }
        }

        $fields = [];
        foreach (($ctx['fields'] ?? []) as $col => $val) {
            if (!in_array($col, self::FIELD_WHITELIST, true)) {
                throw new LogicException("Campo no permitido en transición: {$col}");
            }
            $fields[$col] = $val;
        }

        $detail = $ctx['detail'] ?? [];

        // Al volver a 'available' se limpian los campos transitorios del
        // ocupante anterior; si venía de 'paid', el código de boleta se
        // invalida (queda solo en el evento).
        if ($to === 'available') {
            foreach (['user_id', 'reserved_at', 'reserved_until', 'held_by_vendor_id',
                      'holder_name', 'holder_phone', 'held_at', 'held_note'] as $col) {
                $fields[$col] = null;
            }
            if ($from === 'paid') {
                if (!empty($row['ticket_code'])) {
                    $detail['ticket_code_invalidado'] = $row['ticket_code'];
                }
                foreach (['paid_at', 'payment_id', 'payment_method', 'ticket_code',
                          'issued_at', 'buyer_name', 'buyer_phone'] as $col) {
                    $fields[$col] = null;
                }
            }
        }

        $sets = ['status = :status'];
        $params = ['status' => $to, 'id' => $ticketId];
        foreach ($fields as $col => $val) {
            $sets[] = "{$col} = :f_{$col}";
            $params["f_{$col}"] = $val;
        }
        $db->prepare('UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = :id')
           ->execute($params);

        // Bitácora §14 — misma transacción.
        $db->prepare(
            'INSERT INTO ticket_events
                (ticket_id, raffle_id, from_status, to_status, actor, actor_id, source, reason, detail)
             VALUES (:tid, :rid, :f, :t, :actor, :actor_id, :source, :reason, :detail)'
        )->execute([
            'tid'      => $ticketId,
            'rid'      => (int)$row['raffle_id'],
            'f'        => $from,
            't'        => $to,
            'actor'    => $actor,
            'actor_id' => isset($ctx['actor_id']) ? (int)$ctx['actor_id'] : null,
            'source'   => $source,
            'reason'   => isset($ctx['reason']) ? mb_substr((string)$ctx['reason'], 0, 120) : null,
            'detail'   => $detail !== [] ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
        ]);

        $row['status'] = $to;
        foreach ($fields as $col => $val) {
            $row[$col] = $val;
        }
        return $row;
    }

    /** TTL de reserva en minutos (setting reservation_ttl_minutes, default 45). */
    public static function reservationTtlMinutes(PDO $db): int
    {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'reservation_ttl_minutes'");
        $stmt->execute();
        $v = (int)$stmt->fetchColumn();
        return $v > 0 ? $v : 45;
    }
}
