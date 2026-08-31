<?php
/**
 * Verificación OTP de registro — helpers compartidos.
 *
 * Dos canales:
 *  - whatsapp (OTP inverso): el usuario ENVÍA el código VERIFY-XXXXX al
 *    número de la plataforma (setting otp_whatsapp_number); el webhook lo
 *    intercepta y marca la cuenta verificada. No requiere que el usuario
 *    reciba nada — solo un toque en el enlace wa.me con el texto prellenado.
 *  - email: código enviado por el motor SMTP; el usuario lo digita.
 *
 * Una cuenta está verificada si tiene email_verified_at O phone_verified_at.
 * Las cuentas previas a la migración v4.0 quedaron verificadas (grandfather).
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';

const OTP_TTL_MINUTES   = 10;
const OTP_MAX_PER_HOUR  = 4;   // códigos generados por cuenta+canal por hora
const OTP_MAX_ATTEMPTS  = 5;   // intentos de digitar un mismo código

/** Cuenta autenticada normalizada: tipo, id, nombre, email, teléfono, verificada. */
function otpAccount(): array
{
    $account = Auth::requireLogin();
    $type = (($account['auth_type'] ?? '') === 'vendor') ? 'vendor' : 'user';
    return [
        'type'     => $type,
        'id'       => (int)$account['id'],
        'name'     => $type === 'vendor' ? ($account['business_name'] ?? '') : ($account['name'] ?? ''),
        'email'    => $account['email'] ?? '',
        'phone'    => $type === 'vendor' ? ($account['phone'] ?? '') : ($account['phone_whatsapp'] ?? ''),
        'verified' => !empty($account['email_verified_at']) || !empty($account['phone_verified_at']),
    ];
}

/** Código legible: VERIFY- + 5 caracteres sin confusables (0/O, 1/I/L). */
function otpGenerateCode(): string
{
    $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    $out = '';
    for ($i = 0; $i < 5; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'VERIFY-' . $out;
}

/** Crea (rotando el anterior) un código para la cuenta+canal. Aplica rate limit. */
function otpCreateCode(array $acct, string $channel): string
{
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM verification_codes
        WHERE account_type = ? AND account_id = ? AND channel = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$acct['type'], $acct['id'], $channel]);
    if ((int)$stmt->fetchColumn() >= OTP_MAX_PER_HOUR) {
        Response::error('Demasiados intentos. Espera un momento antes de pedir otro código.', null, 429);
    }

    // Un solo código vigente por cuenta+canal: se invalidan los anteriores.
    $db->prepare("
        DELETE FROM verification_codes
        WHERE account_type = ? AND account_id = ? AND channel = ? AND verified_at IS NULL
    ")->execute([$acct['type'], $acct['id'], $channel]);

    $code = otpGenerateCode();
    $db->prepare("
        INSERT INTO verification_codes (account_type, account_id, channel, code, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL " . OTP_TTL_MINUTES . " MINUTE))
    ")->execute([$acct['type'], $acct['id'], $channel, $code]);

    return $code;
}

/** Marca la cuenta como verificada por el canal dado. */
function otpMarkVerified(string $type, int $id, string $channel): void
{
    $db = Database::getInstance()->getConnection();
    $table = $type === 'vendor' ? 'vendors' : 'users';
    $column = $channel === 'whatsapp' ? 'phone_verified_at' : 'email_verified_at';
    $db->prepare("UPDATE {$table} SET {$column} = NOW() WHERE id = ?")->execute([$id]);
    Logger::activity('account_verified', $id, ['type' => $type, 'channel' => $channel]);
}

/** Número WhatsApp de la plataforma para el OTP inverso ('' si no hay). */
function otpPlatformWhatsApp(): string
{
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'otp_whatsapp_number'");
    $stmt->execute();
    return trim((string)$stmt->fetchColumn());
}

/** Últimos 10 dígitos (línea colombiana) para comparar teléfonos con formatos distintos. */
function otpNormalizePhone(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone);
    return strlen($digits) > 10 ? substr($digits, -10) : $digits;
}
