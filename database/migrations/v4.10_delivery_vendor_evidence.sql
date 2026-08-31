-- v4.10 — Evidencia de entrega del VENDEDOR (obligatoria)
-- Al reportar la entrega, el organizador debe adjuntar una foto de evidencia
-- (premio entregado / acta / ganador recibiendo). Es una columna DISTINTA a
-- delivery_photo_path (esa es la foto opcional que sube el GANADOR al
-- confirmar): dos actores, dos evidencias, nunca se pisan.
ALTER TABLE raffle_winners
    ADD COLUMN delivery_vendor_photo_path VARCHAR(255) NULL AFTER delivery_photo_path;
