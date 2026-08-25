# Diagrama de Flujo del Estado del Número (SaaS Multi-Vendedor)

## Estados Obligatorios
```
DISPONIBLE → RESERVADO → PAGADO → EXPIRADO
```

## Transiciones Permitidas

### 1. DISPONIBLE → RESERVADO
- **Quién lo hace:** Usuario selecciona números y clic en "Pagar"
- **Archivo:** `api/payments/create-reservation.php`
- **Cuándo:** Al iniciar el flujo de pago
- **Condiciones:**
  - Número debe estar en estado DISPONIBLE
  - Usuario debe estar autenticado
  - Rifa debe estar activa
- **Datos que se guardan:**
  - `estado = 'RESERVADO'`
  - `user_id` (comprador)
  - `reservation_id` (UUID único)
  - `reserved_at = NOW()`
  - `expires_at = NOW() + 10 minutos`
  - `payment_intent_id` (referencia al pago)
- **Transaccional:** Sí, dentro de transacción atómica

### 2. RESERVADO → PAGADO
- **Quién lo hace:** Webhook del gateway (Wompi o Mercado Pago)
- **Archivos:**
  - `api/payments/webhooks/wompi.php`
  - `api/payments/webhooks/mercadopago.php`
- **Cuándo:** Cuando el gateway notifica que el pago fue aprobado
- **Condiciones:**
  - `payment_intent.status = 'PENDING'`
  - Webhook recibe evento `APPROVED`/`AUTHORIZED`
  - Se valida firma del webhook (CRÍTICO)
- **Datos que se guardan:**
  - `estado = 'PAGADO'`
  - `payment_intent.status = 'APPROVED'`
  - `payment_intent.gateway_transaction_id` (ID del gateway)
  - `payment_intent.processed_at = NOW()`
  - Se crea registro en `tickets`
- **Transaccional:** Sí, dentro de transacción atómica
- **Acciones posteriores:**
  - Enviar WhatsApp automático con resumen del ticket
  - Enviar email con ticket PDF
  - Generar ticket PDF

### 3. RESERVADO → DISPONIBLE
- **Quién lo hace:** Puede ser OR (exclusivo, no ambos)
- **Archivos:**
  - `cron/expire-reservations.php` (expiración por tiempo)
  - `api/payments/webhooks/wompi.php` (pago rechazado)
  - `api/payments/webhooks/mercadopago.php` (pago rechazado)
- **Cuándo:**
  - **Por expiración:** `expires_at < NOW()` (ejecuta cada minuto)
  - **Por rechazo:** Webhook recibe `REJECTED`/`FAILED`
- **Condiciones:**
  - `estado = 'RESERVADO'`
  - `expires_at < NOW()` (expiración por tiempo) O
  - Webhook `REJECTED` (expiración por rechazo)
- **Datos que se guardan:**
  - `estado = 'DISPONIBLE'`
  - `user_id = NULL` (limpiar asignación)
  - `reservation_id = NULL`
  - `reserved_at = NULL`
  - `expires_at = NULL`
  - `payment_intent_id = NULL`
  - `payment_intent.status = 'CANCELLED'` (si es por rechazo)
- **Transaccional:** Sí, dentro de transacción atómica

### 4. PAGADO → EXPIRADO
- **Estado final** - No se usa en el flujo normal. Solo referencia histórica.
- **Cuándo:** Cuando la rifa termina y el sorteo se realiza
- **Notas:** Los números PAGADOS permanecen en ese estado para auditoría

## Transiciones PROHIBIDAS

### NUNCA PERMITIDO:
- ❌ DISPONIBLE → PAGADO (debe pasar por RESERVADO)
- ❌ PAGADO → DISPONIBLE (una vez pagado, el ticket es permanente)
- ❌ PAGADO → RESERVADO (no se puede despagar)
- ❌ EXPIRADO → PAGADO (no se puede revivir)

## Casos de Prueba Resistidos

### Caso 1: Usuario paga y cierra navegador
- **Lo que pasa:**
  1. Números pasan a RESERVADO
  2. Webhook llega (incluso con navegador cerrado)
  3. Webhook cambia estado a PAGADO
  4. Ticket se genera automáticamente
  5. Usuario ve su ticket cuando vuelve a la aplicación
- **Cómo resiste:** El webhook funciona independientemente del navegador

### Caso 2: Usuario paga doble clic
- **Lo que pasa:**
  1. Primer clic: números → RESERVADO
  2. Segundo clic: Ya están RESERVADO → Error "números no disponibles"
  3. Webhook procesa solo la primera reserva
- **Cómo resiste:** `UNIQUE KEY uq_raffle_numero (raffle_id, numero)` evita duplicados

### Caso 3: Usuario no paga y vence la reserva
- **Lo que pasa:**
  1. Números → RESERVADO con `expires_at = NOW() + 10 min`
  2. Cron corre cada minuto: `expire-reservations.php`
  3. Encuentra `expires_at < NOW()`
  4. Números → DISPONIBLE
  5. Otro usuario puede comprarlos
- **Cómo resiste:** Cron de expiración + timestamp `expires_at`

### Caso 4: Webhook llega tarde
- **Lo que pasa:**
  1. Reserva expiró (números → DISPONIBLE)
  2. Webhook llega tarde (ej: 20 min después)
  3. Webhook busca `payment_intent` con status `PENDING`
  4. Payment_intent ya está en `CANCELLED` o no existe
  5. Webhook logga y devuelve 200 (no hace nada)
- **Cómo resiste:**
  - Webhook verifica `payment_intent.status === 'PENDING'` antes de procesar
  - Si ya fue procesado, no hace nada (idempotencia)
  - Números ya están DISPONIBLE, el usuario ya perdió su chance

### Caso 5: Webhook duplicado
- **Lo que pasa:**
  1. Webhook llega por primera vez
  2. Registra en `webhook_logs` con `processed = false`
  3. Cambia estados de números a PAGADO
  4. Marca `webhook_logs.processed = true`
  5. Webhook llega por segunda vez (gateway reenvía)
  6. Busca `webhook_logs` con `processed = true`
  7. Detecta duplicado, no hace nada, retorna 200
- **Cómo resiste:**
  - Tabla `webhook_logs` rastrea cada webhook
  - Verificación `processed === true` antes de cambiar estados
  - Webhook devuelve 200 para que gateway no reenvíe

## Cronología del Sistema

```
Tiempo     Evento
--------   ----------------------------------------------------
T = 0      Usuario selecciona números, clic "Pagar"
           → create-reservation.php
           → Números: DISPONIBLE → RESERVADO
           → Payment_intent creado (PENDING)

T = 10 min Cron: expire-reservations.php
           → Números RESERVADO → DISPONIBLE
           → Payment_intent: PENDING → CANCELLED

T = 20 seg Gateway notifica (webhook)
           → /webhooks/wompi.php
           → Verifica firma
           → Payment_intent: PENDING → APPROVED
           → Números: RESERVADO → PAGADO
           → Tickets creados

T = 25 seg Post-webhook automático
           → WhatsApp enviado al comprador
           → Email con ticket PDF enviado
```

## Aislamiento por Raffle ID

- **Todas las consultas usan `raffle_id`**: Sí
- **`numero_reservas` tiene `raffle_id`**: Sí
- **`payment_intents` tiene `raffle_id`**: Sí
- **`tickets` tiene `raffle_id`**: Sí
- **`webhook_logs` NO tiene `raffle_id`**: Solo para auditoría global

## El Dinero Manda

- Solo el webhook confirma pagos
- El frontend NUNCA confirma pagos
- El usuario NUNCA confirma pagos
- El vendedor NUNCA confirma pagos
- **SOLO** el webhook del gateway (Wompi/Mercado Pago/Nequi) confirma

## Donde Va Cada Archivo en el Proyecto

### Tablas (SQL)
- **Archivo:** `C:\xampp\htdocs\MisRifas\database\migrations\v3.1_pagos_transaccionales.sql`
- **Contenido:** CREATE TABLE de `numero_reservas`, `payment_intents`, `webhook_logs`

### API de Pagos
- **Archivo:** `C:\xampp\htdocs\MisRifas\api\payments\create-reservation.php`
- **URL:** POST `/api/payments/create-reservation.php`
- **Qué hace:** Reserva números, crea payment_intent, devuelve datos del gateway

### Webhooks
- **Archivo:** `C:\xampp\htdocs\MisRifas\api\payments\webhooks\wompi.php`
  - **URL:** POST `/api/payments/webhooks/wompi.php`
  - **Qué hace:** Procesa notificaciones de Wompi, cambia RESERVADO→PAGADO o RESERVADO→DISPONIBLE

- **Archivo:** `C:\xampp\htdocs\MisRifas\api\payments\webhooks\mercadopago.php`
  - **URL:** POST `/api/payments/webhooks/mercadopago.php`
  - **Qué hace:** Procesa notificaciones de Mercado Pago, cambia RESERVADO→PAGADO o RESERVADO→DISPONIBLE

### Cron Jobs
- **Archivo:** `C:\xampp\htdocs\MisRifas\cron\expire-reservations.php`
- **Frecuencia:** Cada minuto: `* * * * *`
  - **Qué hace:** Pasa números RESERVADO con expires_at vencido → DISPONIBLE

### Archivos Modificados (Existentes)
- **Ninguno** - Todo es nuevo código para pagos transaccionales

## Seguridad Crítica

1. **Validación de firma del webhook:**
   - Wompi: `hash_hmac('sha256', $raw, $secret)`
   - Mercado Pago: `hash_hmac('sha256', $raw, $secret)`
   - Si firma inválida → HTTP 401

2. **Idempotencia del webhook:**
   - Verifica `payment_intent.status === 'PENDING'` antes de procesar
   - Si ya fue procesado → No hace nada, retorna 200
   - Marca `webhook_logs.processed = true` después de procesar

3. **Transaccionalidad:**
   - `create-reservation.php`: Transacción al reservar números + crear payment_intent
   - `wompi.php` y `mercadopago.php`: Transacción al cambiar estados + crear tickets
   - Si falla → `ROLLBACK`, se mantiene estado anterior

4. **Exclusividad de números:**
   - `UNIQUE KEY uq_raffle_numero (raffle_id, numero)` en `numero_reservas`
   - Impide: Un número no puede estar en dos estados a la vez

## Qué Le Falta (Pendiente)

- [ ] Integración real con APIs de Wompi y Mercado Pago (solo skeleton ahora)
- [ ] Generación de ticket PDF
- [ ] Envío automático de WhatsApp al aprobar pago
- [ ] Envío automático de email con ticket PDF

## Notas de Arquitectura

Este diseño cumple:
1. ✅ Estados obligatorios: DISPONIBLE, RESERVADO, PAGADO, EXPIRADO
2. ✅ Transaccionalidad en todas las operaciones de estado
3. ✅ Idempotencia de webhooks
4. ✅ Validación de firma de webhooks
5. ✅ Aislamiento por raffle_id en todo excepto webhook_logs
6. ✅ Solo el webhook confirma pagos (frontends/usuarios/vendedores NO pueden confirmar)
7. ✅ Reservas expiran automáticamente a los 10 minutos
8. ✅ Casos de prueba resistidos: navegador cerrado, doble clic, expiración, webhook tarde, webhook duplicado
