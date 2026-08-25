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
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        Response::error('No hay token', null, 401);
    }
    
    $token = $matches[1];
    $hashedToken = Auth::hashToken($token);
    $db = Database::getInstance()->getConnection();

    // Try vendors (misma tabla y mismas condiciones de status/expiracion
    // que Auth::requireVendor(), para que una suspension sea efectiva aqui
    // tambien - antes se consultaba admin_users, una tabla espejo sin
    // relacion garantizada de IDs con vendors)
    $stmt = $db->prepare("
        SELECT id, slug, email, business_name, role, logo_url, phone, city, department
        FROM vendors
        WHERE auth_token = ? AND status = 'active'
        AND (auth_token_expires IS NULL OR auth_token_expires > NOW())
    ");
    $stmt->execute([$hashedToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user['name'] = $user['business_name'];
        $user['full_name'] = $user['business_name'];
        $user['profile_image'] = $user['logo_url'];
        Response::success($user);
    }
    
    // Try users
    $stmt = $db->prepare("SELECT id, name, email, role, profile_image, phone_whatsapp as phone, department, city FROM users WHERE auth_token = ?");
    $stmt->execute([$hashedToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $user['full_name'] = $user['name'];
        Response::success($user);
    }
    
    Response::error('Token inválido', null, 401);

} catch (Exception $e) {
    Response::serverError('Error al obtener usuario');
}
