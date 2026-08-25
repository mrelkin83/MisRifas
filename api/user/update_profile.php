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
    
    // 1. Manejar carga de imagen si existe
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $profileImagePath = Uploader::upload($_FILES['profile_image'], 'assets/uploads/profiles', 'profile');
        
        // Elimar foto anterior
        if (!empty($user['profile_image'])) {
            Uploader::delete($user['profile_image']);
        }
    }

    // 2. Determinar tabla y campos segun el origen (admin_user o user)
    if ($user['source'] === 'admin_user') {
        $sql = "UPDATE admin_users SET full_name = ?, phone = ?, city = ?, updated_at = NOW()";
        $params = [$name, $phone, $city];
        if ($profileImagePath) { $sql .= ", profile_image = ?"; $params[] = $profileImagePath; }
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
        'profile_image' => $profileImagePath ?: $user['profile_image']
    ]);

} catch (Exception $e) {
    Response::serverError('Error al actualizar perfil: ' . $e->getMessage());
}
