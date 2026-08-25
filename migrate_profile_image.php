<?php
/**
 * Migration: Add profile_image column to users and admin_users
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if column exists in users
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER role");
        echo "Column 'profile_image' added to users.\n";
    }

    // Check if column exists in admin_users
    $stmt = $db->query("SHOW COLUMNS FROM admin_users LIKE 'profile_image'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE admin_users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER role");
        echo "Column 'profile_image' added to admin_users.\n";
    }

} catch (Exception $e) {
    die("Migration Error: " . $e->getMessage());
}
