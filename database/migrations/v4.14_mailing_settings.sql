-- v4.14 — Claves SMTP en system_settings
-- La tarjeta "Correo del sistema (SMTP)" del panel guardaba vía
-- api/admin/settings.php, que SOLO actualiza claves existentes — y las
-- mailing_* nunca fueron sembradas: el formulario decía "guardado" pero no
-- persistía nada. Se siembran vacías: mientras estén vacías, MailService cae
-- al respaldo del .env (SMTP_HOST…); cuando el admin guarde desde el panel,
-- la BD manda.
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT k, '', 'string', d, 'mailing' FROM (
    SELECT 'mailing_smtp_host' AS k, 'Servidor SMTP (host)' AS d
    UNION ALL SELECT 'mailing_smtp_port', 'Puerto SMTP (465 SSL, 587 STARTTLS, 25/1025 relay sin auth)'
    UNION ALL SELECT 'mailing_smtp_user', 'Usuario SMTP (vacío = relay sin autenticación)'
    UNION ALL SELECT 'mailing_smtp_pass', 'Contraseña SMTP'
    UNION ALL SELECT 'mailing_smtp_from', 'Remitente (From)'
    UNION ALL SELECT 'mailing_from_name', 'Nombre del remitente'
) t
WHERE NOT EXISTS (SELECT 1 FROM system_settings s WHERE s.setting_key = t.k);
