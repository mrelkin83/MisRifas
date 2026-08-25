-- v3.5: cierra el bypass de confirmacion de pago del bot de WhatsApp
-- (hallazgo C4 de la auditoria de seguridad, 2026-08-25).
--
-- packages/whatsapp-engine/src/Core/ToolEngine.php::crearPedido() y
-- src/Payments/PaymentManager.php resuelven el modo de cobro con
-- `$cfg['pago_modo'] ?? 'contra_entrega'` en varios puntos. `pago_modo` es
-- NULL por defecto (v3.3_whatsapp_engine.sql) y NINGUN flujo de MisRifas lo
-- configura (ni el panel admin, ni ningun otro codigo) - asi que para
-- CUALQUIER vendor que active el bot, el modo real siempre es
-- 'contra_entrega': la herramienta `crear_pedido` (que crea la
-- reserva/transaccion via RaffleDomainAdapter::crearTransaccion) confirma el
-- pago automaticamente sin haber cobrado nada. No hace falta prompt
-- injection, solo pedirle un numero al bot.
--
-- Las rifas no tienen concepto de "pago contra entrega" (no hay domicilio
-- que gatille el cobro): el pago SIEMPRE debe venir de un canal verificado
-- (webhook de pasarela o aprobacion de un admin), nunca del bot. 'manual'
-- es el modo mas seguro por defecto: solo acepta 'transferencia', que exige
-- comprobante y revision humana (mismo criterio que el resto de la app),
-- y nunca activa la rama contra_entrega en ningun punto del motor.

UPDATE `wa_config` SET `pago_modo` = 'manual' WHERE `pago_modo` IS NULL OR `pago_modo` = '';

ALTER TABLE `wa_config`
  MODIFY COLUMN `pago_modo` VARCHAR(50) NOT NULL DEFAULT 'manual';
