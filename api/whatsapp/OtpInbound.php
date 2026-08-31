<?php
/**
 * OTP inverso de registro por WhatsApp.
 *
 * El usuario pulsa el botón "Verificar por WhatsApp" en la pantalla de
 * verificación: se abre su WhatsApp con "VERIFY-XXXXX" prellenado hacia el
 * número de la plataforma. Este handler intercepta ese mensaje entrante,
 * valida el código contra verification_codes y marca la cuenta verificada —
 * comprobando además que el REMITENTE sea el teléfono declarado en la cuenta
 * (anti-suplantación: un tercero con el código no puede verificar por otro).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/brand.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

class OtpInbound
{
    /**
     * Devuelve true si el mensaje era un código OTP (haya validado o no):
     * en ese caso el webhook responde y el mensaje NO sigue al motor de IA.
     */
    public static function procesar(array $mensaje, $canal): bool
    {
        $texto = trim($mensaje['texto'] ?? '');
        if (!preg_match('/VERIFY-([A-Z0-9]{5})/i', $texto, $m)) {
            return false;
        }
        $code = 'VERIFY-' . strtoupper($m[1]);
        $telefono = $mensaje['telefono'] ?? '';

        try {
            $db = Database::getInstance()->getConnection();
            // Expiración evaluada en SQL para no depender del timezone de PHP.
            $stmt = $db->prepare("
                SELECT id, account_type, account_id, (expires_at < NOW()) AS expired
                FROM verification_codes
                WHERE code = ? AND channel = 'whatsapp' AND verified_at IS NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                self::responder($canal, $telefono, '❌ Ese código no es válido o ya fue usado. Vuelve a ' . plataforma('nombre') . ' y pide uno nuevo.');
                return true;
            }
            if (!empty($row['expired'])) {
                self::responder($canal, $telefono, '⌛ Ese código ya venció. Vuelve a ' . plataforma('nombre') . ' y pide uno nuevo.');
                return true;
            }

            // El remitente debe ser el teléfono registrado en la cuenta.
            $table = $row['account_type'] === 'vendor' ? 'vendors' : 'users';
            $phoneCol = $row['account_type'] === 'vendor' ? 'phone' : 'phone_whatsapp';
            $stmt = $db->prepare("SELECT {$phoneCol} AS phone FROM {$table} WHERE id = ?");
            $stmt->execute([$row['account_id']]);
            $accountPhone = (string)$stmt->fetchColumn();

            if (self::ultimos10($accountPhone) === '' || self::ultimos10($accountPhone) !== self::ultimos10($telefono)) {
                Logger::warning('OTP WhatsApp: remitente no coincide con la cuenta', [
                    'code' => $code, 'account' => $row['account_type'] . '#' . $row['account_id'],
                ]);
                self::responder($canal, $telefono, '❌ Este código pertenece a una cuenta registrada con otro número. Verifica desde el teléfono que registraste, o corrígelo en la pantalla de verificación.');
                return true;
            }

            $db->prepare("UPDATE verification_codes SET verified_at = NOW() WHERE id = ?")->execute([$row['id']]);
            $col = 'phone_verified_at';
            $db->prepare("UPDATE {$table} SET {$col} = NOW() WHERE id = ?")->execute([$row['account_id']]);
            Logger::activity('account_verified', (int)$row['account_id'], [
                'type' => $row['account_type'], 'channel' => 'whatsapp',
            ]);

            self::responder($canal, $telefono, '✅ ¡Cuenta verificada! Ya puedes volver a ' . plataforma('nombre') . ' — la página avanzará sola. 🎟️');
            return true;
        } catch (\Throwable $e) {
            Logger::error('OTP inbound error: ' . $e->getMessage());
            // Era un mensaje OTP aunque falló: no tiene sentido pasarlo a la IA.
            return true;
        }
    }

    private static function ultimos10(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private static function responder($canal, string $telefono, string $texto): void
    {
        try {
            if ($canal && $telefono !== '') {
                $canal->enviarTexto($telefono, $texto);
            }
        } catch (\Throwable $e) {
            // La respuesta de cortesía nunca debe tumbar el webhook.
        }
    }
}
