-- v4.5: Bitácora de transiciones (promt2.md §14) y registro público de
-- sorteos/reprogramaciones (§12).
--
-- ticket_events: toda transición de estado y toda decisión humana, escrita en
-- la MISMA transacción que la transición. Es la fuente para resolver disputas.
--
-- raffle_draws: cada intento de sorteo (ganador, sin ganador, número no
-- vendido) con su desenlace, visible públicamente — la transparencia es lo que
-- hace confiable la reprogramación.

CREATE TABLE IF NOT EXISTS ticket_events (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   BIGINT UNSIGNED NOT NULL,
    raffle_id   BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(20)  NULL,
    to_status   VARCHAR(20)  NOT NULL,
    actor       ENUM('buyer','vendor','system','admin') NOT NULL,
    actor_id    BIGINT UNSIGNED NULL,
    source      ENUM('web','whatsapp','dashboard','cron','admin') NOT NULL,
    reason      VARCHAR(120) NULL,
    detail      JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket (ticket_id, created_at),
    KEY idx_raffle (raffle_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS raffle_draws (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    raffle_id         BIGINT UNSIGNED NOT NULL,
    attempt           TINYINT UNSIGNED NOT NULL,
    lottery_id        INT UNSIGNED    NOT NULL,
    draw_date         DATETIME        NOT NULL,
    winning_number    VARCHAR(10)     NULL,
    ticket_status     VARCHAR(20)     NULL COMMENT 'Estado del ticket al momento del sorteo',
    outcome           ENUM('winner','no_winner','not_sold') NOT NULL,
    rescheduled_to    DATETIME        NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_intento (raffle_id, attempt),
    KEY idx_raffle (raffle_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
