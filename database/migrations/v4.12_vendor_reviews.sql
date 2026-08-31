-- v4.12 — Reseñas de COMPRADORES VERIFICADOS al organizador
-- Solo puede reseñar quien tenga una boleta PAGADA de una rifa del
-- organizador (el código de la boleta es la credencial). Una reseña por
-- comprador por rifa (la nueva pisa la anterior). El sistema completo se
-- habilita/deshabilita con el setting reviews_enabled (como las comisiones).
CREATE TABLE IF NOT EXISTS vendor_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    raffle_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user_raffle (user_id, raffle_id),
    KEY idx_vendor_reviews_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, data_type, description, group_name)
SELECT 'reviews_enabled', '1', 'boolean', 'Reseñas de compradores verificados visibles en los perfiles de organizador', 'general'
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'reviews_enabled');
