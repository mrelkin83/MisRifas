-- v4.4: Núcleo del modelo de pago manual (promt2.md §5, §7, §8, §9).
--
-- - tickets.status gana 'pending_review' (comprobante subido, vendedor no ha
--   decidido) y 'held' (apartado por el vendedor — el "fiado").
-- - payment_method: cómo pagó el comprador (nequi/daviplata/breb/cash).
-- - Columnas held_*: quién apartó y para quién (nombre+celular obligatorios).
-- - Boleta digital: ticket_code (Crockford Base32, no predecible), issued_at
--   y datos del comprador para la boleta.
-- - raffles.cutoff_at: cierre de apartados (default draw_date - 2 días);
--   sales_blocked: bloqueo de ventas nuevas por mora del vendedor (§15.3).
-- - Settings: TTL de reserva configurable (45 min) y modo de reprogramación
--   ('auto' conserva el comportamiento histórico; 'manual' = §12 con
--   pending_reschedule; las guardas aplican a ambos).

ALTER TABLE tickets
  MODIFY COLUMN status ENUM('available','reserved','pending_review','held','paid')
      NOT NULL DEFAULT 'available',
  ADD COLUMN payment_method ENUM('nequi','daviplata','breb','cash') NULL
      AFTER payment_id,
  ADD COLUMN ticket_code   CHAR(12)  NULL COMMENT 'Código público de la boleta'
      AFTER payment_method,
  ADD COLUMN issued_at     DATETIME  NULL AFTER ticket_code,
  ADD COLUMN buyer_name    VARCHAR(120) NULL AFTER issued_at,
  ADD COLUMN buyer_phone   VARCHAR(20)  NULL AFTER buyer_name,
  ADD COLUMN held_by_vendor_id INT UNSIGNED NULL AFTER buyer_phone,
  ADD COLUMN holder_name       VARCHAR(120)  NULL AFTER held_by_vendor_id,
  ADD COLUMN holder_phone      VARCHAR(20)   NULL AFTER holder_name,
  ADD COLUMN held_at           DATETIME      NULL AFTER holder_phone,
  ADD COLUMN held_note         VARCHAR(255)  NULL AFTER held_at,
  ADD UNIQUE KEY uk_ticket_code (ticket_code),
  ADD KEY idx_held (raffle_id, status, held_at);

ALTER TABLE raffles
  ADD COLUMN cutoff_at DATETIME NULL
      COMMENT 'Cierre de apartados. Default: draw_date - 2 días'
      AFTER draw_date,
  ADD COLUMN sales_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER cutoff_at,
  MODIFY COLUMN status ENUM('draft','active','blocked','pending_reschedule',
                            'completed','cancelled','deleted')
      NOT NULL DEFAULT 'draft';

-- Backfill de cutoff_at para rifas vigentes.
UPDATE raffles
   SET cutoff_at = DATE_SUB(draw_date, INTERVAL 2 DAY)
 WHERE cutoff_at IS NULL AND draw_date IS NOT NULL;

INSERT IGNORE INTO system_settings (setting_key, setting_value, data_type, description, group_name) VALUES
    ('reservation_ttl_minutes', '45', 'integer', 'Minutos que dura una reserva (reserved) sin comprobante', 'tickets'),
    ('pending_review_ttl_hours', '12', 'integer', 'Horas que espera un comprobante (pending_review) sin decisión del vendedor', 'tickets'),
    ('reschedule_mode', 'auto', 'string', 'Reprogramación tras sorteo sin ganador: auto (el sistema reagenda) o manual (el vendedor decide en pending_reschedule). Las guardas aplican a ambos.', 'raffles');
