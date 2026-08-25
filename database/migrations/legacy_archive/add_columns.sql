-- Agregar columnas department y scope a raffles
ALTER TABLE `raffles` ADD COLUMN `department` VARCHAR(100) NULL AFTER `city`;
ALTER TABLE `raffles` ADD COLUMN `scope` ENUM('municipal', 'departamental', 'national') DEFAULT 'municipal' AFTER `department`;
