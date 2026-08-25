-- ============================================
-- MÓDULO: EL TAPAZO (Juego de la Tapita Digital)
-- ============================================

DROP TABLE IF EXISTS `tapazo_jugadores`;
DROP TABLE IF EXISTS `tapazos`;

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
