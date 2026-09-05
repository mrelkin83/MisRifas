# Sistema anti-baneo multi-instancia de WhatsApp

> Estado: en producción desde v4.19. Interruptor administrable, apagado por defecto.

## El problema que resuelve

WhatsApp penaliza (hasta banear) los números que envían muchos mensajes salientes
en poco tiempo, sobre todo hacia contactos que no les han escrito primero. Una
plataforma de rifas envía en ráfagas: resultados de sorteos, reprogramaciones,
confirmaciones de pago, recordatorios. Si todo sale siempre del mismo número, ese
número concentra todo el riesgo.

La mitigación: **hasta 5 números vinculados a la vez, y un rotador que va
turnando cuál de ellos es el emisor en cada tanda de envíos**. Ningún número
carga solo con todo el tráfico.

## Arquitectura

### Las instancias: `misrifas-v1` … `misrifas-v5`

- Cada instancia es una conexión independiente en Evolution API, con su propio
  teléfono vinculado por QR.
- El nombre está **fijado por patrón** (`^misrifas-v[1-5]$`) y validado en el
  servidor (`waInstanciaValida()` en `api/whatsapp/admin/index.php`). No se
  aceptan otros nombres: tope duro de 5.
- Las credenciales de Evolution son **de la plataforma** y viven en el `.env`
  del servidor (`WA_EVOLUTION_URL`, `WA_EVOLUTION_APIKEY`), nunca en la BD ni
  en el panel (`waPlataformaCreds()`).

### La instancia ACTIVA

`wa_config.evolution_instancia` guarda cuál instancia es la emisora. **Todos**
los caminos de envío (resultados de sorteo, OTP, comandos SI/NO de pagos, bot de
IA) leen esa fila, así que cambiar la activa redirige TODO el tráfico saliente
sin tocar el motor. Ese es el truco central del diseño: rotar la activa rota todo.

### La entrada no rota

El webhook de recepción se registra en **todas** las instancias (al crear una
nueva, `waCopiarWebhook()` hereda el webhook de cualquier hermana ya
registrada). Los mensajes entrantes se leen desde cualquier número, esté o no
de turno como emisor. La rotación solo afecta la salida.

## El rotador: `api/whatsapp/RotacionInstancias.php`

`RotacionInstancias::rotar($db)` hace round-robin entre las instancias
**conectadas** (estado `open` en Evolution):

1. Verifica el interruptor `wa_rotacion` en `system_settings`; apagado → no hace nada.
2. Consulta `GET /instance/fetchInstances` en Evolution y filtra las
   `misrifas-v[1-5]` con `connectionStatus = open`, ordenadas alfabéticamente.
3. Con **menos de 2 conectadas no hay entre qué rotar** → no hace nada.
4. Avanza de la actual a la siguiente de la lista (circular) y la fija como
   activa en `wa_config.evolution_instancia`.

Propiedades importantes:

- **Best-effort, jamás lanza.** Cualquier fallo (Evolution caído, credenciales
  ausentes, error de BD) devuelve `null` y la tanda sale por la instancia que ya
  estaba activa. Rotar nunca frena un envío.
- Devuelve el nombre de la instancia nueva, o `null` si no rotó.

### Dónde se invoca

`cron/process_notifications.php` (la tanda de la cola de mensajes, cada minuto):
antes de despachar, **si hay mensajes de WhatsApp pendientes en la tanda**,
llama a `RotacionInstancias::rotar()`. Resultado: cada tanda sale por un número
distinto al de la tanda anterior (entre los conectados). Si la tanda no trae
WhatsApps, no se rota — así los turnos no se "queman" en vacío.

## El interruptor: `wa_rotacion`

- Fila en `system_settings` creada por `database/migrations/v4.19_wa_rotacion.sql`.
- **Apagado (`0`) por defecto**: sin rotación, siempre envía la instancia activa.
- Se administra desde el panel (checkbox "🔄 Rotar el número emisor…"), endpoint
  `instancias-rotacion`. Cada cambio queda en la bitácora de auditoría.

## El panel: WhatsApp IA → Conexión, sección 3

`public/admin/whatsapp/conexion.php`. Solo super_admin. Muestra cada instancia
con su estado (✅ conectada / ⏳ esperando QR / ⚪ desconectada), su número, si
tiene webhook, y cuál es la ★ ACTIVA.

Acciones por instancia:

| Botón | Endpoint | Qué hace |
|---|---|---|
| ＋ Nueva instancia | `instancia-crear` | Crea el siguiente slot libre (v1..v5) en Evolution y le hereda el webhook. Se oculta al llegar a 5. |
| Mostrar QR | `instancia-qr` | Pide el QR de esa instancia para vincular (o re-vincular) un teléfono. |
| Usar | `instancia-usar` | La marca como activa (emisora). Solo visible en las no-activas. |
| Desvincular | `instancia-desvincular` | **Logout sin borrar**: cierra la sesión del teléfono pero la instancia sigue creada, lista para re-escanear el QR. Solo visible cuando la instancia está conectada. Si era la activa, `wa_config.estado_conexion` pasa a `desconectado`. |
| 🗑 (Eliminar) | `instancia-eliminar` | Logout **y** delete en Evolution: el número queda desvinculado y la instancia deja de existir. Solo visible en las no-activas. |

Además, el botón general **Desvincular** (`conexion-desconectar`) hace logout de
la instancia activa vía `EvolutionClient::desconectar()`, sin borrarla.

### Guardas

- La instancia **ACTIVA no se puede eliminar**: el endpoint responde 409 y pide
  activar otra primero. Evita quedarse sin emisor por accidente.
- `instancia-desvincular` reporta error si Evolution rechaza el logout (por
  ejemplo, si la instancia ya estaba desconectada); no borra nada.
- Toda acción queda registrada en la bitácora (`AuditLogger`).

## Interacción con el resto del sistema

- **OTP y comandos de pago (SI/NO)**: salen por la activa, o sea que también
  rotan. La respuesta del comprador entra por cualquier instancia (webhook en
  todas), así que no importa desde qué número le llegó el mensaje.
- **Bot de IA**: idem — responde saliendo por la activa del momento.
- **Diagnóstico**: el chip 🩺 de Comunicaciones y `tools/diagnostico.php`
  verifican la conexión de la instancia activa.

## Limitaciones conocidas

- La rotación es **por tanda**, no por mensaje: los hasta 100 mensajes de una
  misma corrida del cron salen todos por el mismo número.
- Si solo hay una instancia conectada, la rotación no hace nada (y está bien:
  no hay alternativa).
- Un contacto puede recibir mensajes de la plataforma desde números distintos
  en días distintos. Es el precio de repartir el riesgo; los mensajes siempre
  se identifican con la marca en el texto.
- Si Evolution reinicia su contenedor Docker, las instancias pueden quedar en
  un estado zombi ("open" pero rechazando envíos con "Connection Closed"). El
  remedio comprobado es reiniciar el contenedor de Evolution; si una instancia
  queda corrupta, desvincularla y volver a escanear el QR.

## Archivos del sistema

| Archivo | Papel |
|---|---|
| `api/whatsapp/RotacionInstancias.php` | El rotador round-robin (lógica anti-baneo). |
| `cron/process_notifications.php` | Invoca la rotación antes de cada tanda con WhatsApps. |
| `api/whatsapp/admin/index.php` | Endpoints `instancias*` y `conexion-*` del panel. |
| `public/admin/whatsapp/conexion.php` | UI de gestión (sección 3). |
| `database/migrations/v4.19_wa_rotacion.sql` | Crea el interruptor `wa_rotacion`. |
| `packages/whatsapp-engine/src/Channel/EvolutionClient.php` | Cliente Evolution del motor (envío, logout de la activa). |
