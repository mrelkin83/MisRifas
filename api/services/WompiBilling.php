<?php

declare(strict_types=1);

/**
 * Cobro de la plataforma vía Wompi (comisión % o tarifa por talonario).
 *
 * Flujo: el vendedor pulsa "Pagar" → linkPago() arma el Web Checkout de
 * Wompi con referencia única y firma de integridad → Wompi notifica el
 * webhook (api/payments/wompi-billing-webhook.php) → si la firma del evento
 * es válida, el estado es APPROVED y el monto coincide, se reactiva TODO
 * automáticamente (lo mismo que el "Marcar pagada" manual del super_admin,
 * que queda como plan de contingencia).
 *
 * Llaves: system_settings wompi_platform_* (Configuración → Comisiones).
 */
class WompiBilling
{
    public const REF_PREFIJO = 'MRCOBRO';

    public static function config(PDO $db): array
    {
        $cfg = ['public_key' => '', 'integrity_secret' => '', 'events_secret' => '', 'private_key' => ''];
        $q = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'wompi_platform_%'");
        foreach ($q as $r) {
            $cfg[str_replace('wompi_platform_', '', $r['setting_key'])] = trim((string)$r['setting_value']);
        }
        return $cfg;
    }

    /** ¿Hay lo mínimo para cobrar automático? (privada es opcional) */
    public static function configurado(PDO $db): bool
    {
        $c = self::config($db);
        return $c['public_key'] !== '' && $c['integrity_secret'] !== '' && $c['events_secret'] !== '';
    }

    /**
     * Link de Web Checkout para el cobro de una rifa.
     * @return array{url:string, reference:string, amount_in_cents:int}
     */
    public static function linkPago(PDO $db, array $raffle): array
    {
        $c = self::config($db);
        $amountCents = (int)round(((float)$raffle['commission_amount']) * 100);
        // Referencia parseable por el webhook: MRCOBRO-<raffleId>-<epoch>
        $reference = self::REF_PREFIJO . '-' . (int)$raffle['id'] . '-' . time();
        $firma = hash('sha256', $reference . $amountCents . 'COP' . $c['integrity_secret']);
        $redirect = rtrim((string)(getenv('APP_URL') ?: ''), '/') . BASE_PATH . '/public/vendor/index.php';
        $url = 'https://checkout.wompi.co/p/?' . http_build_query([
            'public-key' => $c['public_key'],
            'currency' => 'COP',
            'amount-in-cents' => $amountCents,
            'reference' => $reference,
            'signature:integrity' => $firma,
            'redirect-url' => $redirect,
        ]);
        return ['url' => $url, 'reference' => $reference, 'amount_in_cents' => $amountCents];
    }

    /**
     * Verifica la firma del evento del webhook (checksum de Wompi):
     * sha256( concat(valores de signature.properties en orden) + timestamp + events_secret ).
     */
    public static function firmaValida(array $evento, string $eventsSecret): bool
    {
        $props = $evento['signature']['properties'] ?? null;
        $checksum = (string)($evento['signature']['checksum'] ?? '');
        $timestamp = $evento['timestamp'] ?? null;
        if (!is_array($props) || $checksum === '' || $timestamp === null || $eventsSecret === '') {
            return false;
        }
        $cadena = '';
        foreach ($props as $ruta) {
            $valor = $evento['data'] ?? null;
            foreach (explode('.', (string)$ruta) as $seg) {
                if (!is_array($valor) || !array_key_exists($seg, $valor)) {
                    return false;
                }
                $valor = $valor[$seg];
            }
            $cadena .= (string)$valor;
        }
        $esperado = hash('sha256', $cadena . $timestamp . $eventsSecret);
        return hash_equals($esperado, strtolower($checksum));
    }

    /**
     * Procesa un evento YA verificado. Idempotente: un cobro pagado se
     * responde ok sin tocar nada. Devuelve un resumen para el log.
     */
    public static function procesarEvento(PDO $db, array $evento): array
    {
        $tx = $evento['data']['transaction'] ?? [];
        $status = (string)($tx['status'] ?? '');
        $reference = (string)($tx['reference'] ?? '');
        $amountCents = (int)($tx['amount_in_cents'] ?? 0);

        if (!preg_match('/^' . self::REF_PREFIJO . '-(\d+)-\d+$/', $reference, $m)) {
            return ['ok' => true, 'nota' => 'referencia ajena al cobro de plataforma'];
        }
        if ($status !== 'APPROVED') {
            return ['ok' => true, 'nota' => "estado {$status}: sin acción"];
        }
        $raffleId = (int)$m[1];
        $stmt = $db->prepare('SELECT id, name, vendor_id, commission_amount, commission_paid FROM raffles WHERE id = ?');
        $stmt->execute([$raffleId]);
        $raffle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$raffle) {
            return ['ok' => true, 'nota' => "rifa {$raffleId} inexistente"];
        }
        if ((int)$raffle['commission_paid'] === 1) {
            return ['ok' => true, 'nota' => 'ya estaba pagada (idempotente)'];
        }
        $esperado = (int)round(((float)$raffle['commission_amount']) * 100);
        if ($esperado <= 0 || $amountCents !== $esperado) {
            // Monto que no cuadra JAMÁS activa nada — se deja rastro y a revisión manual.
            Logger::warning('Wompi billing: monto no coincide — NO se activa', [
                'raffle_id' => $raffleId, 'esperado' => $esperado, 'recibido' => $amountCents, 'ref' => $reference,
            ]);
            return ['ok' => true, 'nota' => 'monto no coincide: queda para revisión manual'];
        }

        // Exactamente lo que hace el "Marcar pagada" manual (contingencia).
        $db->prepare('UPDATE raffles SET commission_paid = 1, commission_payment_date = NOW(), sales_blocked = 0 WHERE id = ?')
           ->execute([$raffleId]);
        Logger::activity('commission_paid_wompi', (int)$raffle['vendor_id'], [
            'raffle_id' => $raffleId, 'reference' => $reference, 'amount_in_cents' => $amountCents,
            'transaction_id' => $tx['id'] ?? null,
        ]);

        // Confirmación al vendedor por la cola normal (correo; WA si vinculó).
        try {
            require_once __DIR__ . '/../../config/brand.php';
            $v = $db->prepare('SELECT email, phone, business_name FROM vendors WHERE id = ?');
            $v->execute([(int)$raffle['vendor_id']]);
            if ($vend = $v->fetch(PDO::FETCH_ASSOC)) {
                $texto = "✅ Pago recibido: tu cobro de la rifa \"{$raffle['name']}\" quedó al día. "
                    . "Ventas y creación de rifas reactivadas automáticamente. — " . plataforma('nombre');
                $ins = $db->prepare("INSERT INTO message_queue (raffle_id, vendor_id, recipient_phone, recipient_email, channel, message_type, subject, body_text, status, scheduled_at, created_at)
                                     VALUES (?,?,?,?, 'email', 'payment_confirmed', ?, ?, 'pending', NOW(), NOW())");
                $ins->execute([$raffleId, (int)$raffle['vendor_id'], $vend['phone'], $vend['email'],
                    'Pago recibido — rifa ' . $raffle['name'], $texto]);
            }
        } catch (Throwable $e) {
            Logger::error('Wompi billing: no se pudo encolar la confirmación', ['error' => $e->getMessage()]);
        }

        return ['ok' => true, 'nota' => "rifa {$raffleId} pagada y reactivada", 'activado' => true];
    }
}
