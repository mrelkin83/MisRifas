-- Tabla: tapazos (rifas rápidas sin costo - El Tapazo)
CREATE TABLE IF NOT EXISTS `tapazos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `prize` VARCHAR(500) NULL,
  `total_participants` INT UNSIGNED NOT NULL,
  `win_mode` ENUM('highest', 'lowest') NOT NULL DEFAULT 'highest',
  `whatsapp` VARCHAR(20) NULL,
  `status` ENUM('draft', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tapazo_participants (participantes del tapazo)
CREATE TABLE IF NOT EXISTS `tapazo_participants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tapazo_id` INT UNSIGNED NOT NULL,
  `participant_name` VARCHAR(255) NOT NULL,
  `participant_phone` VARCHAR(20) NULL,
  `cap_number` INT UNSIGNED NOT NULL COMMENT 'Número de la tapa asignado',
  `status` ENUM('pending', 'confirmed', 'revealed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cap` (`tapazo_id`, `cap_number`),
  FOREIGN KEY (`tapazo_id`) REFERENCES `tapazos` (`id`) ON DELETE CASCADE,
  INDEX `idx_tapazo` (`tapazo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
