-- v4.11 — Enlace público ENMASCARADO de la rifa
-- Los enlaces compartibles dejan de exponer el id autoincremental
-- (enumerable) y usan un código opaco no adivinable: raffle.php?c=XXXXXXXXXX
-- (?id= sigue funcionando por compatibilidad). El backfill de los códigos lo
-- hace PHP (tools/backfill_public_codes.php) con alfabeto Crockford.
ALTER TABLE raffles
    ADD COLUMN public_code CHAR(10) NULL AFTER id,
    ADD UNIQUE KEY uq_raffles_public_code (public_code);
