<?php
/**
 * Migration: Create raffle_images table for multi-photo support
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Ensure raffle_id matches raffles.id type (BIGINT UNSIGNED)
    $sql = "CREATE TABLE IF NOT EXISTS raffle_images (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        raffle_id BIGINT UNSIGNED NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_primary TINYINT(1) DEFAULT 0,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "Table 'raffle_images' created successfully.\n";

} catch (Exception $e) {
    die("Migration Error: " . $e->getMessage());
}
