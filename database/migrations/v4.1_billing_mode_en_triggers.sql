-- v4.1: Los triggers de comisión (v3.6) aprenden la modalidad de cobro.
--
-- calculate_commission_before_insert/_before_update recalculaban SIEMPRE con
-- la fórmula porcentual, pisando cualquier valor que fijara PHP — el modo
-- 'talonario' (tarifa plana por rifa creada) no podía funcionar. Ahora los
-- triggers leen billing_mode:
--   talonario  → commission_amount = talonario_fee (plana; no depende de
--                precio ni boletos, y el UPDATE no la recalcula).
--   commission → porcentaje histórico sobre el valor total.

DELIMITER $$

DROP TRIGGER IF EXISTS `calculate_commission_before_insert`$$
CREATE TRIGGER `calculate_commission_before_insert`
BEFORE INSERT ON `raffles`
FOR EACH ROW
BEGIN
    DECLARE v_percentage DECIMAL(5,4) DEFAULT 1;
    DECLARE v_enabled BOOLEAN DEFAULT FALSE;
    DECLARE v_mode VARCHAR(20) DEFAULT 'commission';
    DECLARE v_fee DECIMAL(10,2) DEFAULT 0;

    SELECT CAST(setting_value AS DECIMAL) INTO v_percentage
    FROM system_settings WHERE setting_key = 'commission_percentage' LIMIT 1;

    SELECT CAST(setting_value AS UNSIGNED) INTO v_enabled
    FROM system_settings WHERE setting_key = 'commission_enabled' LIMIT 1;

    SELECT setting_value INTO v_mode
    FROM system_settings WHERE setting_key = 'billing_mode' LIMIT 1;

    SELECT CAST(setting_value AS DECIMAL(10,2)) INTO v_fee
    FROM system_settings WHERE setting_key = 'talonario_fee' LIMIT 1;

    IF v_enabled = TRUE AND v_mode = 'talonario' THEN
        SET NEW.commission_amount = v_fee;
        SET NEW.commission_due_date = DATE_SUB(NEW.draw_date, INTERVAL 8 DAY);
    ELSEIF v_enabled = TRUE AND v_percentage > 0 THEN
        SET NEW.commission_amount = (NEW.ticket_price * NEW.total_tickets) * (v_percentage / 100);
        SET NEW.commission_due_date = DATE_SUB(NEW.draw_date, INTERVAL 8 DAY);
    ELSE
        SET NEW.commission_amount = 0;
        SET NEW.commission_due_date = NULL;
    END IF;
END$$

DROP TRIGGER IF EXISTS `calculate_commission_before_update`$$
CREATE TRIGGER `calculate_commission_before_update`
BEFORE UPDATE ON `raffles`
FOR EACH ROW
BEGIN
    DECLARE v_percentage DECIMAL(5,4) DEFAULT 1;
    DECLARE v_enabled BOOLEAN DEFAULT FALSE;
    DECLARE v_mode VARCHAR(20) DEFAULT 'commission';

    -- La tarifa por talonario es plana: cambiar precio/boletos no la altera.
    -- Solo el modo comisión recalcula ante esos cambios.
    IF NEW.commission_paid = 0
       AND (NEW.ticket_price <> OLD.ticket_price OR NEW.total_tickets <> OLD.total_tickets) THEN

        SELECT setting_value INTO v_mode
        FROM system_settings WHERE setting_key = 'billing_mode' LIMIT 1;

        IF v_mode <> 'talonario' THEN
            SELECT CAST(setting_value AS DECIMAL) INTO v_percentage
            FROM system_settings WHERE setting_key = 'commission_percentage' LIMIT 1;

            SELECT CAST(setting_value AS UNSIGNED) INTO v_enabled
            FROM system_settings WHERE setting_key = 'commission_enabled' LIMIT 1;

            IF v_enabled = TRUE AND v_percentage > 0 THEN
                SET NEW.commission_amount = (NEW.ticket_price * NEW.total_tickets) * (v_percentage / 100);
            ELSE
                SET NEW.commission_amount = 0;
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;
