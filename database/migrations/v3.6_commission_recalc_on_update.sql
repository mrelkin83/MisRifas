-- v3.6: cierra el bypass de comision por edicion de precio despues de crear
-- (hallazgo M1 de la auditoria de seguridad, 2026-08-25).
--
-- `calculate_commission_before_insert` solo corre en el INSERT. Cualquier
-- vendor (Auth::requireAdmin() no chequea rol, solo ownership via
-- created_by) puede llamar POST /api/admin/raffles/update.php despues y
-- subir ticket_price/total_tickets libremente sin que commission_amount se
-- vuelva a calcular - crear al precio minimo, luego subir el precio real,
-- deja la comision fija en el monto original (chico).
--
-- Mismo criterio que el trigger de INSERT, en un BEFORE UPDATE: si el
-- precio o el total de boletos cambia Y la comision todavia no se pago,
-- se recalcula con el mismo porcentaje. Si ya se pago, no se toca (no
-- tiene sentido mover retroactivamente algo que el vendor ya liquido).

DELIMITER $$

DROP TRIGGER IF EXISTS `calculate_commission_before_update`$$
CREATE TRIGGER `calculate_commission_before_update`
BEFORE UPDATE ON `raffles`
FOR EACH ROW
BEGIN
    DECLARE commission_percentage DECIMAL(5,4) DEFAULT 1;
    DECLARE commission_enabled BOOLEAN DEFAULT FALSE;

    IF NEW.commission_paid = 0
       AND (NEW.ticket_price <> OLD.ticket_price OR NEW.total_tickets <> OLD.total_tickets) THEN

        SELECT CAST(setting_value AS DECIMAL) INTO commission_percentage
        FROM system_settings
        WHERE setting_key = 'commission_percentage'
        LIMIT 1;

        SELECT CAST(setting_value AS UNSIGNED) INTO commission_enabled
        FROM system_settings
        WHERE setting_key = 'commission_enabled'
        LIMIT 1;

        IF commission_enabled = TRUE AND commission_percentage > 0 THEN
            SET NEW.commission_amount = (NEW.ticket_price * NEW.total_tickets) * (commission_percentage / 100);
        ELSE
            SET NEW.commission_amount = 0;
        END IF;
    END IF;
END$$

DELIMITER ;
