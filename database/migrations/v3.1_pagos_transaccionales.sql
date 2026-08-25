-- ========================================
-- TABLA: numero_reservas
-- Estados de cada número de rifa
-- ========================================

CREATE TABLE IF NOT EXISTS numero_reservas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    raffle_id BIGINT UNSIGNED NOT NULL,
    numero VARCHAR(10) NOT NULL,
    estado ENUM('DISPONIBLE', 'RESERVADO', 'PAGADO', 'EXPIRADO') NOT NULL DEFAULT 'DISPONIBLE',

    -- Datos de reserva
    user_id BIGINT UNSIGNED NULL,
    reservation_id VARCHAR(50) NULL,
    reserved_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,

    -- Datos de pago
    payment_intent_id BIGINT UNSIGNED NULL,
    payment_gateway ENUM('wompi', 'mercadopago', 'nequi', 'manual') NULL,
    payment_reference VARCHAR(100) NULL,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices únicos (CRÍTICO para evitar duplicados)
    UNIQUE KEY uq_raffle_numero (raffle_id, numero),

    -- Índice para expiración de reservas
    INDEX idx_expiracion (estado, expires_at),

    -- Índice para consultas por usuario
    INDEX idx_user (user_id, raffle_id),

    -- Índice para payment_intent
    INDEX idx_payment (payment_intent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLA: payments
-- Registro de intentos de pago
-- ========================================

CREATE TABLE IF NOT EXISTS payment_intents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    raffle_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    -- Datos del pago
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'COP',
    gateway ENUM('wompi', 'mercadopago', 'nequi', 'manual') NOT NULL,

    -- Referencia al gateway
    gateway_reference VARCHAR(100) NULL,
    gateway_transaction_id VARCHAR(100) NULL,

    -- Estado del payment_intent
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'FAILED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',

    -- Datos de respuesta del gateway
    gateway_response JSON NULL,
    gateway_response_code VARCHAR(50) NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,

    -- Índices
    INDEX idx_status (status),
    INDEX idx_gateway_reference (gateway_reference),
    INDEX idx_user (user_id, raffle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLA: webhook_logs
-- Log de todos los webhooks recibidos
-- ========================================

CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gateway ENUM('wompi', 'mercadopago', 'nequi') NOT NULL,

    -- Datos del webhook
    event_type VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    headers JSON NULL,

    -- Estado de procesamiento
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,

    -- Timestamps
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_processed (processed, received_at),
    INDEX idx_transaction (gateway, transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- VISTAS ÚTILES (opcional pero muy útiles)
-- ========================================

-- Vista: Números disponibles por rifa
CREATE OR REPLACE VIEW v_numeros_disponibles AS
SELECT nr.raffle_id, nr.numero
FROM numero_reservas nr
WHERE nr.estado = 'DISPONIBLE';

-- Vista: Reservas por expirar
CREATE OR REPLACE VIEW v_reservas_expirando AS
SELECT nr.id, nr.raffle_id, nr.numero, nr.user_id, nr.reservation_id, nr.expires_at
FROM numero_reservas nr
WHERE nr.estado = 'RESERVADO' AND nr.expires_at IS NOT NULL;
