<?php

declare(strict_types=1);

require_once __DIR__ . '/TicketStateMachine.php';

/**
 * Revisión de pagos manuales (promt2.md §10).
 *
 * ÚNICO servicio de dominio para confirmar/rechazar un comprobante. Las dos
 * vías (WhatsApp y panel) llaman exactamente aquí — la única diferencia es el
 * `source` que queda en la bitácora. Nunca dupliques esta lógica.
 */
final class PaymentReview
{
    /** §10.2: motivos de rechazo (lista corta, obligatoria). */
    public const REJECT_REASONS = [
        'no_llego'   => 'La plata no llegó',
        'monto'      => 'El monto no coincide',
        'ilegible'   => 'Comprobante ilegible',
        'repetido'   => 'Comprobante repetido',
        'otro'       => 'Otro motivo',
    ];

    /**
     * Estado del ticket para revisión, validando que la rifa pertenezca al
     * vendedor. Devuelve null si no existe o no es suyo.
     */
    public static function ticketDelVendedor(PDO $db, int $ticketId, int $vendorId): ?array
    {
        $stmt = $db->prepare("
            SELECT t.id, t.status, t.ticket_number, t.raffle_id,
                   r.name AS raffle_name,
                   p.id AS payment_id
            FROM tickets t
            JOIN raffles r ON t.raffle_id = r.id
            LEFT JOIN payments p ON p.ticket_id = t.id AND p.transaction_status = 'pending'
            WHERE t.id = ? AND COALESCE(r.vendor_id, r.created_by) = ?
        ");
        $stmt->execute([$ticketId, $vendorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Confirmar el pago: pending_review → paid (emite boleta en la misma
     * transacción vía la máquina de estados).
     *
     * @return array{ok:bool, estado:string, mensaje:string}
     */
    public static function aprobar(PDO $db, int $ticketId, int $vendorId, string $source): array
    {
        $info = self::ticketDelVendedor($db, $ticketId, $vendorId);
        if (!$info) {
            return ['ok' => false, 'estado' => 'sin_permiso', 'mensaje' => 'No tienes permiso sobre este boleto'];
        }
        if (!$info['payment_id']) {
            return ['ok' => false, 'estado' => 'sin_pago', 'mensaje' => 'Este boleto no tiene un pago pendiente de revisión'];
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
            $stmt->execute([$ticketId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['status'], ['reserved', 'pending_review'], true)) {
                $db->rollBack();
                return ['ok' => false, 'estado' => (string)($row['status'] ?? 'inexistente'),
                        'mensaje' => self::explicarEstado((string)($row['status'] ?? ''))];
            }
            if ($row['status'] === 'reserved') {
                $row = TicketStateMachine::apply($db, $row, 'pending_review', [
                    'actor' => 'system', 'source' => $source,
                    'reason' => 'normalización: comprobante previo a la máquina de estados',
                ]);
            }
            TicketStateMachine::apply($db, $row, 'paid', [
                'actor' => 'vendor', 'source' => $source, 'actor_id' => $vendorId,
                'reason' => 'pago confirmado por el vendedor',
                'detail' => ['payment_id' => (int)$info['payment_id']],
                'fields' => ['paid_at' => date('Y-m-d H:i:s'), 'payment_id' => (int)$info['payment_id'] ?: null],
            ]);
            if ($info['payment_id']) {
                $db->prepare("UPDATE payments SET transaction_status = 'completed', verified_at = NOW() WHERE id = ?")
                   ->execute([$info['payment_id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Confirmación al COMPRADOR por CORREO ("el correo va siempre") con el
        // enlace de su boleta digital. La boleta por WhatsApp la envía el
        // llamador (Boleta::enviarPorWhatsApp) — aquí solo el canal que
        // faltaba: antes el comprador sin WhatsApp no recibía NADA al
        // aprobarse su pago. Best-effort: nunca revierte la aprobación.
        try {
            $stmt = $db->prepare("
                SELECT t.ticket_number, t.opportunities, t.ticket_code, t.buyer_name, t.user_id,
                       r.name, r.draw_date, r.image_url, u.email AS buyer_email, u.name AS user_name, u.phone_whatsapp
                FROM tickets t
                JOIN raffles r ON r.id = t.raffle_id
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.id = ?
            ");
            $stmt->execute([$ticketId]);
            if (($d = $stmt->fetch(PDO::FETCH_ASSOC)) && !empty($d['buyer_email'])) {
                require_once __DIR__ . '/MessageBuilderService.php';
                require_once __DIR__ . '/Boleta.php';
                $boletaUrl = !empty($d['ticket_code']) ? Boleta::urlPublica((string)$d['ticket_code']) : '';
                $msg = MessageBuilderService::buildPaymentConfirmedMessage(
                    ['name' => $d['name'], 'draw_date' => $d['draw_date'], 'image_url' => $d['image_url']],
                    ['ticket_number' => $d['ticket_number'], 'opportunities' => $d['opportunities']],
                    ['name' => $d['buyer_name'] ?: $d['user_name']],
                    $boletaUrl
                );
                $db->prepare("
                    INSERT INTO message_queue (raffle_id, vendor_id, recipient_user_id, recipient_phone, recipient_email,
                                               channel, message_type, subject, body_text, body_html, status, scheduled_at, created_at)
                    SELECT t.raffle_id, ?, t.user_id, ?, ?, 'email', 'payment_confirmed', ?, ?, ?, 'pending', NOW(), NOW()
                    FROM tickets t WHERE t.id = ?
                ")->execute([$vendorId, $d['phone_whatsapp'], $d['buyer_email'],
                    $msg['subject'], $msg['body_text'], $msg['body_html'], $ticketId]);
            }
        } catch (Throwable $e) {
            // No todos los llamadores cargan utils/Logger (p. ej. el webhook).
            error_log('[PaymentReview] correo de confirmación no encolado ticket=' . $ticketId . ' ' . $e->getMessage());
        }

        return ['ok' => true, 'estado' => 'paid',
                'mensaje' => 'Pago del boleto #' . $info['ticket_number'] . ' confirmado. Boleta emitida.'];
    }

    /**
     * Rechazar el pago: pending_review → available. El motivo es OBLIGATORIO
     * y de la lista corta (§10.2).
     */
    public static function rechazar(PDO $db, int $ticketId, int $vendorId, string $source, string $reasonKey): array
    {
        if (!array_key_exists($reasonKey, self::REJECT_REASONS)) {
            return ['ok' => false, 'estado' => 'motivo_invalido',
                    'mensaje' => 'Motivo de rechazo requerido: ' . implode(', ', array_keys(self::REJECT_REASONS))];
        }
        $info = self::ticketDelVendedor($db, $ticketId, $vendorId);
        if (!$info) {
            return ['ok' => false, 'estado' => 'sin_permiso', 'mensaje' => 'No tienes permiso sobre este boleto'];
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? FOR UPDATE');
            $stmt->execute([$ticketId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !in_array($row['status'], ['reserved', 'pending_review'], true)) {
                $db->rollBack();
                return ['ok' => false, 'estado' => (string)($row['status'] ?? 'inexistente'),
                        'mensaje' => self::explicarEstado((string)($row['status'] ?? ''))];
            }
            if ($row['status'] === 'reserved') {
                $row = TicketStateMachine::apply($db, $row, 'pending_review', [
                    'actor' => 'system', 'source' => $source,
                    'reason' => 'normalización: comprobante previo a la máquina de estados',
                ]);
            }
            TicketStateMachine::apply($db, $row, 'available', [
                'actor' => 'vendor', 'source' => $source, 'actor_id' => $vendorId,
                'reason' => 'rechazado: ' . self::REJECT_REASONS[$reasonKey],
                'detail' => ['payment_id' => (int)$info['payment_id'], 'motivo' => $reasonKey],
            ]);
            if ($info['payment_id']) {
                $db->prepare("UPDATE payments SET transaction_status = 'failed' WHERE id = ?")
                   ->execute([$info['payment_id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ['ok' => true, 'estado' => 'available',
                'mensaje' => 'Pago del boleto #' . $info['ticket_number'] . ' rechazado (' . self::REJECT_REASONS[$reasonKey] . '). El número volvió a la venta.'];
    }

    /** §10.1: si el ticket ya no está en revisión, se explica, no se fuerza. */
    public static function explicarEstado(string $status): string
    {
        return match ($status) {
            'paid'      => 'Ese boleto ya está PAGADO (alguien lo confirmó antes).',
            'available' => 'Ese boleto ya no está en revisión: volvió a estar disponible (venció o fue rechazado).',
            'reserved'  => 'Ese boleto está reservado pero aún sin comprobante.',
            'held'      => 'Ese boleto está apartado por ti, no en revisión de pago.',
            default     => 'Ese boleto no existe o ya no está en revisión.',
        };
    }
}
