# Mis Rifas — Instrucciones del proyecto

Eres el ingeniero principal de **Mis Rifas**, plataforma que permite a personas
naturales en Colombia vender talonarios de rifas por internet.

Este documento manda sobre cualquier suposición. Cuando una tarea lo contradiga,
**detente y pregunta** en vez de asumir.

---

## 1. Contexto del negocio

Un **vendedor** crea una rifa con un talonario de N números y comparte el enlace
público. Los **compradores** eligen número y pagan **directamente al vendedor** por
Nequi, DaviPlata, Bre-B o en efectivo. El vendedor confirma que la plata llegó y el
número queda bloqueado.

El número ganador **no lo sortea la plataforma**: sale del resultado oficial de una
lotería colombiana (`lotteries`, `lottery_results`, `LotteryScraperService`), cruzado con
`raffles.winning_mode` (últimas 2 cifras, primeras 3, etc.).

La plataforma no cobra hoy; a futuro cobrará comisión porcentual o tarifa plana por
talonario (`system_settings.billing_mode`). Los vendedores son personas de bajos
ingresos, sin cuenta empresarial ni capacidad de pagar una pasarela.

---

## 2. Invariantes no negociables

### 2.1 La plataforma nunca toca el dinero del comprador

El dinero va siempre del comprador al vendedor, directo. La plataforma no recibe, no
custodia, no dispersa y no retiene fondos de las rifas.

Recibir plata en una cuenta central para repartirla convierte al proyecto en agregador
de pagos, actividad vigilada por la Superintendencia Financiera. **Nunca propongas ni
implementes esa arquitectura.**

(El cobro de la comisión de la plataforma al vendedor es otra cosa y sí es legítimo —
ver sección 14.)

### 2.2 No existe verificación automática del pago

No hay API bancaria, ni webhook, ni conciliación automática.

- **No** integres Wompi, ePayco, Bold, PayU, MercadoPago ni Stripe.
- **No** consumas endpoints no documentados de Nequi, Bancolombia o DaviPlata.
- **No** intentes decodificar el QR del comprobante de Nequi. Es un token efímero de 5
  minutos que solo la app oficial resuelve contra su backend; localmente no contiene
  nada verificable.
- **No** uses OCR como fuente de verdad. Sirve para prellenar un campo que un humano
  confirma, nada más.

**La única fuente de verdad es la confirmación explícita del vendedor.**

### 2.3 Un ticket pagado es irreversible desde el código

`tickets.status = 'paid'` no se revierte por expiración, cron ni reintento. Solo un
endpoint administrativo autenticado y registrado en bitácora puede deshacerlo.

### 2.4 Un ticket no pagado no gana

Si el número que sale en la lotería corresponde a un ticket que no está en `paid`, ese
ticket **no** es ganador. La rifa se reprograma (sección 12). Esta regla no tiene
excepciones y es la que protege a quienes sí pagaron.

### 2.5 El dinero se representa en centavos enteros

Los montos nuevos son `BIGINT` de centavos. Nunca `FLOAT` ni `DOUBLE`. El esquema
heredado usa `DECIMAL` en `raffles.ticket_price`, `vendors.commission_balance` y
`payments`; respétalo donde ya existe, pero no introduzcas columnas nuevas de dinero en
punto flotante.

---

## 3. Stack técnico

| Capa | Tecnología | Restricción |
|---|---|---|
| Lenguaje | PHP 8.2+ | `declare(strict_types=1);` en archivos nuevos |
| Base de datos | MySQL 8, InnoDB | `utf8mb4` |
| Acceso a datos | PDO + repositorios en `api/repositories/` | Sentencias preparadas siempre |
| CSS | TailwindCSS | Sin componentes de terceros |
| JavaScript | Vanilla | Sin React, Vue, Alpine, jQuery |
| Mensajería | `packages/whatsapp-engine` | Ver sección 10 |

**No instales dependencias sin autorización previa.** Si crees que una librería es
necesaria, explica qué problema resuelve y espera aprobación antes de tocar
`composer.json`.

### Convención de idioma

El esquema existente está en inglés (`vendors`, `raffles`, `tickets`, `status`,
`raffle_winners`). **Mantén esa convención.** No introduzcas tablas ni columnas en
español; no renombres lo existente.

---

## 4. Limpieza: eliminar integraciones de pasarela

Tarea previa a cualquier funcionalidad nueva.

### 4.1 Panel del vendedor (`public/vendor/index.php`)

Elimina:

- El bloque "Credenciales API Nequi (Automático)" (≈ líneas 1076–1091) y su
  `nequi-config-form`.
- El bloque "Configuración Wompi" (≈ líneas 1616–1631) y su `wompi-config-form`.
- Los manejadores JS asociados: submit de `wompi-config-form` (≈ 3079), submit de
  `nequi-config-form` (≈ 3319), y las precargas de `wompi_public_key` (≈ 2771) y
  `nequi_key` / `nequi_secret` / `nequi_phone` (≈ 3222).

Renombra el ítem de menú "Mi Perfil (Integraciones)" a "Mi Perfil". La sección se
conserva: allí van ahora las llaves de cobro (sección 5).

Los números de línea son orientativos — el archivo tiene ~3.900 líneas y cualquier
edición previa los corre. **Verifica el contexto antes de cortar.**

### 4.2 Endpoints de pasarela

```
api/payments/webhooks/wompi.php
api/payments/webhooks/mercadopago.php
api/payments/webhook_nequi.php
api/payments/webhook.php
api/payments/create-link.php
api/payments/check-status.php
public/pago-procesando.php
```

Antes de borrar cada archivo busca sus referencias
(`grep -rn "create-link\|pago-procesando" --include="*.php" --include="*.js" .`) y limpia
los llamados. **Reporta qué encontraste antes de eliminar.**

Limpia también el manejador de tipo `nequi` en `api/admin/profile_api.php`.

### 4.3 NO tocar el motor de WhatsApp

`packages/whatsapp-engine/src/Payments/WompiAdapter.php` y `PaymentManager.php`
pertenecen al motor reutilizable que se usa en otros proyectos. **No los elimines ni los
modifiques.** Mis Rifas simplemente no los instancia. Lo mismo aplica a
`vendor/elkinlinan/whatsapp-ai-engine/`.

### 4.4 Base de datos

Deja `payment_intents` y `payment_gateway` en su sitio por compatibilidad histórica,
pero ninguna ruta nueva los escribe. No borres tablas heredadas sin autorización.

---

## 5. Métodos de pago

### 5.1 Llaves de cobro del vendedor

El vendedor configura dónde recibe la plata. Guarda en `vendors.payment_config` (JSON ya
existente):

```json
{
  "mode": "manual",
  "nequi_phone": "3001234567",
  "daviplata_phone": "3001234567",
  "breb_key": "@elkinrifas",
  "accepts_cash": true
}
```

Reglas:

- Todos los campos son opcionales, pero el vendedor **debe** tener al menos uno
  configurado para publicar una rifa. Valida al pasar la rifa a `active`.
- Al comprador solo se le muestran los métodos que el vendedor configuró. Si no tiene
  DaviPlata, esa opción no aparece.
- La llave Bre-B se guarda tal como el vendedor la escribe. No inventes validación de
  formato: puede ser celular, cédula, correo o alfanumérica.

### 5.2 Métodos disponibles

```sql
ALTER TABLE tickets
  ADD COLUMN payment_method ENUM('nequi','daviplata','breb','cash') NULL
      AFTER payment_id;
```

- `nequi`, `daviplata`, `breb`: el comprador transfiere y sube comprobante.
- `cash`: efectivo. **Solo lo registra el vendedor** desde su panel, nunca el comprador
  desde el enlace público. Va directo a `paid` sin comprobante, pero **exige** nombre y
  celular del comprador y queda registrado con el vendedor como actor. Sin esos datos no
  hay boleta emitible ni trazabilidad de disputa.

### 5.3 Pantalla de pago del comprador

Cuando el comprador selecciona un número, la vista muestra:

1. El número y el monto exacto **con los centavos** (sección 6).
2. Los métodos disponibles del vendedor, como tarjetas seleccionables.
3. Al elegir uno, el dato de destino en grande con botón de copiar: celular de Nequi,
   celular de DaviPlata o llave Bre-B.
4. Instrucción clara: transferir el monto exacto y volver a subir el comprobante.
5. El contador de la reserva.

No muestres las tres llaves a la vez. Una selección, un dato, un botón de copiar. El
usuario típico está en celular y con prisa.

---

## 6. Codificación de centavos

Cada orden lleva un sufijo de centavos único entre las órdenes vigentes de la rifa, para
que el vendedor identifique el pago mirando solo el monto.

- Talonario de hasta 100 números, compra de un solo número: el sufijo es el número de la
  boleta. Boleta 37 sobre $10.000 → el comprador paga **$10.037**.
- Varios números, o talonario de más de 100: sufijo aleatorio en `[1, 999]`, verificado
  contra las órdenes vigentes de esa rifa.

`monto = raffles.ticket_price * cantidad + sufijo`

Muestra siempre el monto completo: `$10.037`. Nunca lo redondees ni digas
"aproximadamente $10.000". No aplica a `cash`.

---

## 7. Estados del ticket

### 7.1 Enum

```sql
ALTER TABLE tickets
  MODIFY COLUMN status ENUM('available','reserved','pending_review','held','paid')
      NOT NULL DEFAULT 'available';
```

| Estado | Significado |
|---|---|
| `available` | Libre |
| `reserved` | El comprador lo apartó y está yendo a pagar (TTL corto) |
| `pending_review` | Subió comprobante, el vendedor no ha decidido |
| `held` | **Apartado por el vendedor**, sin pagar (sección 8) |
| `paid` | Confirmado. Bloqueado, irreversible, y con boleta emitida |

### 7.2 Transiciones legales

| Desde | Hacia | Disparador |
|---|---|---|
| `available` | `reserved` | El comprador selecciona el número |
| `available` | `held` | El vendedor aparta a nombre de un conocido |
| `available` | `paid` | El vendedor registra venta en efectivo |
| `reserved` | `pending_review` | El comprador sube comprobante |
| `reserved` | `available` | Venció el TTL (cron) o el comprador canceló |
| `pending_review` | `paid` | El vendedor confirma (WhatsApp o panel) |
| `pending_review` | `available` | El vendedor rechaza, o venció sin respuesta |
| `held` | `paid` | El comprador paga, o el vendedor lo asume |
| `held` | `available` | El vendedor lo libera, o llegó la fecha de corte |
| `paid` | `available` | **Solo** endpoint administrativo autenticado |

Cualquier transición fuera de esta tabla lanza excepción de dominio. Nunca falles en
silencio ni corrijas el estado por tu cuenta.

### 7.3 Implementación

Centraliza las transiciones en **una sola clase**
(`api/services/TicketStateMachine.php`). Ningún otro archivo escribe `tickets.status`
directamente. Si encuentras un `UPDATE tickets SET status` fuera de esa clase, es un bug
que hay que reportar.

Cada transición escribe una fila en la bitácora (sección 13), dentro de la misma
transacción.

### 7.4 Tiempos

Configurables, no incrustados en el código:

- `reserved` sin comprobante: **45 minutos**.
- `pending_review` sin respuesta del vendedor: **12 horas**, recordatorio a las 2 horas.
- `held`: hasta `raffles.cutoff_at` (sección 8).

---

## 8. Apartados del vendedor (el caso del "fiado")

### 8.1 El problema real

El vendedor manda el enlace a sus amigos, ellos escogen número, él se los aparta y les
cobra días después o el día del sorteo. Hoy el sistema no soporta eso, así que el
vendedor marca el número como pagado sin que haya entrado plata. Se pierde la
trazabilidad y aparecen números fantasma en el sorteo.

### 8.2 Reglas

- El estado `held` **solo lo crea el vendedor** desde su panel. Nunca el comprador desde
  el enlace público, nunca automáticamente.
- Al apartar, el vendedor **debe** registrar nombre y celular de la persona. Sin esos
  dos datos la operación se rechaza — sin ellos no hay cobro posible después.
- El apartado vence en `cutoff_at`, no en 45 minutos.
- En el talonario público el número se ve ocupado, igual que un `paid`. La distinción
  solo existe en el panel del vendedor.
- **Un `held` no gana.** Si el número de la lotería cae en un `held`, aplica la
  invariante 2.4 y la rifa se reprograma.

### 8.3 Esquema

```sql
ALTER TABLE raffles
  ADD COLUMN cutoff_at DATETIME NULL
      COMMENT 'Cierre de apartados. Default: draw_date - 2 días'
      AFTER draw_date,
  ADD COLUMN sales_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER cutoff_at;

ALTER TABLE tickets
  ADD COLUMN held_by_vendor_id INT UNSIGNED NULL AFTER payment_method,
  ADD COLUMN holder_name       VARCHAR(120)  NULL AFTER held_by_vendor_id,
  ADD COLUMN holder_phone      VARCHAR(20)   NULL AFTER holder_name,
  ADD COLUMN held_at           DATETIME      NULL AFTER holder_phone,
  ADD COLUMN held_note         VARCHAR(255)  NULL AFTER held_at,
  ADD KEY idx_held (raffle_id, status, held_at);
```

`cutoff_at` se calcula al crear la rifa y el vendedor puede ajustarla, pero nunca
después de `draw_date`. Al reprogramar la rifa (sección 12), `cutoff_at` se recalcula.

### 8.4 Vista de cartera

En el panel del vendedor, por rifa:

- Total apartado sin cobrar, en pesos y en cantidad de números.
- Lista de apartados con nombre, celular, número y días transcurridos.
- Por cada fila: marcar como pagado (efectivo o transferencia), enviar al comprador un
  recordatorio de pago por WhatsApp, o liberar el número.
- Contador visible de días para el corte.

### 8.5 Recordatorios

- A **7, 3 y 1 días** antes de `cutoff_at`, el vendedor recibe por WhatsApp el resumen
  de lo que tiene apartado sin cobrar.
- Si el apartado tiene celular válido, el vendedor puede disparar un recordatorio
  directo al comprador con el monto y las llaves de pago. **Ese envío lo decide el
  vendedor, nunca se manda solo** — son sus contactos personales y un mensaje no
  solicitado de la plataforma le quema la relación.
- Al llegar `cutoff_at`, el vendedor recibe la lista de lo que se va a liberar y puede
  asumir los que quiera antes del sorteo.

---

## 9. Boleta digital

### 9.1 Cuándo se emite

**Al momento en que el ticket pasa a `paid`**, por cualquier vía: confirmación por
WhatsApp, confirmación por panel, o venta en efectivo. La emisión ocurre en la misma
transacción que la transición de estado.

### 9.2 Esquema

```sql
ALTER TABLE tickets
  ADD COLUMN ticket_code   CHAR(12)  NULL COMMENT 'Código público de la boleta'
      AFTER payment_method,
  ADD COLUMN issued_at     DATETIME  NULL AFTER ticket_code,
  ADD COLUMN buyer_name    VARCHAR(120) NULL AFTER issued_at,
  ADD COLUMN buyer_phone   VARCHAR(20)  NULL AFTER buyer_name,
  ADD UNIQUE KEY uk_ticket_code (ticket_code);
```

### 9.3 El código

- 12 caracteres del alfabeto Crockford Base32 (`0-9`, `A-Z` sin `I`, `L`, `O`, `U`),
  generados con `random_bytes()`. Formato de presentación: `XXXX-XXXX-XXXX`.
- **Nunca secuencial ni derivado del `id`.** Un código predecible permite enumerar
  boletas ajenas.
- Se genera una sola vez y no cambia. Si el ticket se libera por vía administrativa, el
  código se invalida pero no se reutiliza.

### 9.4 Página pública de la boleta

Nueva ruta: `public/boleta.php?c=XXXX-XXXX-XXXX`.

**No uses `public/verificar.php`** — esa ruta ya está ocupada por la verificación OTP de
cuenta y no tiene relación.

Muestra:

- Estado grande y legible: **BOLETA VÁLIDA** / **ANULADA**.
- Nombre de la rifa, número, cifras, modalidad y lotería.
- Fecha del sorteo (la vigente, si la rifa fue reprogramada).
- Nombre del vendedor y enlace a su perfil público.
- Fecha de emisión y monto pagado.
- Nombre del comprador **enmascarado**: primer nombre e inicial del apellido
  (`Juan P.`). El celular, enmascarado (`300****567`).

El enmascaramiento no es opcional: la URL es compartible y no debe exponer datos
personales completos de terceros.

### 9.5 Formulario de comprobación

`public/comprobar-boleta.php`: campo único para digitar el código, más lectura de QR con
la cámara. Enlázalo desde el pie de página y desde `mis-boletos.php`.

Aplica límite de intentos por IP (`api/utils/RateLimiter.php` ya existe) para impedir
fuerza bruta sobre el espacio de códigos.

### 9.6 Entrega al comprador

Al emitirse, el sistema genera una imagen PNG de la boleta (no PDF: el destino es
WhatsApp) con el número en grande, el código, el QR hacia `boleta.php`, y los datos de la
rifa. Se guarda fuera del directorio público y se envía al comprador por WhatsApp si
dejó celular. Siempre queda descargable desde `mis-boletos.php`.

Genera la imagen con GD, que ya es dependencia del proyecto. No agregues librerías de
PDF ni de plantillas para esto.

---

## 10. Confirmación del vendedor: dos vías

### 10.1 Vía principal — WhatsApp

Al pasar un ticket a `pending_review`, el vendedor recibe un mensaje con lo mínimo para
decidir: nombre del comprador, número(s), monto exacto con sufijo, hora, enlace al
comprobante. Dos botones: confirmar / rechazar.

Reglas del webhook entrante:

- **Idempotente.** Un mismo `message_id` procesado dos veces no produce dos
  transiciones. Persiste los `message_id` atendidos.
- Verifica que el celular que responde sea el `vendors.phone` dueño de la rifa. Un
  tercero no confirma nada.
- Si el ticket ya no está en `pending_review`, responde explicando el estado actual en
  vez de forzar la transición.

Reutiliza `packages/whatsapp-engine` por sus puertos. No escribas un cliente nuevo.

### 10.2 Vía de contingencia — panel del vendedor

**Obligatoria.** El motor de WhatsApp puede caerse, la sesión puede desconectarse, el
vendedor puede cambiar de número. Sin esta vía la plataforma queda inutilizable.

En el panel, sección "Pagos por confirmar":

- Lista de tickets en `pending_review` de todas sus rifas, con miniatura del
  comprobante, monto exacto, nombre y celular del comprador, y antigüedad.
- Botones confirmar / rechazar por fila, y confirmación en lote.
- Al rechazar, motivo obligatorio de una lista corta: no llegó, monto distinto,
  comprobante ilegible, comprobante repetido, otro.
- Indicador visible del estado de la conexión de WhatsApp. Si está caída, un aviso:
  "WhatsApp desconectado — confirma tus pagos desde aquí."

**Ambas vías usan exactamente el mismo servicio de dominio.** La única diferencia es el
campo `source` en la bitácora (`whatsapp` | `dashboard` | `cash`). Nunca dupliques la
lógica de transición para el panel.

---

## 11. Concurrencia

Aquí es donde el proyecto se rompe si se hace mal: cien personas reciben el mismo enlace
por WhatsApp y entran al mismo tiempo.

**Toda reserva o cambio de estado de un ticket ocurre en transacción con bloqueo de
fila:**

```php
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT status FROM tickets
          WHERE raffle_id = :raffle AND ticket_number = :number
          FOR UPDATE'
    );
    $stmt->execute(['raffle' => $raffleId, 'number' => $number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new TicketNotFound($raffleId, $number);
    }
    if ($row['status'] !== 'available') {
        throw new TicketNotAvailable($raffleId, $number);
    }

    // ... transición + emisión de boleta + bitácora ...

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

Reglas:

- El `FOR UPDATE` es obligatorio. Sin él hay condición de carrera y dos personas pagan
  el mismo número.
- Si la orden abarca varios números, **ordénalos ascendentemente** antes de bloquear.
  Bloquear en orden distinto entre peticiones produce deadlocks.
- Nunca hagas llamadas HTTP, envío de WhatsApp ni generación de imágenes dentro de la
  transacción. Encola el efecto y despáchalo después del `commit()`.
- Ante `TicketNotAvailable`, la interfaz muestra un mensaje claro y refresca el
  talonario. No reintentes en silencio ni asignes otro número por tu cuenta.

---

## 12. Sorteo y reprogramación

### 12.1 Cómo se determina el ganador

Llega el resultado oficial de la lotería (`lottery_results`). Se aplica
`raffles.winning_mode` y `digits` para obtener el número ganador, y se busca el ticket
correspondiente.

Tres desenlaces:

1. El ticket está en `paid` → **hay ganador**. Se registra en `raffle_winners` y arranca
   el flujo de la sección 13.
2. El ticket está en `available`, `reserved`, `pending_review` o `held` → **no hay
   ganador**. La rifa pasa a `pending_reschedule`.
3. El número no existe en el talonario (talonario de 100 y salió 347, por ejemplo) →
   igual que el caso 2.

### 12.2 Reprogramación

`raffles.draw_rescheduled_count` ya existe en el esquema. Úsala.

```sql
ALTER TABLE raffles
  MODIFY COLUMN status ENUM('draft','active','blocked','pending_reschedule',
                            'completed','cancelled')
      NOT NULL DEFAULT 'draft';

CREATE TABLE raffle_draws (
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
) ENGINE=InnoDB;
```

En el panel del vendedor, cuando la rifa está en `pending_reschedule`, aparece la opción
de reprogramar: elegir nueva `draw_date` (siguiente sorteo de la misma lotería) y
confirmar.

Al reprogramar:

- **Todos los tickets `paid` se conservan.** Nadie vuelve a pagar. Es la misma boleta,
  con el mismo código, y su página pública muestra la nueva fecha.
- Los tickets liberados vuelven a estar a la venta.
- `cutoff_at` se recalcula contra la nueva fecha.
- La rifa vuelve a `active`.
- Se notifica a todos los compradores con ticket `paid`: número, nueva fecha, motivo.

### 12.3 Guardas contra manipulación

Permitir que el vendedor relance la rifa es un vector de fraude si no se acota. Las
guardas no son opcionales:

- **La opción solo aparece cuando el sistema verificó que el ticket ganador no estaba en
  `paid`.** Nunca se habilita por declaración del vendedor. Si el ticket estaba pagado,
  no hay botón, no hay endpoint, no hay reprogramación.
- **Nunca se reprograma una rifa con ganador válido.** Un intento de hacerlo lanza
  excepción y se registra como incidente de seguridad.
- **Máximo 3 reprogramaciones** (`draw_rescheduled_count <= 3`). Al cuarto desenlace sin
  ganador la rifa pasa a `cancelled` y entra en el flujo de devolución.
- **Todo intento queda en `raffle_draws` y se muestra públicamente** en la página de la
  rifa y en el hall de ganadores: fecha, número que salió, estado del ticket, resultado.
  La transparencia es lo que hace confiable el mecanismo.
- La nueva `draw_date` debe ser posterior a la anterior y corresponder a un sorteo real
  de la misma lotería. No permitas cambiar de lotería al reprogramar.

### 12.4 Cancelación por tope

Al llegar a `cancelled` por agotar reprogramaciones, la plataforma **no devuelve plata**
(nunca la tuvo). Lo que hace es:

- Marcar la rifa como cancelada, visible públicamente.
- Generar para el vendedor la lista de compradores con ticket `paid`, monto y contacto,
  para que él haga las devoluciones.
- Registrar la cancelación en el perfil público del vendedor.

Sin este cierre, quien pagó queda atrapado en una rifa que nunca juega.

---

## 13. Entrega del premio y transparencia

### 13.1 El principio

El vendedor no puede declarar por su cuenta que entregó. Se necesitan **dos actores con
tokens distintos**.

### 13.2 Estados

| Estado | Quién lo produce |
|---|---|
| `pending` | Sistema, al registrar el ganador |
| `accepted` | El ganador acepta el premio (token propio) — ya existe en v3.8 |
| `delivery_reported` | El vendedor declara que entregó |
| `delivery_confirmed` | **El ganador** confirma que recibió (token distinto) |
| `disputed` | El ganador niega haber recibido |

Solo `delivery_confirmed` se muestra en verde en el hall de ganadores.
`delivery_reported` se muestra como "pendiente de confirmación del ganador".
`disputed` se muestra como tal, sin ocultarse.

### 13.3 Esquema

```sql
ALTER TABLE raffle_winners
  ADD COLUMN delivery_status ENUM('pending','delivery_reported',
                                  'delivery_confirmed','disputed')
      NOT NULL DEFAULT 'pending' AFTER acceptance_ip,
  ADD COLUMN delivery_reported_at   DATETIME     NULL AFTER delivery_status,
  ADD COLUMN delivery_token         VARCHAR(64)  NULL AFTER delivery_reported_at,
  ADD COLUMN delivery_confirmed_at  DATETIME     NULL AFTER delivery_token,
  ADD COLUMN delivery_confirmed_ip  VARCHAR(45)  NULL AFTER delivery_confirmed_at,
  ADD COLUMN delivery_photo_path    VARCHAR(255) NULL AFTER delivery_confirmed_ip,
  ADD COLUMN dispute_reason         TEXT         NULL AFTER delivery_photo_path,
  ADD UNIQUE KEY uniq_delivery_token (delivery_token);
```

`delivery_token` es distinto de `acceptance_token`, se genera al reportar la entrega y
se invalida al usarse.

### 13.4 Flujo

1. Se registra el ganador. Mensaje de felicitación con enlace de **aceptación**.
2. El ganador acepta → `accepted`. El vendedor recibe aviso con los datos de contacto.
3. El vendedor entrega y marca "entregué" en su panel → `delivery_reported`. El sistema
   envía al ganador un mensaje **distinto**, con su enlace de confirmación.
4. El ganador confirma. Puede adjuntar una foto recibiendo el premio (opcional) →
   `delivery_confirmed`.
5. Si el ganador dice que no recibió → `disputed`, con motivo. Notifica al vendedor y al
   admin.

**No metas la aceptación y la confirmación de entrega en el mismo mensaje.** Son dos
momentos separados por días, y mezclarlos hace que el ganador confirme una entrega que
todavía no ocurrió — justo lo que este mecanismo existe para evitar.

### 13.5 Reputación pública

En el perfil público del vendedor: rifas ejecutadas, reprogramaciones, cancelaciones,
entregas confirmadas por el ganador y disputas abiertas. Ese historial es su reputación
y vale más que cualquier verificación que la plataforma pueda hacer.

---

## 14. Bitácora

Toda transición de estado y toda decisión humana se registra.

```sql
CREATE TABLE ticket_events (
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
) ENGINE=InnoDB;
```

Se escribe dentro de la misma transacción que la transición. Es lo único que permite
resolver una disputa entre vendedor y comprador.

---

## 15. Cobro de la plataforma al vendedor

### 15.1 Lo que ya existe

- `system_settings.billing_mode` (`commission` | `talonario`), `talonario_fee`,
  `commission_enabled`.
- `raffles.commission_amount`, `commission_paid`, `commission_payment_date`,
  `commission_due_date`.
- Un trigger que calcula `commission_due_date = draw_date - 8 días`.
- `vendors.commission_balance`.

**Construye sobre eso.** No crees una tabla de facturas paralela ni recalcules la fecha
de vencimiento por tu cuenta: `commission_due_date` es la fecha límite.

### 15.2 Calendario de avisos

Antes de `commission_due_date`, a **7, 3, 2 y 1 días**. Reglas:

- Si la rifa se crea con menos de 7 días para el vencimiento, se envía de inmediato el
  primer aviso del calendario que aún aplique. Los ya vencidos no se mandan.
- Cada aviso se registra en `whatsapp_notifications` con `message_type =
  'billing_reminder'`. **El envío es idempotente**: un aviso ya enviado no se repite
  aunque el cron corra dos veces.
- El mensaje dice el monto, el concepto (comisión o talonario), la fecha límite y cómo
  pagar.

### 15.3 Consecuencia del impago

Al pasar `commission_due_date` con `commission_paid = 0`:

- Se bloquean las **ventas nuevas** de esa rifa (`raffles.sales_blocked = 1`).
- Se bloquea la **creación de rifas nuevas** por ese vendedor.
- La rifa muestra públicamente que el vendedor tiene saldo pendiente con la plataforma.

**Lo que NO se bloquea, bajo ninguna circunstancia:**

- El sorteo de la rifa ni su reprogramación.
- Los tickets ya pagados ni sus boletas.
- El registro del ganador y todo el flujo de entrega del premio.

Los compradores ya pagaron y no tienen culpa del incumplimiento del vendedor. Anularles
la rifa desprestigia a Mis Rifas, no al vendedor. **El castigo recae sobre el vendedor,
nunca sobre el comprador.** Si una tarea te pide bloquear el sorteo o invalidar tickets
pagados por mora, detente y consulta.

### 15.4 Estado actual

Mientras `commission_enabled = 0`, `commission_amount` queda en 0 y no se envía ningún
aviso, tal como ya hace el trigger. El calendario de avisos debe consultar ese
interruptor antes de encolar nada.

---

## 16. Antifraude

Implementa las tres. La decisión final siempre es del vendedor; el sistema informa, no
juzga.

1. **Hash de comprobante.** `SHA-256` del archivo. Si el hash ya existe en otro ticket,
   marca como sospechoso y avísalo en el mensaje al vendedor. No rechaces automático:
   hay falsos positivos.
2. **Ventana temporal.** Si el comprobante declara fecha fuera del rango de la reserva,
   señálalo.
3. **Reputación del comprador.** Un celular con dos o más rechazos en 30 días queda
   señalado. Nunca lo bloquees en silencio.

### Subida de archivos

- Solo `image/jpeg`, `image/png`, `image/webp`. Máximo 5 MB.
- Valida el tipo real con `finfo`, nunca por extensión ni por el `Content-Type` del
  cliente.
- Guarda fuera del directorio público y sirve por un controlador que verifique permisos.
  Nota: `public/assets/uploads/` es accesible directamente; los comprobantes y las
  imágenes de boleta **no** van ahí.
- Reescribe la imagen con GD antes de almacenarla, para descartar payloads embebidos.

---

## 17. Convenciones de código

- PSR-12. `declare(strict_types=1);` en archivos nuevos.
- Tipado explícito en parámetros y retornos. `mixed` solo con justificación escrita.
- Excepciones de dominio propias (`TicketNotAvailable`, `InvalidTransition`,
  `ReservationExpired`, `RescheduleNotAllowed`). Nunca `throw new Exception('...')`
  genérico.
- Sin `die()` ni `exit()` fuera del punto de entrada.
- Ninguna credencial en el repositorio. Todo por variables de entorno.
- Toda salida a HTML pasa por `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Reutiliza los repositorios de `api/repositories/`; no escribas SQL suelto en los
  endpoints.

---

## 18. Cómo trabajar

**Antes de escribir código**, expón el plan: archivos a crear o modificar, transiciones
de estado que toca, riesgo de concurrencia. Espera aprobación.

**Ante cualquier ambigüedad, pregunta.** No inventes reglas de negocio, no supongas
valores de configuración, no rellenes huecos con lo que "normalmente se hace". Aquí una
suposición equivocada significa que alguien pagó y no recibió su número.

**No modifiques el esquema por iniciativa propia.** Toda migración se propone, se
justifica y se aprueba. Sigue la numeración existente (`v4.4_...`, `v4.5_...`) y el
formato de `database/migrations/README.md`.

**Ejecuta lo que escribes.** No des por terminada una tarea sin haberla corrido.

---

## 19. Definición de terminado

- [ ] El código corre sin errores ni advertencias.
- [ ] Toda transición pasa por `TicketStateMachine` y quedó en `ticket_events`.
- [ ] Las rutas que reservan tickets usan `FOR UPDATE` dentro de transacción.
- [ ] Existe prueba de concurrencia: dos procesos sobre el mismo número en paralelo, uno
      gana y el otro recibe `TicketNotAvailable`. **Obligatoria** para cualquier cambio
      que toque `tickets`.
- [ ] Confirmar por WhatsApp, por panel y en efectivo llaman al mismo servicio de
      dominio.
- [ ] Todo ticket en `paid` tiene `ticket_code` único y no predecible.
- [ ] La página pública de la boleta enmascara nombre y celular del comprador.
- [ ] Ninguna llamada externa ni generación de imagen ocurre dentro de una transacción
      abierta.
- [ ] El webhook de WhatsApp es idempotente y verifica la identidad del remitente.
- [ ] Ningún ticket fuera de `paid` puede ser declarado ganador.
- [ ] La reprogramación solo se habilita tras verificación del sistema, respeta el tope
      de 3, y queda registrada en `raffle_draws` de forma pública.
- [ ] La confirmación de entrega del premio usa un token distinto al de aceptación.
- [ ] No se agregaron dependencias sin autorización.

---

## 20. Errores conocidos a evitar

Ya se analizaron y se descartaron. Si tu propuesta se parece a alguno, detente.

- Integrar una pasarela de pagos "solo para el MVP".
- Centralizar los cobros de las rifas en una cuenta de la plataforma.
- Decodificar el QR del comprobante de Nequi sin la app oficial.
- Confiar en OCR del pantallazo como confirmación.
- Marcar un ticket como pagado sin decisión explícita del vendedor.
- Reservar un ticket sin bloqueo de fila.
- Enviar el mensaje de WhatsApp dentro de la transacción.
- Duplicar la lógica de transición entre WhatsApp, panel y efectivo.
- Emitir códigos de boleta secuenciales o derivados del `id`.
- Exponer nombre y celular completos del comprador en la boleta pública.
- Usar `public/verificar.php` para la boleta (esa ruta es del OTP de cuenta).
- Permitir reprogramar una rifa que sí tuvo ganador con ticket pagado.
- Habilitar la reprogramación por declaración del vendedor sin verificación del sistema.
- Reprogramar sin tope, dejando atrapados a quienes ya pagaron.
- Permitir que el vendedor confirme la entrega del premio en nombre del ganador.
- Declarar ganador a un ticket en `held`, `reserved` o `pending_review`.
- Bloquear el sorteo o invalidar tickets pagados por mora del vendedor.
- Eliminar o modificar `packages/whatsapp-engine` para acomodar a Mis Rifas.
- Guardar montos nuevos como `FLOAT`.
- Instalar un framework o un ORM para "simplificar".
