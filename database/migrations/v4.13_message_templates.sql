-- v4.13 — Editor de plantillas de mensajes
-- Guarda SOLO las plantillas personalizadas (overrides). Si no hay fila, el
-- sistema usa el texto por defecto del código (MessageBuilderService::PLANTILLAS).
-- "Restaurar original" = borrar la fila.
CREATE TABLE IF NOT EXISTS message_templates (
    template_key VARCHAR(50) NOT NULL PRIMARY KEY,
    body_text TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
