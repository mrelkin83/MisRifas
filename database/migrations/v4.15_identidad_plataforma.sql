-- v4.15 — Identidad de la plataforma 100% administrable
-- El nombre, correo y WhatsApp de la plataforma pueden CAMBIAR en el
-- despliegue final (dominio y marca actuales son de pruebas). Se siembran
-- las claves que falten para que api/admin/settings.php (que solo
-- actualiza claves existentes) pueda persistirlas desde el panel.
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT k, v, 'string', d, 'general' FROM (
    SELECT 'platform_name' AS k, 'MisRifas' AS v, 'Nombre de la plataforma (títulos, correos, boletas)' AS d
    UNION ALL SELECT 'platform_email', '', 'Correo de contacto/remitente por defecto'
    UNION ALL SELECT 'contact_whatsapp', '', 'WhatsApp de soporte de la plataforma'
) t
WHERE NOT EXISTS (SELECT 1 FROM system_settings s WHERE s.setting_key = t.k);

-- El correo sembrado por el legacy era un supuesto (contacto@misrifas.com,
-- un dominio que ni siquiera es el de pruebas). Si nadie lo ha cambiado,
-- se vacía: vacío = honesto ("sin definir", se deriva no-reply@dominio).
UPDATE system_settings SET setting_value = ''
WHERE setting_key = 'platform_email' AND setting_value = 'contacto@misrifas.com';
