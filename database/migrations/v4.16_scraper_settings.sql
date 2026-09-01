-- v4.16 — Scraper de resultados administrable desde el panel
-- Interruptor y última corrida (el POST de settings solo actualiza claves
-- existentes, por eso se siembran). El slug por lotería usa la columna
-- lotteries.api_source, que existía desde el esquema inicial y nadie usaba.
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT k, v, 'string', d, 'scraper' FROM (
    SELECT 'scraper_enabled' AS k, '1' AS v, 'Scraper de resultados de loterías encendido (1) o apagado (0)' AS d
    UNION ALL SELECT 'scraper_last_run', '', 'Resumen JSON de la última corrida del scraper (lo escribe el cron)'
) t
WHERE NOT EXISTS (SELECT 1 FROM system_settings s WHERE s.setting_key = t.k);
