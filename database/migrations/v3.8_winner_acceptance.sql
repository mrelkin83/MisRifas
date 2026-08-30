-- v3.8 — Aceptación del premio por el ganador (transparencia del sorteo).
-- El ganador confirma la aceptación mediante un enlace tokenizado (sin login);
-- queda registrado con fecha e IP y se muestra públicamente en /ganadores.
ALTER TABLE raffle_winners
  ADD COLUMN acceptance_status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending' AFTER prize_delivered_at,
  ADD COLUMN acceptance_token  VARCHAR(64) NULL AFTER acceptance_status,
  ADD COLUMN accepted_at       TIMESTAMP NULL AFTER acceptance_token,
  ADD COLUMN acceptance_ip     VARCHAR(45) NULL AFTER accepted_at,
  ADD UNIQUE KEY uniq_acceptance_token (acceptance_token);
