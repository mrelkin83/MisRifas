<?php
/**
 * API: Corregir el teléfono antes de verificar ("¿Número equivocado?")
 * POST /api/auth/otp/update_phone.php  { phone: '3001234567' }
 *
 * Solo disponible mientras la cuenta NO está verificada: una vez verificada,
 * el teléfono se cambia por el flujo normal de perfil.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/otp_common.php';
require_once __DIR__ . '/../../utils/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $acct = otpAccount();
    if ($acct['verified']) {
        Response::error('La cuenta ya está verificada; cambia el teléfono desde tu perfil.', null, 409);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));
    if (!preg_match('/^3[0-9]{9}$/', $phone)) {
        Response::error('El teléfono debe ser un celular colombiano válido (10 dígitos, empieza por 3)', null, 422);
    }

    $db = Database::getInstance()->getConnection();

    // El teléfono no puede pertenecer a OTRA cuenta.
    $stmt = $db->prepare("
        SELECT 1 FROM vendors WHERE phone = ? AND NOT (id = ? AND 'vendor' = ?)
        UNION
        SELECT 1 FROM users WHERE phone_whatsapp = ? AND NOT (id = ? AND 'user' = ?)
    ");
    $stmt->execute([$phone, $acct['id'], $acct['type'], $phone, $acct['id'], $acct['type']]);
    if ($stmt->fetch()) {
        Response::error('Ese teléfono ya está registrado en otra cuenta', null, 409);
    }

    if ($acct['type'] === 'vendor') {
        $db->prepare("UPDATE vendors SET phone = ? WHERE id = ?")->execute([$phone, $acct['id']]);
    } else {
        $db->prepare("UPDATE users SET phone_whatsapp = ? WHERE id = ?")->execute([$phone, $acct['id']]);
    }

    // Los códigos WhatsApp emitidos para el número anterior dejan de valer.
    $db->prepare("
        DELETE FROM verification_codes
        WHERE account_type = ? AND account_id = ? AND channel = 'whatsapp' AND verified_at IS NULL
    ")->execute([$acct['type'], $acct['id']]);

    Logger::activity('otp_phone_updated', $acct['id'], ['type' => $acct['type']]);
    Response::success(['phone' => $phone], 'Teléfono actualizado');
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al actualizar el teléfono');
}
