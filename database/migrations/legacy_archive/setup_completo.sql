-- ============================================
-- SETUP COMPLETO - MISRIFAS
-- Base de Datos: MySQL 8.0+
-- Fecha: 2026-04-16
-- ============================================
-- Este script crea toda la base de datos desde cero
-- con todas las correcciones necesarias incluidas
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- ELIMINAR TABLAS EXISTENTES
-- ============================================
DROP TABLE IF EXISTS `raffle_winners`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `commission_payments`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `raffle_images`;
DROP TABLE IF EXISTS `raffles`;
DROP TABLE IF EXISTS `lottery_results`;
DROP TABLE IF EXISTS `lotteries`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `tapazos`;
DROP TABLE IF EXISTS `tapazo_jugadores`;

-- ============================================
-- 1. TABLA: users (Compradores)
-- ============================================
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unique_id` VARCHAR(36) NOT NULL COMMENT 'UUID para consultas sin login',
  `name` VARCHAR(255) NOT NULL,
  `phone_whatsapp` VARCHAR(20) NOT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `department` VARCHAR(100) NULL DEFAULT NULL,
  `city` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  INDEX `idx_phone` (`phone_whatsapp`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. TABLA: admin_users (Creadores/Vendedores)
-- ============================================
CREATE TABLE `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `auth_token` VARCHAR(255) NULL DEFAULT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `document_id` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Documento de identidad',
  `department` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Departamento',
  `city` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Ciudad/Municipio',
  `phone` VARCHAR(20) NULL DEFAULT NULL COMMENT 'WhatsApp verificado',
  `role` ENUM('super_admin', 'admin', 'moderator', 'vendedor') NOT NULL DEFAULT 'admin',
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `wompi_config` JSON NULL DEFAULT NULL,
  `profile_image` VARCHAR(500) NULL DEFAULT NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  INDEX `idx_active` (`active`),
  INDEX `idx_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. TABLA: lotteries (Loterías Colombianas)
-- ============================================
CREATE TABLE `lotteries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `day_of_week` ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
  `draw_time` TIME NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `api_source` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_day_active` (`day_of_week`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TABLA: raffles (Rifas)
-- ============================================
CREATE TABLE `raffles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NULL DEFAULT NULL,
  `scope` ENUM('municipal', 'departamental', 'national') DEFAULT 'municipal',
  `whatsapp_contact` VARCHAR(20) NOT NULL,
  `responsible_person` VARCHAR(255) NOT NULL,
  `ticket_price` DECIMAL(10, 2) NOT NULL,
  `total_tickets` INT UNSIGNED NOT NULL,
  `digits` TINYINT UNSIGNED NOT NULL COMMENT '2, 3 o 4 cifras',
  `draw_date` DATETIME NOT NULL,
  `lottery_id` INT UNSIGNED NOT NULL,
  `opportunities` TINYINT UNSIGNED NOT NULL COMMENT '1, 2, 4 o 5',
  `winning_mode` ENUM('last_2', 'first_2', 'last_3', 'first_3', 'last_4') NOT NULL,
  `status` ENUM('draft', 'active', 'blocked', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
  `commission_paid` BOOLEAN NOT NULL DEFAULT FALSE,
  `commission_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `commission_payment_date` DATETIME NULL DEFAULT NULL,
  `commission_due_date` DATETIME NULL DEFAULT NULL COMMENT '8 días antes del sorteo',
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `shares` INT UNSIGNED NOT NULL DEFAULT 0,
  `draw_rescheduled_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `winning_ticket_number` VARCHAR(10) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`lottery_id`) REFERENCES `lotteries` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_status` (`status`),
  INDEX `idx_draw_date` (`draw_date`),
  INDEX `idx_city` (`city`),
  INDEX `idx_commission_due` (`commission_due_date`, `commission_paid`),
  INDEX `idx_active_raffles` (`status`, `draw_date`),
  FULLTEXT KEY `ft_search` (`name`, `description`, `city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. TABLA: raffle_images (Galería)
-- ============================================
CREATE TABLE `raffle_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`raffle_id`) REFERENCES `raffles` (`id`) ON DELETE CASCADE,
  INDEX `idx_raffle` (`raffle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. TABLA: tickets (Boletos)
-- ============================================
CREATE TABLE `tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `ticket_number` VARCHAR(10) NOT NULL,
  `opportunities` JSON NOT NULL,
  `status` ENUM('available', 'reserved', 'paid') NOT NULL DEFAULT 'available',
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `reserved_at` TIMESTAMP NULL DEFAULT NULL,
  `reserved_until` TIMESTAMP NULL DEFAULT NULL,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `payment_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ticket` (`raffle_id`, `ticket_number`),
  FOREIGN KEY (`raffle_id`) REFERENCES `raffles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_raffle_status` (`raffle_id`, `status`),
  INDEX `idx_reserved_until` (`status`, `reserved_until`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TABLA: payments (Pagos)
-- ============================================
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `payment_method` ENUM('nequi', 'bancolombia', 'daviplata', 'efecty', 'manual') NOT NULL DEFAULT 'nequi',
  `transaction_reference` VARCHAR(100) NOT NULL,
  `transaction_status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `payment_gateway_response` JSON NULL DEFAULT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_reference` (`transaction_reference`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`raffle_id`) REFERENCES `raffles` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE RESTRICT,
  INDEX `idx_status` (`transaction_status`),
  INDEX `idx_raffle` (`raffle_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. TABLA: commission_payments (Comisiones)
-- ============================================
CREATE TABLE `commission_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `payment_reference` VARCHAR(100) NULL DEFAULT NULL,
  `status` ENUM('pending', 'paid', 'overdue', 'waived') NOT NULL DEFAULT 'pending',
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `due_date` DATETIME NOT NULL,
  `payment_proof_url` VARCHAR(500) NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`raffle_id`) REFERENCES `raffles` (`id`) ON DELETE CASCADE,
  INDEX `idx_status_due` (`status`, `due_date`),
  INDEX `idx_raffle` (`raffle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. TABLA: lottery_results (Resultados)
-- ============================================
CREATE TABLE `lottery_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lottery_id` INT UNSIGNED NOT NULL,
  `draw_date` DATE NOT NULL,
  `winning_number` VARCHAR(10) NOT NULL,
  `series` VARCHAR(10) NULL DEFAULT NULL,
  `prize_amount` DECIMAL(15, 2) NULL DEFAULT NULL,
  `fetched_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_url` VARCHAR(500) NULL DEFAULT NULL,
  `verified` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_result` (`lottery_id`, `draw_date`),
  FOREIGN KEY (`lottery_id`) REFERENCES `lotteries` (`id`) ON DELETE RESTRICT,
  INDEX `idx_draw_date` (`draw_date`),
  INDEX `idx_lottery_date` (`lottery_id`, `draw_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. TABLA: raffle_winners (Ganadores)
-- ============================================
CREATE TABLE `raffle_winners` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `winning_number` VARCHAR(10) NOT NULL,
  `matched_opportunity` VARCHAR(10) NOT NULL,
  `prize_description` TEXT NOT NULL,
  `prize_delivered` BOOLEAN NOT NULL DEFAULT FALSE,
  `prize_delivered_at` TIMESTAMP NULL DEFAULT NULL,
  `notified` BOOLEAN NOT NULL DEFAULT FALSE,
  `notified_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`raffle_id`) REFERENCES `raffles` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_raffle` (`raffle_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_notified` (`notified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. TABLA: system_settings (Configuración)
-- ============================================
CREATE TABLE `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `data_type` ENUM('boolean', 'integer', 'decimal', 'string', 'json') NOT NULL DEFAULT 'string',
  `description` VARCHAR(500) NULL DEFAULT NULL,
  `group_name` VARCHAR(50) NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  INDEX `idx_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. TABLA: notifications (Notificaciones)
-- ============================================
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `raffle_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `type` ENUM('reservation', 'payment_confirmed', 'winner', 'reminder', 'commission_due', 'raffle_blocked') NOT NULL,
  `channel` ENUM('whatsapp', 'email', 'sms', 'push') NOT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NULL DEFAULT NULL,
  `message` TEXT NOT NULL,
  `metadata` JSON NULL DEFAULT NULL,
  `status` ENUM('pending', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'pending',
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `error_message` TEXT NULL DEFAULT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`raffle_id`) REFERENCES `raffles` (`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_pending` (`status`, `attempts`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. TABLA: banners (Banners del sistema)
-- ============================================
CREATE TABLE `banners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `subtitle` VARCHAR(500) NULL DEFAULT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `button_text` VARCHAR(100) NULL DEFAULT NULL,
  `button_link` VARCHAR(500) NULL DEFAULT NULL,
  `order_num` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `start_date` DATETIME NULL DEFAULT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_active` (`active`, `order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. TABLA: tapazos (El Tapazo)
-- ============================================
CREATE TABLE `tapazos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NULL,
  `imagen_url` VARCHAR(500) NULL,
  `cantidad_jugadores` INT UNSIGNED NOT NULL,
  `valor_cupo` DECIMAL(10,2) NULL COMMENT 'Valor informativo del cupo',
  `regla` ENUM('alto_gana', 'bajo_gana') NOT NULL DEFAULT 'alto_gana',
  `fecha_hora_destape` DATETIME NOT NULL,
  `estado` ENUM('creado', 'lleno', 'esperando', 'destapando', 'finalizado') NOT NULL DEFAULT 'creado',
  `codigo_unico` VARCHAR(36) NOT NULL COMMENT 'UUID para el link público',
  `ultimo_revelado` VARCHAR(500) NULL COMMENT 'IDs de jugadores revelados separados por coma',
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_unico` (`codigo_unico`),
  INDEX `idx_estado` (`estado`),
  INDEX `idx_fecha` (`fecha_hora_destape`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 15. TABLA: tapazo_jugadores
-- ============================================
CREATE TABLE `tapazo_jugadores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tapazo_id` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `cerveza_numero` INT UNSIGNED NOT NULL COMMENT 'Slot de cerveza elegido (1-N)',
  `numero_tapa` INT UNSIGNED NULL COMMENT 'Número interno revelado al destapar',
  `orden_destape` INT UNSIGNED NULL COMMENT 'Orden en que se destapa',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cerveza` (`tapazo_id`, `cerveza_numero`),
  UNIQUE KEY `unique_nombre` (`tapazo_id`, `nombre`),
  FOREIGN KEY (`tapazo_id`) REFERENCES `tapazos` (`id`) ON DELETE CASCADE,
  INDEX `idx_tapazo` (`tapazo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TRIGGERS Y STORED PROCEDURES
-- ============================================

DELIMITER $$

DROP TRIGGER IF EXISTS `calculate_commission_before_insert`$$
CREATE TRIGGER `calculate_commission_before_insert`
BEFORE INSERT ON `raffles`
FOR EACH ROW
BEGIN
    DECLARE commission_percentage DECIMAL(5,4) DEFAULT 1;
    DECLARE commission_enabled BOOLEAN DEFAULT FALSE;

    SELECT CAST(setting_value AS DECIMAL) INTO commission_percentage
    FROM system_settings
    WHERE setting_key = 'commission_percentage'
    LIMIT 1;

    SELECT CAST(setting_value AS UNSIGNED) INTO commission_enabled
    FROM system_settings
    WHERE setting_key = 'commission_enabled'
    LIMIT 1;

    IF commission_enabled = TRUE AND commission_percentage > 0 THEN
        SET NEW.commission_amount = (NEW.ticket_price * NEW.total_tickets) * (commission_percentage / 100);
        SET NEW.commission_due_date = DATE_SUB(NEW.draw_date, INTERVAL 8 DAY);
    ELSE
        SET NEW.commission_amount = 0;
        SET NEW.commission_due_date = NULL;
    END IF;
END$$

DROP TRIGGER IF EXISTS `update_ticket_paid_at`$$
CREATE TRIGGER `update_ticket_paid_at`
BEFORE UPDATE ON `tickets`
FOR EACH ROW
BEGIN
    IF NEW.status = 'paid' AND OLD.status != 'paid' THEN
        SET NEW.paid_at = CURRENT_TIMESTAMP;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `release_expired_reservations`$$
CREATE PROCEDURE `release_expired_reservations`()
BEGIN
    UPDATE tickets
    SET status = 'available', user_id = NULL, reserved_at = NULL, reserved_until = NULL
    WHERE status = 'reserved' AND reserved_until < NOW();
    SELECT ROW_COUNT() as released_count;
END$$

DROP PROCEDURE IF EXISTS `block_unpaid_commission_raffles`$$
CREATE PROCEDURE `block_unpaid_commission_raffles`()
BEGIN
    UPDATE raffles
    SET status = 'blocked'
    WHERE status = 'active' AND commission_paid = FALSE AND commission_due_date <= NOW();
    SELECT ROW_COUNT() as blocked_count;
END$$

DELIMITER ;

-- ============================================
-- CONFIGURACIÓN INICIAL DEL SISTEMA
-- ============================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `data_type`, `description`, `group_name`) VALUES
('commission_enabled', '0', 'boolean', 'Toggle global de comisión', 'payments'),
('commission_percentage', '1.00', 'decimal', 'Porcentaje de comisión', 'payments'),
('reservation_hours_default', '2', 'integer', 'Horas de reserva por defecto', 'tickets'),
('max_tickets_per_purchase', '10', 'integer', 'Máximo de boletos por compra', 'tickets'),
('min_ticket_price', '1000', 'integer', 'Precio mínimo de boleto', 'validation'),
('max_ticket_price', '1000000', 'integer', 'Precio máximo de boleto', 'validation'),
('platform_name', 'MisRifas', 'string', 'Nombre de la plataforma', 'general'),
('platform_email', 'contacto@misrifas.com', 'string', 'Email de contacto', 'general');

-- ============================================
-- LOTERÍAS COLOMBIANAS
-- ============================================

INSERT INTO `lotteries` (`name`, `day_of_week`, `draw_time`, `active`) VALUES
('Lotería de Cundinamarca', 'monday',    '22:30:00', 1),
('Lotería de Tolima',       'monday',    '23:00:00', 1),
('Lotería Cruz Roja',       'tuesday',   '22:30:00', 1),
('Lotería de Huila',        'tuesday',   '22:30:00', 1),
('Lotería de Manizales',    'wednesday', '22:30:00', 1),
('Lotería del Meta',        'wednesday', '22:30:00', 1),
('Lotería del Valle',       'wednesday', '22:30:00', 1),
('Lotería Quindío',         'thursday',  '22:30:00', 1),
('Lotería de Bogotá',       'thursday',  '22:30:00', 1),
('Lotería de Santander',    'friday',    '23:00:00', 1),
('Lotería de Medellín',     'friday',    '23:00:00', 1),
('Lotería Risaralda',       'friday',    '23:00:00', 1),
('Lotería de Boyacá',       'saturday',  '22:40:00', 1),
('Lotería de Cauca',        'saturday',  '21:40:00', 1),
('Extra de Colombia',       'saturday',  '23:00:00', 1);

-- ============================================
-- USUARIO ADMIN POR DEFECTO
-- Usuario: admin@misrifas.com
-- Contraseña: password123
-- ============================================

INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `role`, `active`) VALUES
('admin', 'admin@misrifas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Principal', 'super_admin', 1);

-- ============================================
-- FINALIZACIÓN
-- ============================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

SELECT '========================================' as '';
SELECT 'BASE DE DATOS CREADA EXITOSAMENTE' as '';
SELECT '========================================' as '';
SELECT 'Tablas creadas:' as '';
SELECT '- users' as '';
SELECT '- admin_users (con campo department)' as '';
SELECT '- lotteries (15 loterías colombianas)' as '';
SELECT '- raffles' as '';
SELECT '- tickets' as '';
SELECT '- payments' as '';
SELECT '- Y 9 tablas más...' as '';
SELECT '' as '';
SELECT 'Usuario admin creado:' as '';
SELECT '  Email: admin@misrifas.com' as '';
SELECT '  Password: password123' as '';
SELECT '' as '';
SELECT 'Puedes probar el registro en:' as '';
SELECT '  http://localhost/MisRifas/public/admin/index.php?auth=register' as '';
SELECT '========================================' as '';
