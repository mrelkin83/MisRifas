-- v4.7: Codificación de centavos por orden (promt2.md §6).
--
-- Cada orden de compra lleva un sufijo en pesos ("centavos" del monto) único
-- entre las órdenes vigentes de la rifa, para que el vendedor identifique el
-- pago mirando solo el monto: boleta 37 sobre $10.000 → el comprador paga
-- $10.037. El sufijo se guarda por fila de numero_reservas (todas las filas
-- de una misma orden/reservation_id comparten el valor).

ALTER TABLE numero_reservas
  ADD COLUMN payment_suffix SMALLINT UNSIGNED NULL
      COMMENT 'Sufijo del monto (1-999) que identifica la orden — §6'
      AFTER payment_reference,
  ADD KEY idx_suffix_vigente (raffle_id, estado, payment_suffix);
