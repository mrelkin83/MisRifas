-- ============================================================
-- MIGRACION v3.2: Columnas de autenticacion para compradores
-- ============================================================
-- Hallazgo (auditoria Fase 1): la tabla `users` nunca tuvo columnas de
-- autenticacion (ni siquiera en el schema.sql legacy), pero el codigo
-- (api/auth/register.php, login.php, me.php, api/utils/Auth.php) asume
-- que existen desde siempre. El registro/login de compradores nunca
-- pudo haber funcionado contra este schema - fallaba con "Unknown
-- column" en cada intento. Aqui se agregan las columnas que ese codigo
-- ya referencia, sin tocar el flujo de compra como invitado (reserve.php
-- sigue creando filas sin password/token, quedan NULL).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DELIMITER //
DROP PROCEDURE IF EXISTS `add_column_if_not_exists_users`//
CREATE PROCEDURE `add_column_if_not_exists_users`(
    IN p_column_name VARCHAR(100),
    IN p_column_def TEXT
)
BEGIN
    DECLARE col_count INT;
    SET col_count = (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = p_column_name
    );
    IF col_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `users` ADD COLUMN ', p_column_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

CALL add_column_if_not_exists_users('password_hash', '`password_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `email`');
CALL add_column_if_not_exists_users('auth_token', '`auth_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password_hash`');
CALL add_column_if_not_exists_users('auth_token_expires', '`auth_token_expires` TIMESTAMP NULL DEFAULT NULL AFTER `auth_token`');
CALL add_column_if_not_exists_users('role', "`role` VARCHAR(20) NOT NULL DEFAULT 'buyer' AFTER `auth_token_expires`");
CALL add_column_if_not_exists_users('active', '`active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`');
CALL add_column_if_not_exists_users('profile_image', '`profile_image` VARCHAR(500) NULL DEFAULT NULL AFTER `active`');
CALL add_column_if_not_exists_users('last_login', '`last_login` TIMESTAMP NULL DEFAULT NULL AFTER `profile_image`');

-- Indice para el lookup por token que hace Auth.php en cada request autenticado
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_auth_token'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `users` ADD INDEX `idx_auth_token` (`auth_token`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP PROCEDURE IF EXISTS `add_column_if_not_exists_users`;

SET FOREIGN_KEY_CHECKS = 1;
