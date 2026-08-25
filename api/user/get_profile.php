<?php
/**
 * API: Obtener Perfil del Usuario
 * GET /api/user/get_profile.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';

try {
    $user = Auth::requireLogin();

    // Auth::requireLogin() marca el actor en 'auth_type' ('vendor'|'buyer'),
    // no en 'source' (esa clave nunca existio). 'vendors' y 'users' tienen
    // columnas distintas para nombre/foto - full_name/username/profile_image
    // eran de la vieja tabla admin_users, ninguna existe en 'vendors'.
    $esVendor = ($user['auth_type'] ?? '') === 'vendor';

    // Devolvemos datos limpios
    Response::success([
        'id'            => $user['id'],
        'name'          => $esVendor ? $user['business_name'] : $user['name'],
        'email'         => $user['email'],
        'phone'         => $user['phone'] ?? '',
        'department'    => $user['department'] ?? '',
        'city'          => $user['city'] ?? '',
        'role'          => $user['role'],
        'profile_image' => $esVendor ? ($user['logo_url'] ?? null) : ($user['profile_image'] ?? null),
        'source'        => $user['auth_type'] ?? null
    ]);

} catch (Exception $e) {
    Response::serverError('Error al obtener perfil');
}
