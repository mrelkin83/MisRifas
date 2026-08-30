-- v3.9: Backfill de vendor_id en rifas legado.
--
-- Las rifas creadas antes de la unificación de cuentas quedaron con
-- vendor_id = NULL: la propiedad vivía solo en created_by. Los endpoints
-- de scoping del vendedor (api/vendor/list_raffles.php) filtran por
-- vendor_id, así que esas rifas eran invisibles para su dueño.
-- Ambos caminos de creación actuales ya escriben vendor_id = created_by;
-- esta migración alinea las filas históricas con esa convención.

UPDATE raffles SET vendor_id = created_by WHERE vendor_id IS NULL;
