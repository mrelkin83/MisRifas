# Base de Datos - MisRifas

## Estado actual

El schema real de la BD `misrifas` se produce aplicando, **en este orden exacto**,
las 6 migraciones vigentes en esta carpeta. El orden importa por dependencias de
foreign key (`vendors` antes de que `raffles` pueda referenciarla, `admin_users`
antes de `vendors`, etc).

## Cómo reconstruir la base de datos

```bash
./install.sh
```

Por defecto conecta como `mysql -u root` (sin password) contra una BD llamada
`misrifas`. Para otra configuración:

```bash
MYSQL_USER=usuario MYSQL_PWD=clave DB_NAME=otra_bd ./install.sh
```

El script hace `DROP DATABASE` + `CREATE DATABASE` antes de aplicar las
migraciones — **no tiene guardas de confirmación**, está pensado para
desarrollo/CI. Si hay datos reales que conservar, sacar un `mysqldump` antes.

Si no se puede ejecutar el `.sh` (por ejemplo en Windows sin Git Bash), aplicar
manualmente en este orden vía `mysql` CLI o phpMyAdmin:

| # | Archivo | Qué agrega |
|---|---------|------------|
| 1 | `000_setup_completo_legacy.sql` | Las tablas base: `admin_users`, `users`, `lotteries`, `lottery_results`, `raffles`, `tickets`, `raffle_images`, `raffle_winners`, `banners`, `system_settings`, `notifications`, `tapazos`, `tapazo_jugadores`, vistas (`v_numeros_disponibles`, `v_reservas_expirando`), triggers/procedimientos, las 15 loterías colombianas precargadas y el admin por defecto. |
| 2 | `v2.1_fix_schema.sql` | Ajusta el ENUM de `raffles.status`, agrega `audit_log` y `password_resets`. |
| 3 | `v3.0_saas_multi_vendor.sql` | Agrega `vendors`, `raffles.vendor_id` (FK), columnas de scraping en `lottery_results`, tabla `message_queue`. |
| 4 | `v3.1_pagos_transaccionales.sql` | Agrega `numero_reservas`, `payment_intents`, `webhook_logs`. |
| 5 | `v3.2_buyer_auth_columns.sql` | Agrega columnas de autenticación de comprador en `users` (incluye `phone_whatsapp`, `auth_token` + índice). |
| 6 | `v3.3_whatsapp_engine.sql` | Agrega las tablas del motor de WhatsApp: `wa_config`, `wa_conversaciones`, `wa_mensajes`, `wa_agentes`, `wa_eventos`. |

Resultado esperado: **27 tablas** (más 2 vistas). Verificar con:

```sql
SHOW TABLES;
SELECT COUNT(*) FROM lotteries;   -- 15
DESCRIBE admin_users;             -- debe tener columna `department`
```

## `legacy_archive/`

Archivos que **NO se aplican** — o son duplicados exactos de una migración
vigente, o rompen el schema si se aplican después de `000` (columnas duplicadas,
tablas con shape incompatible):

- `001_tapazo_module.sql`, `002_tapazos_legacy.sql` — versión antigua de
  `tapazos`/`tapazo_jugadores` incompatible con la que ya trae `000` (nombres de
  columna distintos, en inglés). Aplicarlos tras `000` falla o corrompe la tabla.
- `003_add_columns.sql` — agrega a `raffles` columnas que `000` ya incluye
  (`Duplicate column name` si se aplica).
- `004_update_lotteries.sql` — inserta las mismas 15 loterías que `000` ya carga
  (inofensivo pero redundante).
- `setup_completo.sql` — duplicado byte a byte de `000_setup_completo_legacy.sql`.
- `schema.sql`, `add_columns.sql`, `seed_data.sql`, `tapazo_module.sql`,
  `tapazos.sql`, `update_lotteries.sql` — sueltos en `database/` (fuera de
  `migrations/`), predecesores de las migraciones actuales.

Se conservan por historial, no por que haga falta aplicarlos.

## Usuario administrador por defecto

`000_setup_completo_legacy.sql` crea:

```
Email: admin@misrifas.com
Password: password123
Rol: super_admin
```

Cambiar esta contraseña es obligatorio antes de cualquier despliegue en VPS/producción.

## Nuevas migraciones

Para agregar funcionalidad, crear un archivo `vX.Y_descripcion.sql` nuevo en esta
carpeta y agregarlo al arreglo `MIGRATIONS` de `install.sh` (al final, en orden).

## Respaldo

```bash
mysqldump -u root -p misrifas > backup_$(date +%Y%m%d).sql
```
