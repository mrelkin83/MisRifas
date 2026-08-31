<?php
/**
 * API: Verificar código digitado (canal email, o el mismo VERIFY de WhatsApp)
 * POST /api/auth/otp/verify.php  { code: 'VERIFY-XXXXX' }
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
    $code = strtoupper(trim($input['code'] ?? ''));
    // Tolerar que peguen solo la parte final del código.
    if ($code !== '' && strpos($code, 'VERIFY-') !== 0) {
        $code = 'VERIFY-' . $code;
    }
    if (!preg_match('/^VERIFY-[A-Z0-9]{5}$/', $code)) {
        Response::error('El código no tiene el formato esperado (VERIFY-XXXXX)', null, 422);
    }

    $db = Database::getInstance()->getConnection();
    // La expiración se evalúa en SQL (expires_at < NOW()) para no depender
    // de que el timezone de PHP coincida con el de MySQL.
    $stmt = $db->prepare("
        SELECT id, channel, attempts, (expires_at < NOW()) AS expired
        FROM verification_codes
        WHERE account_type = ? AND account_id = ? AND verified_at IS NULL
        ORDER BY created_at DESC
    ");
    $stmt->execute([$acct['type'], $acct['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar el código exacto entre los vigentes de la cuenta.
    $stmt = $db->prepare("
        SELECT id, channel, attempts, (expires_at < NOW()) AS expired
        FROM verification_codes
        WHERE account_type = ? AND account_id = ? AND code = ? AND verified_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$acct['type'], $acct['id'], $code]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match) {
        // Contar el intento fallido contra los códigos vigentes de la cuenta.
        foreach ($rows as $r) {
            $db->prepare("UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?")->execute([$r['id']]);
            if ((int)$r['attempts'] + 1 >= OTP_MAX_ATTEMPTS) {
                $db->prepare("DELETE FROM verification_codes WHERE id = ?")->execute([$r['id']]);
            }
        }
        Response::error('Código incorrecto. Revisa e intenta de nuevo.', null, 422);
    }
    if (!empty($match['expired'])) {
        Response::error('El código venció. Pide uno nuevo.', 'CODE_EXPIRED', 422);
    }
    if ((int)$match['attempts'] >= OTP_MAX_ATTEMPTS) {
        Response::error('Demasiados intentos con este código. Pide uno nuevo.', null, 429);
    }

    $db->prepare("UPDATE verification_codes SET verified_at = NOW() WHERE id = ?")->execute([$match['id']]);
    otpMarkVerified($acct['type'], $acct['id'], $match['channel']);

    Response::success(['verified' => true], '¡Cuenta verificada!');
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al verificar el código');
}
