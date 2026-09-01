-- v4.19 — Rotación del número emisor de WhatsApp (anti-baneo)
-- Con varias instancias conectadas (misrifas-v1..v5), el emisor rota entre
-- ellas en cada tanda de envíos para no salir siempre del mismo número.
-- Administrable desde WhatsApp IA → Conexión.
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT 'wa_rotacion', '0', 'string', 'Rotar el número emisor de WhatsApp entre las instancias conectadas (anti-baneo)', 'whatsapp'
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'wa_rotacion');
