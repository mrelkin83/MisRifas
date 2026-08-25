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
    
    // Devolvemos datos limpios
    Response::success([
        'id'            => $user['id'],
        'name'          => $user['full_name'] ?? $user['username'],
        'email'         => $user['email'],
        'phone'         => $user['phone'] ?? '',
        'department'    => $user['department'] ?? '',
        'city'          => $user['city'] ?? '',
        'role'          => $user['role'],
        'profile_image' => $user['profile_image'] ?? null,
        'source'        => $user['source']
    ]);

} catch (Exception $e) {
    Response::serverError('Error al obtener perfil');
}
