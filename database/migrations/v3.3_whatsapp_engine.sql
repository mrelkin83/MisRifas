-- ============================================================
-- MIGRACION v3.3: Tablas del motor de WhatsApp (whatsapp-engine)
-- ============================================================
-- El paquete packages/whatsapp-engine (Ports & Adapters, ver
-- docs/plan Fase 2) no trae SQL propio - las tablas las crea la app
-- consumidora. Columnas verificadas leyendo el codigo fuente real del
-- paquete (Core/WaConfig.php, ConversationManager.php, HumanHandoff.php,
-- AgentManager.php, AuditLogger.php, Providers/*.php), no inferidas.
--
-- Multi-tenant "una base, una columna": todas llevan vendor_id, que es
-- el valor que devuelve MisRifasTenant::scopeFila(). El motor mismo
-- aplica ese filtro en sus queries via Core\Scope; no hace falta nada
-- mas de nuestra parte para que quede aislado por vendor.
--
-- NO se crea wa_instancias: esa tabla es solo para el modo "una base de
-- datos por negocio" (WaConfig::publicarEnMaster()/resolverPorToken()),
-- que no aplica aqui - MisRifas resuelve el vendor del webhook con su
-- propia logica (ver api/whatsapp/webhook.php, Paso 4).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- wa_config: una fila por vendor. Todo lo listado en
-- WaConfig::SECRETOS se guarda cifrado (MisRifasSecret, AES-256-GCM).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_config` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NOT NULL,

  `activo` TINYINT(1) NOT NULL DEFAULT 0,
  `webhook_token_hash` CHAR(64) NULL DEFAULT NULL,

  `evolution_instancia` VARCHAR(100) NULL DEFAULT NULL,
  `evolution_url` VARCHAR(255) NULL DEFAULT NULL,
  `evolution_apikey` TEXT NULL DEFAULT NULL COMMENT 'cifrado',
  `numero_whatsapp` VARCHAR(20) NULL DEFAULT NULL,
  `handoff_numero` VARCHAR(20) NULL DEFAULT NULL,
  `horario_atencion` JSON NULL DEFAULT NULL,

  `llm_proveedor` VARCHAR(50) NULL DEFAULT NULL,
  `llm_modelo` VARCHAR(100) NULL DEFAULT NULL,
  `llm_api_key` TEXT NULL DEFAULT NULL COMMENT 'cifrado',
  `llm_fallback_proveedor` VARCHAR(50) NULL DEFAULT NULL,
  `llm_fallback_modelo` VARCHAR(100) NULL DEFAULT NULL,
  `llm_fallback_api_key` TEXT NULL DEFAULT NULL COMMENT 'cifrado',
  `llm_max_tokens` INT UNSIGNED NULL DEFAULT NULL,
  `llm_temperatura` DECIMAL(3,2) NULL DEFAULT NULL,

  `stt_proveedor` VARCHAR(50) NULL DEFAULT NULL,
  `stt_url` VARCHAR(255) NULL DEFAULT NULL,
  `stt_modelo` VARCHAR(100) NULL DEFAULT NULL,
  `stt_api_key` TEXT NULL DEFAULT NULL COMMENT 'cifrado',

  `tts_proveedor` VARCHAR(50) NULL DEFAULT NULL,
  `tts_modo` VARCHAR(50) NULL DEFAULT NULL,
  `tts_voice_id` VARCHAR(100) NULL DEFAULT NULL,
  `tts_url` VARCHAR(255) NULL DEFAULT NULL,
  `tts_modelo` VARCHAR(100) NULL DEFAULT NULL,
  `tts_api_key` TEXT NULL DEFAULT NULL COMMENT 'cifrado',

  `vision_proveedor` VARCHAR(50) NULL DEFAULT NULL,
  `vision_url` VARCHAR(255) NULL DEFAULT NULL,
  `vision_modelo` VARCHAR(100) NULL DEFAULT NULL,
  `vision_api_key` TEXT NULL DEFAULT NULL COMMENT 'cifrado',

  `pago_modo` VARCHAR(50) NULL DEFAULT NULL,
  `pago_datos_transferencia` TEXT NULL DEFAULT NULL,
  `pago_expira_minutos` INT UNSIGNED NULL DEFAULT NULL,
  `wompi_public_key` VARCHAR(255) NULL DEFAULT NULL,
  `wompi_private_key` TEXT NULL DEFAULT NULL COMMENT 'cifrado',
  `wompi_events_secret` TEXT NULL DEFAULT NULL COMMENT 'cifrado',
  `wompi_integrity_secret` TEXT NULL DEFAULT NULL COMMENT 'cifrado',
  `wompi_ambiente` VARCHAR(20) NULL DEFAULT NULL,

  `entrega_modos` VARCHAR(100) NULL DEFAULT NULL,
  `costo_domicilio` DECIMAL(10,2) NULL DEFAULT NULL,
  `pedido_minimo` DECIMAL(10,2) NULL DEFAULT NULL,

  `limite_mensajes` INT UNSIGNED NULL DEFAULT NULL,
  `limite_ventana_minutos` INT UNSIGNED NULL DEFAULT NULL,

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wa_config_vendor` (`vendor_id`),
  UNIQUE KEY `uk_wa_config_webhook_token` (`webhook_token_hash`),
  CONSTRAINT `fk_wa_config_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- wa_conversaciones
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_conversaciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NOT NULL,
  `telefono` VARCHAR(50) NOT NULL COMMENT 'digitos o JID @lid completo',
  `nombre_contacto` VARCHAR(255) NULL DEFAULT NULL,
  `estado` ENUM('IA_ACTIVA','HUMANO_ATENDIENDO','IA_PAUSADA','CERRADA') NOT NULL DEFAULT 'IA_ACTIVA',
  `atendida_por` INT UNSIGNED NULL DEFAULT NULL COMMENT 'vendors.id de quien la tomo',
  `cliente_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'users.id si se identifico al comprador',
  `contexto` JSON NULL DEFAULT NULL,
  `ultimo_mensaje_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_wa_conv_vendor_telefono` (`vendor_id`, `telefono`),
  INDEX `idx_wa_conv_estado` (`vendor_id`, `estado`),
  CONSTRAINT `fk_wa_conv_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- wa_mensajes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_mensajes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversacion_id` BIGINT UNSIGNED NOT NULL,
  `message_id_externo` VARCHAR(255) NULL DEFAULT NULL COMMENT 'key.id de Evolution, idempotencia de webhook',
  `direccion` ENUM('entrante','saliente') NOT NULL,
  `tipo` VARCHAR(20) NOT NULL DEFAULT 'texto' COMMENT 'texto|audio|imagen|documento|sistema',
  `contenido` TEXT NULL DEFAULT NULL,
  `media_ruta` VARCHAR(500) NULL DEFAULT NULL,
  `media_mime` VARCHAR(100) NULL DEFAULT NULL,
  `transcripcion` TEXT NULL DEFAULT NULL,
  `tokens_entrada` INT UNSIGNED NOT NULL DEFAULT 0,
  `tokens_salida` INT UNSIGNED NOT NULL DEFAULT 0,
  `proveedor` VARCHAR(50) NULL DEFAULT NULL,
  `modelo` VARCHAR(100) NULL DEFAULT NULL,
  `latencia_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wa_msg_externo` (`message_id_externo`),
  INDEX `idx_wa_msg_conv` (`conversacion_id`, `id`),
  CONSTRAINT `fk_wa_msg_conv` FOREIGN KEY (`conversacion_id`) REFERENCES `wa_conversaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- wa_agentes: persona/tono del bot. Degrada con gracia si falta
-- (AgentManager::activo() cae a un default embebido), pero se crea
-- para que cada vendor pueda personalizarlo desde su panel.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_agentes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(100) NOT NULL DEFAULT 'Asistente',
  `rol` TEXT NULL DEFAULT NULL,
  `objetivo` TEXT NULL DEFAULT NULL,
  `personalidad` TEXT NULL DEFAULT NULL,
  `idioma` VARCHAR(5) NOT NULL DEFAULT 'es',
  `instrucciones` TEXT NULL DEFAULT NULL,
  `herramientas` JSON NULL DEFAULT NULL COMMENT 'null = todas las permitidas',
  `saludo_inicial` TEXT NULL DEFAULT NULL,
  `mensaje_fuera_horario` TEXT NULL DEFAULT NULL,
  `mensaje_error` TEXT NULL DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_wa_agentes_vendor_activo` (`vendor_id`, `activo`),
  CONSTRAINT `fk_wa_agentes_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- wa_eventos: bitacora de auditoria (AuditLogger). Nunca guarda
-- secretos (AuditLogger::sanitizar() los redacta antes de llegar aqui).
-- Degrada con gracia si falta (log() nunca lanza), pero se crea porque
-- es la unica traza de "que hizo el bot" util para soporte/disputas.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wa_eventos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` INT UNSIGNED NOT NULL,
  `conversacion_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `payload` JSON NULL DEFAULT NULL,
  `usuario_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_wa_eventos_vendor` (`vendor_id`, `created_at`),
  INDEX `idx_wa_eventos_conv` (`conversacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
