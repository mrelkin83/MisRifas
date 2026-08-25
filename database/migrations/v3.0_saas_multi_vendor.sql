-- ============================================================
-- MisRifas v3.0 — SaaS Multi-Vendor Redesign
-- Migration: Crear vendors, message_queue, modificar raffles
-- ============================================================

-- 1. Crear tabla vendors
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `business_name` VARCHAR(255) NOT NULL,
  `legal_name` VARCHAR(255) NULL,
  `document_type` ENUM('CC', 'NIT', 'CE', 'PP') NULL,
  `document_number` VARCHAR(20) NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `city` VARCHAR(100) NULL,
  `department` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `logo_url` VARCHAR(500) NULL,
  `auth_token` VARCHAR(255) NULL,
  `auth_token_expires` TIMESTAMP NULL,
  `reset_token` VARCHAR(255) NULL,
  `reset_token_expires` TIMESTAMP NULL,
  `role` ENUM('vendor', 'super_admin') NOT NULL DEFAULT 'vendor',
  `status` ENUM('active', 'suspended', 'pending_verification') NOT NULL DEFAULT 'pending_verification',
  `wa_config` JSON NULL,
  `payment_config` JSON NULL COMMENT '{"mode":"manual"} o {"mode":"centralized"}',
  `commission_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_earnings` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `last_login` TIMESTAMP NULL,
  `email_verified_at` TIMESTAMP NULL,
  `phone_verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  UNIQUE KEY `uk_email` (`email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Migrar admin_users existentes a vendors
INSERT INTO `vendors` (`id`, `slug`, `business_name`, `legal_name`, `document_type`, `document_number`, `email`, `password_hash`, `phone`, `city`, `department`, `logo_url`, `auth_token`, `role`, `status`, `wa_config`, `last_login`, `created_at`)
SELECT
  au.id,
  LOWER(REPLACE(REPLACE(REPLACE(COALESCE(au.username, au.email), ' ', '-'), '@', '-'), '.', '-')),
  COALESCE(au.full_name, au.username, 'Vendor'),
  au.full_name,
  NULL,
  au.document_id,
  au.email,
  au.password_hash,
  COALESCE(au.phone, '3000000000'),
  au.city,
  au.department,
  au.profile_image,
  au.auth_token,
  CASE WHEN au.role = 'super_admin' THEN 'super_admin' ELSE 'vendor' END,
  CASE WHEN au.active = 1 THEN 'active' ELSE 'suspended' END,
  au.wompi_config,
  au.last_login,
  au.created_at
FROM admin_users au;

-- 3. Agregar vendor_id a raffles
SET @dbname = DATABASE();
SET @tablename = 'raffles';
SET @columnname = 'vendor_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  'ALTER TABLE raffles ADD COLUMN vendor_id INT UNSIGNED NULL AFTER id, ADD INDEX idx_vendor (vendor_id), ADD CONSTRAINT fk_raffles_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. Migrar datos: copiar created_by a vendor_id
UPDATE raffles SET vendor_id = created_by WHERE vendor_id IS NULL;

-- 5. Agregar columnas de tracking a lottery_results
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'lottery_results' AND COLUMN_NAME = 'scraped_at') > 0,
  'SELECT 1',
  'ALTER TABLE lottery_results ADD COLUMN scraped_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER verified, ADD COLUMN scrape_source VARCHAR(50) NULL DEFAULT ''colombia.com'' AFTER scraped_at, ADD COLUMN scrape_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER scrape_source'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 6. Crear tabla message_queue
CREATE TABLE IF NOT EXISTS `message_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED NOT NULL,
  `recipient_user_id` BIGINT UNSIGNED NULL,
  `recipient_phone` VARCHAR(20) NULL,
  `recipient_email` VARCHAR(255) NULL,
  `channel` ENUM('whatsapp', 'email', 'sms') NOT NULL,
  `message_type` ENUM('reservation', 'payment_confirmed', 'winner', 'no_winner', 'draw_reminder', 'payment_reminder') NOT NULL,
  `subject` VARCHAR(500) NULL,
  `body_text` TEXT NOT NULL,
  `body_html` TEXT NULL,
  `variables` JSON NULL,
  `status` ENUM('pending', 'processing', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'pending',
  `scheduled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT NULL,
  `external_id` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_raffle_status` (`raffle_id`, `status`),
  INDEX `idx_scheduled` (`status`, `scheduled_at`),
  INDEX `idx_vendor` (`vendor_id`),
  INDEX `idx_recipient` (`recipient_user_id`),
  CONSTRAINT `fk_mq_raffle` FOREIGN KEY (`raffle_id`) REFERENCES `raffles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mq_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Agregar auth_token_expires a vendors para los que ya existen
-- (ya incluido en CREATE TABLE)

-- 8. Agregar payment_config default a vendors existentes
UPDATE vendors SET payment_config = '{"mode":"manual"}' WHERE payment_config IS NULL;

-- 9. Verificar integridad
SELECT 'vendors' as tbl, COUNT(*) as rows_count FROM vendors
UNION ALL
SELECT 'raffles', COUNT(*) FROM raffles
UNION ALL
SELECT 'message_queue', COUNT(*) FROM message_queue
UNION ALL
SELECT 'lottery_results', COUNT(*) FROM lottery_results;
