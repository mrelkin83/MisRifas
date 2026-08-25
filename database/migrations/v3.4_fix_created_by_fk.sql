-- v3.4: corrige raffles.created_by para que apunte a la tabla que realmente
-- lo puebla.
--
-- Bug (encontrado en auditoria de seguridad, 2026-08-25 - hallazgo C3): la
-- FK original (000_setup_completo_legacy.sql) apunta a admin_users(id), pero
-- desde que existe el modelo SaaS multi-vendor (v3.0), Auth::requireVendor()
-- autentica contra `vendors` y api/raffles/create.php / api/vendor/create_raffle.php
-- insertan ahi el vendors.id del vendor autenticado. admin_users solo tiene
-- la fila sembrada original (id=1), asi que crear una rifa como cualquier
-- otro vendor tiraba "ERROR 1452: foreign key constraint fails" siempre
-- (confirmado con INSERT real contra la BD reconstruida en Fase 0).
--
-- Fix: repuntar la FK a vendors(id), que es la tabla que el codigo realmente
-- usa. `vendor_id` (columna separada, agregada en v3.0) sigue siendo la
-- columna de scoping preferida para queries nuevas; created_by se conserva
-- por compatibilidad con el codigo existente que ya lo lee/escribe.

ALTER TABLE `raffles`
  DROP FOREIGN KEY `raffles_ibfk_2`;

ALTER TABLE `raffles`
  ADD CONSTRAINT `fk_raffles_created_by`
  FOREIGN KEY (`created_by`) REFERENCES `vendors` (`id`) ON DELETE RESTRICT;
