-- v4.2: Opt-in de notificaciones automáticas por rifa.
--
-- Al crear la rifa, el organizador decide si el sistema verifica el
-- resultado y notifica a los participantes automáticamente (activado por
-- defecto). Con auto_notify = 0 el sorteo igual se procesa y el ganador se
-- registra, y el ORGANIZADOR sí es notificado — solo se omiten los mensajes
-- a los compradores (el organizador los contacta a su manera).

ALTER TABLE raffles ADD COLUMN auto_notify TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Notificar resultado a los participantes automáticamente (email/WhatsApp)';
