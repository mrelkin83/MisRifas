<?php
/**
 * API: Actualizar Perfil del Usuario
 * POST /api/user/update_profile.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../api/utils/Auth.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Uploader.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $user = Auth::requireLogin();
    
    // Al usar multipart/form-data, los datos llegan por $_POST
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $dept     = trim($_POST['department'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($phone)) {
        Response::error('Nombre y teléfono son obligatorios');
    }

    $db = Database::getInstance()->getConnection();

    // Auth::requireLogin() marca el actor en 'auth_type' ('vendor'|'buyer'),
    // NUNCA en 'source' - ese chequeo era siempre falso, asi que TODO
    // caller (vendor o buyer) actualizaba la fila de `users` con su propio
    // id numerico. Como `vendors` y `users` tienen secuencias autoincrement
    // independientes, un vendor con id=N sobreescribia silenciosamente al
    // comprador con users.id=N (incluyendo su password_hash si mandaba uno).
    $esVendor = ($user['auth_type'] ?? '') === 'vendor';
    $oldImageField = $esVendor ? ($user['logo_url'] ?? null) : ($user['profile_image'] ?? null);

    // 1. Manejar carga de imagen si existe
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $profileImagePath = Uploader::upload($_FILES['profile_image'], 'assets/uploads/profiles', 'profile');

        // Elimar foto anterior
        if (!empty($oldImageField)) {
            Uploader::delete($oldImageField);
        }
    }

    // 2. Determinar tabla y campos segun el actor autenticado
    if ($esVendor) {
        $sql = "UPDATE vendors SET business_name = ?, phone = ?, city = ?, department = ?, updated_at = NOW()";
        $params = [$name, $phone, $city, $dept];
        if ($profileImagePath) { $sql .= ", logo_url = ?"; $params[] = $profileImagePath; }
        if (!empty($password)) { $sql .= ", password_hash = ?"; $params[] = password_hash($password, PASSWORD_DEFAULT); }
        $sql .= " WHERE id = ?";
        $params[] = $user['id'];
    } else {
        $sql = "UPDATE users SET name = ?, phone_whatsapp = ?, department = ?, city = ?, updated_at = NOW()";
        $params = [$name, $phone, $dept, $city];
        if ($profileImagePath) { $sql .= ", profile_image = ?"; $params[] = $profileImagePath; }
        if (!empty($password)) { $sql .= ", password_hash = ?"; $params[] = password_hash($password, PASSWORD_DEFAULT); }
        $sql .= " WHERE id = ?";
        $params[] = $user['id'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    Response::success([
        'message' => 'Perfil actualizado con éxito',
        'profile_image' => $profileImagePath ?: $oldImageField
    ]);

} catch (Exception $e) {
    Response::serverError('Error al actualizar perfil: ' . $e->getMessage());
}
