# Suite de tests de regresión — MisRifas

Tests de **integración** que golpean los endpoints HTTP reales y verifican los
flujos críticos del negocio. Sin dependencias externas (PHP puro + curl).

## Cómo correr

Con el sitio servido localmente (Laragon/Apache + MySQL) y accesible en
`http://localhost/MisRifas`:

```bash
php tests/run.php
```

Sale con código `0` si todo pasa, `1` si algún test falla (apto para CI).

Para apuntar a otra URL local:

```bash
TEST_BASE_URL=http://localhost:8080 php tests/run.php
```

> **Solo local.** El harness aborta si la URL no es `localhost`/`127.0.0.1`.
> Nunca corre contra producción.

## Qué cubre

| Archivo | Flujo crítico |
|---|---|
| `01_reservation.php` | Reserva: corte por `draw_date`, camino feliz, **atomicidad** (número ya tomado → rollback, sin ventas parciales) |
| `02_winner_acceptance.php` | Aceptación de premio: detalle por token, aceptar, **idempotencia**, token inválido |
| `03_authorization.php` | **IDOR** de aprobación de pago (un vendedor no toca boletos ajenos), gates `super_admin` (lotería manual, router WhatsApp) |
| `04_tapazo_ratelimit.php` | Rate limiting del Tapazo público (`crear.php`) |
| `05_http_hardening.php` | Rutas sensibles no servibles por HTTP (`api/cron`, `api/workers`, `api/utils`, `config`) |

## Cómo está hecho

- `bootstrap.php` — aserciones, cliente HTTP (curl), **fixtures** (rifas/boletos,
  compradores, tokens) y registro de **teardown** LIFO.
- Todos los fixtures se marcan `__TEST__` y se **eliminan siempre** al final
  (incluso ante un fatal, vía `register_shutdown_function`). La suite es
  idempotente: no deja residuo en la base.
- `run.php` descubre y ejecuta `regression/*.php` en orden.

## Añadir un test

Crea `regression/NN_nombre.php`. Usa `section()`, `check()`, `assertHttp()`,
`httpGet()/httpPost()` y los helpers `fxRaffle()/fxBuyer()/fxToken()`. Cualquier
dato que crees fuera de esos helpers, regístralo con `onTeardown(fn)`.
