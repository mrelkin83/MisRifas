-- ============================================
-- MIGRACION v2.1: Correccion de Schema
-- Proposito: Sincronizar el schema de BD con el codigo PHP
-- Fecha: 2026-04-28
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-05:00";

-- Helper procedure to add column safely (checks if column exists first)
DELIMITER //
DROP PROCEDURE IF EXISTS `add_column_if_not_exists`//
CREATE PROCEDURE `add_column_if_not_exists`(
    IN p_table_name VARCHAR(100),
    IN p_column_name VARCHAR(100),
    IN p_column_def TEXT
)
BEGIN
    DECLARE col_count INT;
    SET col_count = (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    );
    IF col_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- 1. Agregar 'deleted' al ENUM de raffles.status
ALTER TABLE `raffles` MODIFY COLUMN `status` ENUM('draft', 'active', 'blocked', 'completed', 'cancelled', 'deleted') NOT NULL DEFAULT 'draft';

-- 2. Columnas faltantes en raffles (usadas por CommissionRepository)
CALL add_column_if_not_exists('raffles', 'blocked_reason', '`blocked_reason` VARCHAR(500) NULL DEFAULT NULL AFTER `commission_due_date`');
CALL add_column_if_not_exists('raffles', 'blocked_at', '`blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `blocked_reason`');

-- 3. Columna is_primary en raffle_images (usada por RaffleRepository)
CALL add_column_if_not_exists('raffle_images', 'is_primary', '`is_primary` BOOLEAN NOT NULL DEFAULT FALSE AFTER `image_url`');

-- 4. Columna alias reference en payments (codigo usa 'reference', schema tiene 'transaction_reference')
CALL add_column_if_not_exists('payments', 'reference', '`reference` VARCHAR(100) NULL DEFAULT NULL AFTER `transaction_reference`');
UPDATE `payments` SET `reference` = `transaction_reference` WHERE `reference` IS NULL;

-- 5. Columna alias reference en commission_payments (codigo usa 'reference', schema tiene 'payment_reference')
CALL add_column_if_not_exists('commission_payments', 'reference', '`reference` VARCHAR(100) NULL DEFAULT NULL AFTER `payment_reference`');
UPDATE `commission_payments` SET `reference` = `payment_reference` WHERE `reference` IS NULL;

-- 6. Columna alias confirmed_at en commission_payments (codigo usa 'confirmed_at', schema tiene 'paid_at')
CALL add_column_if_not_exists('commission_payments', 'confirmed_at', '`confirmed_at` TIMESTAMP NULL DEFAULT NULL AFTER `paid_at`');

-- 7. Tabla audit_log (referenciada por CommissionRepository)
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `changes` JSON NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_table_record` (`table_name`, `record_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla password_resets (referenciada por recover.php)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_email` (`email`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cleanup helper procedure
DROP PROCEDURE IF EXISTS `add_column_if_not_exists`;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
