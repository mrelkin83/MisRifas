-- v4.3: Columnas de estado de conexión que el motor WhatsApp escribe.
--
-- WaConfig::guardar() persiste estado_conexion/ultima_conexion al pedir el
-- QR, consultar estado y desconectar — pero la tabla wa_config portada a
-- MisRifas se creó sin esas columnas: cualquier intento de vincular WhatsApp
-- (módulo admin o autoservicio del vendedor) moría con "Unknown column".
-- Este era uno de los bugs latentes del módulo WhatsApp IA sin depurar.

ALTER TABLE wa_config
    ADD COLUMN estado_conexion VARCHAR(20) NULL DEFAULT NULL
        COMMENT 'desconectado | qr | conectado | error',
    ADD COLUMN ultima_conexion DATETIME NULL DEFAULT NULL;
