-- v4.18 — Llaves Wompi DE LA PLATAFORMA (cobro de comisiones/talonario)
-- Distintas de las wompi_* por vendedor (vendors.payment_config): estas
-- cobran PARA la plataforma. Administrables en Configuración → Comisiones;
-- se siembran vacías porque el guardado solo actualiza claves existentes.
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT k, '', 'string', d, 'commissions' FROM (
    SELECT 'wompi_platform_public_key' AS k, 'Llave pública Wompi de la plataforma (pub_test_/pub_prod_)' AS d
    UNION ALL SELECT 'wompi_platform_integrity_secret', 'Secreto de integridad Wompi (firma los links de pago)'
    UNION ALL SELECT 'wompi_platform_events_secret', 'Secreto de eventos Wompi (verifica la firma del webhook)'
    UNION ALL SELECT 'wompi_platform_private_key', 'Llave privada Wompi (consultas de conciliación; opcional)'
) t
WHERE NOT EXISTS (SELECT 1 FROM system_settings s WHERE s.setting_key = t.k);
