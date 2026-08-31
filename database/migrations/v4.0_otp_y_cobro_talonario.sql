-- v4.0: Verificación OTP de registro + modalidad de cobro por talonario.
--
-- OTP: los registros NUEVOS deben verificar su cuenta por WhatsApp (OTP
-- inverso: el usuario envía VERIFY-XXXXX al número de la plataforma) o por
-- correo (código). Las cuentas EXISTENTES se marcan verificadas (grandfather)
-- para no bloquear a nadie retroactivamente.
--
-- Cobro: `billing_mode` elige entre comisión porcentual ('commission', el
-- modo histórico) o tarifa plana por talonario creado ('talonario');
-- `commission_enabled` sigue siendo el interruptor maestro.

CREATE TABLE IF NOT EXISTS verification_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('vendor','user') NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('whatsapp','email') NOT NULL,
    code VARCHAR(20) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account (account_type, account_id),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users no tenía columnas de verificación (vendors ya las tenía).
-- (Sin IF NOT EXISTS: MySQL 8 no lo soporta en ADD COLUMN; esta migración
-- corre una sola vez por entorno.)
ALTER TABLE users
    ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN phone_verified_at TIMESTAMP NULL DEFAULT NULL;

-- Grandfather: toda cuenta creada antes del OTP queda verificada.
UPDATE vendors SET email_verified_at = NOW()
 WHERE email_verified_at IS NULL AND phone_verified_at IS NULL;
UPDATE users SET email_verified_at = NOW()
 WHERE email_verified_at IS NULL AND phone_verified_at IS NULL;

-- Settings nuevos (INSERT IGNORE: la API de settings solo actualiza claves
-- existentes). otp_whatsapp_number: número E.164 sin '+' del WhatsApp de la
-- plataforma que recibe los códigos VERIFY (vacío = solo verificación email).
INSERT IGNORE INTO system_settings (setting_key, setting_value, data_type, description, group_name) VALUES
    ('billing_mode', 'commission', 'string', 'Modalidad de cobro: commission (porcentaje) o talonario (tarifa plana por rifa creada)', 'commissions'),
    ('talonario_fee', '0', 'decimal', 'Tarifa plana en COP por talonario creado (modo talonario)', 'commissions'),
    ('otp_whatsapp_number', '', 'string', 'Número WhatsApp de la plataforma (E.164 sin +) que recibe los códigos VERIFY del registro', 'security');
