<?php

declare(strict_types=1);

/**
 * API: Restablecer contraseña de un usuario (gestor de usuarios)
 * POST /api/admin/users/reset_password.php  { type: 'vendor'|'buyer', id }
 *
 * SOLO super_admin. Genera una contraseña temporal legible, la guarda
 * hasheada y la devuelve UNA sola vez (no se vuelve a mostrar ni se envía
 * por ningún canal: el admin se la entrega al usuario y este debería
 * cambiarla al entrar).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/utils/Response.php';
require_once __DIR__ . '/../../../api/utils/Auth.php';
require_once __DIR__ . '/../../../api/utils/Logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', null, 405);
}

try {
    $admin = Auth::requireAdmin();
    if (($admin['role'] ?? '') !== 'super_admin') {
        Response::error('Solo el super administrador puede restablecer contraseñas', null, 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $type = (string)($input['type'] ?? '');
    $id = (int)($input['id'] ?? 0);
    if (!in_array($type, ['vendor', 'buyer'], true) || $id <= 0) {
        Response::error('Datos inválidos (type vendor|buyer, id)', null, 422);
    }

    // Temporal LEGIBLE para dictar por teléfono: sin caracteres confundibles.
    $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $tmp = 'MR-';
    for ($i = 0; $i < 8; $i++) {
        $tmp .= $abc[random_int(0, strlen($abc) - 1)];
    }
    $hash = password_hash($tmp, PASSWORD_DEFAULT);

    $db = Database::getInstance()->getConnection();
    if ($type === 'vendor') {
        $st = $db->prepare('UPDATE vendors SET password_hash = ?, auth_token = NULL, auth_token_expires = NULL WHERE id = ?');
    } else {
        $st = $db->prepare('UPDATE users SET password_hash = ?, auth_token = NULL, auth_token_expires = NULL WHERE id = ?');
    }
    $st->execute([$hash, $id]);
    if ($st->rowCount() === 0) {
        Response::error('Usuario no encontrado', null, 404);
    }

    Logger::activity('user_password_reset', (int)$admin['id'], ['type' => $type, 'target_id' => $id]);

    Response::success(
        ['password' => $tmp],
        'Contraseña restablecida. Cópiala AHORA: no se volverá a mostrar. La sesión anterior del usuario quedó cerrada.'
    );
} catch (Exception $e) {
    Logger::exception($e);
    Response::serverError('Error al restablecer la contraseña');
}
