-- v4.6: Confirmación de entrega del premio por DOS actores (promt2.md §13).
--
-- El vendedor declara que entregó (delivery_reported) y el GANADOR confirma
-- con un token distinto al de aceptación (delivery_confirmed). Solo la
-- confirmación del ganador se muestra en verde en el hall. La disputa nunca
-- se oculta.

ALTER TABLE raffle_winners
  ADD COLUMN delivery_status ENUM('pending','delivery_reported',
                                  'delivery_confirmed','disputed')
      NOT NULL DEFAULT 'pending',
  ADD COLUMN delivery_reported_at   DATETIME     NULL AFTER delivery_status,
  ADD COLUMN delivery_token         VARCHAR(64)  NULL AFTER delivery_reported_at,
  ADD COLUMN delivery_confirmed_at  DATETIME     NULL AFTER delivery_token,
  ADD COLUMN delivery_confirmed_ip  VARCHAR(45)  NULL AFTER delivery_confirmed_at,
  ADD COLUMN delivery_photo_path    VARCHAR(255) NULL AFTER delivery_confirmed_ip,
  ADD COLUMN dispute_reason         TEXT         NULL AFTER delivery_photo_path,
  ADD UNIQUE KEY uniq_delivery_token (delivery_token);
