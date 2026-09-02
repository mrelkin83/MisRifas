-- v4.22 — Botones de WhatsApp como opción EXPERIMENTAL (apagada)
-- Baileys (API no oficial) envía botones que los servidores de WhatsApp
-- ACEPTAN pero DESCARTAN en silencio para cuentas normales: el vendedor no
-- recibe nada. Se deja el mecanismo detrás de un interruptor por si algún
-- día se migra a la API oficial de WhatsApp Business (esa sí los soporta).
INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT 'wa_botones', '0', 'string', 'Enviar avisos de pago con botones interactivos (experimental; WhatsApp los descarta en cuentas no-Business)', 'whatsapp'
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'wa_botones');
