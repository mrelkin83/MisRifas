-- v4.20 — Correo ADICIONAL de avisos por vendedor
-- Los avisos del organizador (nuevo pago, recordatorios) llegan a su correo
-- de cuenta Y a este adicional si lo configura (ej: cuenta corporativa +
-- gmail personal). Editable en Mi Perfil.
-- (sin IF NOT EXISTS: MySQL local no lo soporta; correr una sola vez)
ALTER TABLE vendors ADD COLUMN notification_email VARCHAR(255) NULL DEFAULT NULL AFTER email;
