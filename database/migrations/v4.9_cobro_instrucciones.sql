-- v4.9: Instrucciones de pago del cobro de la plataforma (promt2.md §15.2:
-- el aviso dice el monto, el concepto, la fecha límite y CÓMO pagar).
-- El registro idempotente de avisos vive en message_queue (type
-- payment_reminder con el umbral en el subject) — no se crea tabla paralela
-- (§15.1). Nota: el spec menciona una tabla whatsapp_notifications que no
-- existe en este esquema; message_queue cumple ese rol aquí.

INSERT IGNORE INTO system_settings (setting_key, setting_value, data_type, description, group_name) VALUES
    ('billing_payment_instructions',
     'Paga por Nequi al número de la plataforma y envía el comprobante al administrador.',
     'string',
     'Cómo paga el vendedor la comisión/tarifa por talonario a la plataforma (aparece en los avisos de cobro)',
     'commissions');
