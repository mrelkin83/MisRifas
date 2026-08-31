<?php
/**
 * API: Iniciar verificación OTP
 * POST /api/auth/otp/start.php  { channel: 'whatsapp' | 'email' }
 *
 * whatsapp → devuelve wa_link (wa.me con VERIFY-XXXXX prellenado hacia el
 *            número de la plataforma). El usuario solo pulsa "enviar".
 * email    → envía el código al correo de la cuenta por el motor SMTP.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/otp_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $acct = otpAccount();

    if ($acct['verified']) {
        Response::success(['verified' => true], 'La cuenta ya está verificada');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $channel = ($input['channel'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';

    if ($channel === 'whatsapp') {
        $platformNumber = otpPlatformWhatsApp();
        if ($platformNumber === '') {
            Response::error('La verificación por WhatsApp no está disponible por ahora. Usa el correo.', 'CHANNEL_UNAVAILABLE', 409);
        }
        if (otpNormalizePhone($acct['phone']) === '') {
            Response::error('Tu cuenta no tiene un teléfono registrado.', null, 409);
        }
        $code = otpCreateCode($acct, 'whatsapp');
        Response::success([
            'verified' => false,
            'channel'  => 'whatsapp',
            'code'     => $code,
            'wa_link'  => 'https://wa.me/' . rawurlencode($platformNumber) . '?text=' . rawurlencode($code),
            'expires_in_minutes' => OTP_TTL_MINUTES,
        ]);
    }

    // email
    if (!filter_var($acct['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Tu cuenta no tiene un correo válido registrado.', null, 409);
    }
    $code = otpCreateCode($acct, 'email');

    $sent = false;
    try {
        require_once __DIR__ . '/../../services/MailService.php';
        require_once __DIR__ . '/../../../config/brand.php';
        $marca = htmlspecialchars(plataforma('nombre'), ENT_QUOTES, 'UTF-8');
        $nombre = htmlspecialchars($acct['name'] ?: 'Hola', ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="margin:0;padding:0;background:#0f172a;font-family:sans-serif;">'
            . '<div style="max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#2563eb;padding:24px;text-align:center;"><h1 style="color:#fff;margin:0;font-size:24px;">' . $marca . '</h1></div>'
            . '<div style="padding:32px;color:#94a3b8;line-height:1.6;">'
            . '<h2 style="color:#f1f5f9;margin:0 0 16px;">¡Ya casi, ' . $nombre . '!</h2>'
            . '<p>Para activar tu cuenta y proteger la comunidad de perfiles falsos, usa este código de verificación:</p>'
            . '<p style="text-align:center;margin:24px 0;"><span style="display:inline-block;background:#0f172a;color:#fbbf24;font-size:28px;font-weight:800;letter-spacing:2px;padding:14px 28px;border-radius:12px;">' . $code . '</span></p>'
            . '<p>El código vence en ' . OTP_TTL_MINUTES . ' minutos. Si no creaste esta cuenta, ignora este mensaje.</p>'
            . '</div>'
            . '<div style="padding:16px;text-align:center;color:#64748b;font-size:12px;">' . $marca . ' - Rifas digitales</div>'
            . '</div></body></html>';
        $text = "Tu codigo de verificacion de " . plataforma('nombre') . " es: {$code}\nVence en " . OTP_TTL_MINUTES . " minutos.";
        $mail = new MailService();
        $sent = (bool)$mail->sendDirect($acct['email'], 'Tu código de verificación — ' . plataforma('nombre'), $html, $text);
    } catch (Exception $e) {
        Logger::error('OTP email send failed', ['error' => $e->getMessage()]);
    }

    Response::success([
        'verified' => false,
        'channel'  => 'email',
        'email_sent' => $sent,
        'email_masked' => preg_replace('/(?<=.).(?=[^@]*.@)/', '•', $acct['email']),
        'expires_in_minutes' => OTP_TTL_MINUTES,
    ]);
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al iniciar la verificación');
}
