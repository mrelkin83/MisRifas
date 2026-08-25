<?php
/**
 * API: Get Current User Info
 * GET /api/auth/me.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';

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
    $db = Database::getInstance()->getConnection();
    
    // Try admin_users
    $stmt = $db->prepare("SELECT id, username, email, full_name, role, profile_image, phone, city, department FROM admin_users WHERE auth_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $user['name'] = $user['full_name'];
        Response::success($user);
    }
    
    // Try users
    $stmt = $db->prepare("SELECT id, name, email, role, profile_image, phone_whatsapp as phone, department, city FROM users WHERE auth_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $user['full_name'] = $user['name'];
        Response::success($user);
    }
    
    Response::error('Token inválido', null, 401);

} catch (Exception $e) {
    Response::serverError('Error al obtener usuario');
}
