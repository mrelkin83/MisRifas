<?php

declare(strict_types=1);

/**
 * Confirmación de pagos por WhatsApp (promt2.md §10.1).
 *
 * El vendedor responde al aviso de "nuevo pago por confirmar" con:
 *   SI <ticket_id>                  → confirmar
 *   NO <ticket_id> <motivo 1-5>     → rechazar (motivo obligatorio §10.2)
 * (también CONFIRMAR/RECHAZAR). Cualquier otro texto no es asunto de este
 * interceptor y sigue su flujo normal.
 *
 * Guardas:
 *  - Solo el celular registrado del vendedor dueño de la instancia puede
 *    confirmar (un tercero que escriba al número del negocio no confirma nada).
 *  - Idempotencia: se invoca DESPUÉS del dedupe por message_id del webhook
 *    (guardarMensaje devuelve 0 en reintentos de Evolution) y, además, la
 *    transición es idempotente por estado: un segundo intento encuentra el
 *    ticket fuera de pending_review y solo explica el estado actual.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Logger.php';
require_once __DIR__ . '/../../api/services/PaymentReview.php';

class PaymentInbound
{
    /** Mapa de motivos por número para responder rápido desde el teléfono. */
    private const MOTIVOS_NUM = ['1' => 'no_llego', '2' => 'monto', '3' => 'ilegible', '4' => 'repetido', '5' => 'otro'];

    /** true si el mensaje era un comando de pago (se atendió y no sigue a la IA). */
    public static function procesar(array $mensaje, $canal, int $vendorId): bool
    {
        $texto = trim((string)($mensaje['texto'] ?? ''));
        if (!preg_match('/^(SI|NO|CONFIRMAR|RECHAZAR)\s+(\d{1,10})(?:\s+(\S+))?$/iu', $texto, $m)) {
            return false;
        }
        $accion = strtoupper($m[1]);
        $aprobar = in_array($accion, ['SI', 'CONFIRMAR'], true);
        $ticketId = (int)$m[2];
        $motivoRaw = strtolower($m[3] ?? '');
        $telefono = (string)($mensaje['telefono'] ?? '');

        try {
            $db = Database::getInstance()->getConnection();

            // El remitente debe ser el celular del VENDEDOR dueño de la instancia.
            $stmt = $db->prepare('SELECT phone FROM vendors WHERE id = ?');
            $stmt->execute([$vendorId]);
            $vendorPhone = (string)$stmt->fetchColumn();
            if (self::u10($vendorPhone) === '' || self::u10($vendorPhone) !== self::u10($telefono)) {
                Logger::warning('PaymentInbound: remitente no es el vendedor', ['vendor' => $vendorId]);
                self::responder($canal, $telefono, '❌ Solo el celular registrado del organizador puede confirmar pagos.');
                return true;
            }

            if ($aprobar) {
                $r = PaymentReview::aprobar($db, $ticketId, $vendorId, 'whatsapp');
                if ($r['ok']) {
                    // Boleta al comprador — después del commit del servicio.
                    require_once __DIR__ . '/../../api/services/Boleta.php';
                    Boleta::enviarPorWhatsApp($db, $ticketId, $vendorId);
                    self::responder($canal, $telefono, '✅ ' . $r['mensaje']);
                } else {
                    self::responder($canal, $telefono, 'ℹ️ ' . $r['mensaje']);
                }
                return true;
            }

            // Rechazo: motivo obligatorio (número 1-5 o clave).
            $motivo = self::MOTIVOS_NUM[$motivoRaw] ?? (array_key_exists($motivoRaw, PaymentReview::REJECT_REASONS) ? $motivoRaw : '');
            if ($motivo === '') {
                $lista = '';
                $i = 1;
                foreach (PaymentReview::REJECT_REASONS as $label) {
                    $lista .= $i . '. ' . $label . "\n";
                    $i++;
                }
                self::responder($canal, $telefono,
                    "Para rechazar indica el motivo. Responde:\n*NO {$ticketId} <número>*\n\n" . $lista);
                return true;
            }
            $r = PaymentReview::rechazar($db, $ticketId, $vendorId, 'whatsapp', $motivo);
            self::responder($canal, $telefono, ($r['ok'] ? '❌ ' : 'ℹ️ ') . $r['mensaje']);
            return true;
        } catch (\Throwable $e) {
            Logger::error('PaymentInbound error: ' . $e->getMessage(), ['ticket' => $ticketId]);
            self::responder($canal, $telefono, '⚠️ No pude procesar el comando. Confírmalo desde tu panel → Pagos por confirmar.');
            return true;
        }
    }

    private static function u10(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string)$phone);
        return strlen($d) > 10 ? substr($d, -10) : $d;
    }

    private static function responder($canal, string $telefono, string $texto): void
    {
        try {
            if ($canal && $telefono !== '') {
                $canal->enviarTexto($telefono, $texto);
            }
        } catch (\Throwable $e) {
            // La respuesta de cortesía nunca tumba el webhook.
        }
    }
}
