-- v4.8: El archivo de evidencia (payments) acepta los métodos reales del
-- pago manual (promt2.md §5.2): breb y cash. Se conservan los valores
-- legados (bancolombia/efecty/manual) para no invalidar filas históricas.

ALTER TABLE payments
  MODIFY COLUMN payment_method
      ENUM('nequi','daviplata','breb','cash','bancolombia','efecty','manual')
      NOT NULL;
