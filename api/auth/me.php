<?php
/**
 * API: Get Current User Info
 * GET /api/auth/me.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Método no permitido', null, 405);
}

try {
    // Reusar la unica fuente de verdad de autenticacion: requireLogin ya
    // valida token hasheado + cuenta activa + expiracion, tanto para vendors
    // como para compradores. Antes me.php reimplementaba la consulta y la
    // rama de users NO comprobaba active/expiracion, dejando pasar tokens
    // expirados o de cuentas desactivadas.
    $account = Auth::requireLogin();

    if (($account['auth_type'] ?? '') === 'vendor') {
        $user = [
            'id'            => $account['id'],
            'slug'          => $account['slug'] ?? null,
            'email'         => $account['email'] ?? null,
            'name'          => $account['business_name'] ?? null,
            'full_name'     => $account['business_name'] ?? null,
            'role'          => $account['role'] ?? 'vendor',
            'profile_image' => $account['logo_url'] ?? null,
            'phone'         => $account['phone'] ?? null,
            'city'          => $account['city'] ?? null,
            'department'    => $account['department'] ?? null,
        ];
    } else {
        $user = [
            'id'            => $account['id'],
            'name'          => $account['name'] ?? null,
            'full_name'     => $account['name'] ?? null,
            'email'         => $account['email'] ?? null,
            'role'          => $account['role'] ?? 'buyer',
            'profile_image' => $account['profile_image'] ?? null,
            'phone'         => $account['phone_whatsapp'] ?? null,
            'department'    => $account['department'] ?? null,
            'city'          => $account['city'] ?? null,
        ];
    }

    Response::success($user);

} catch (Exception $e) {
    Response::serverError('Error al obtener usuario');
}
