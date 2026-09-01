-- v4.21 — Duración administrable de los banners del hero (segundos)
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT 'home_banners_interval', '6', 'string', 'Segundos que dura cada banner del slider de portada', 'general'
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'home_banners_interval');
