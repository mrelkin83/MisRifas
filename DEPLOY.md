# MisRifas — Guía de despliegue a VPS

Checklist y pasos para llevar este proyecto de "corriendo en Laragon local"
a un VPS de producción. Escrito durante la Fase 4 (hardening) de la
iniciativa de reconstrucción del proyecto; asume Ubuntu/Debian + Apache +
PHP-FPM o mod_php + MySQL/MariaDB.

## 1. Requisitos del servidor

- PHP **8.4** (el proyecto se desarrolló y probó contra 8.4.12 — verificar
  con `php -v`, no asumir que la CLI y el runtime real de Apache coinciden).
- Extensiones PHP: `pdo_mysql`, `mbstring`, `gd` o `imagick`, `curl`, `json`,
  `openssl`.
- MySQL 8.x o MariaDB 10.6+.
- Apache con `mod_rewrite` habilitado. **Si el servidor corre PHP vía
  mod_fcgid o mod_cgi (no mod_php, no PHP-FPM con FastCGI bien configurado),
  ver la sección 5 — es un problema real que ya mordió a este proyecto.**
- Node.js + npm solo se necesitan en la máquina de build (para
  `npm run build:css`), no en el propio VPS si se sube
  `public/css/tailwind.min.css` ya compilado (así se hace hoy: el archivo
  compilado SÍ se versiona en git, ver `.gitignore`).
- Composer, para `packages/whatsapp-engine` (path-repo del `composer.json`
  raíz).

## 2. Primer despliegue

> **Atajo — auto-instalador.** `deploy/install-vps.sh` automatiza casi todo
> este documento (paquetes, PHP, MySQL, VirtualHost, HTTPS, permisos, cron) con
> el dominio `misrifas.online` ya configurado. Como root en el VPS:
> ```bash
> bash deploy/install-vps.sh              # base (web + BD + HTTPS + cron)
> bash deploy/install-vps.sh --mail       # + servidor de correo Postal
> bash deploy/install-vps.sh --sms        # + gateway SMS Gammu (requiere módem)
> ```
> Revisa las variables de CONFIGURACIÓN al inicio del script antes de correrlo.
> Los pasos manuales de abajo siguen valiendo como referencia y para depurar.

```bash
git clone <repo> /var/www/misrifas
cd /var/www/misrifas

# Dependencias PHP
composer install --no-dev --optimize-autoloader

# Variables de entorno — copiar y completar con secretos reales
cp .env.example .env
nano .env   # DB_*, APP_SECRET_KEY, CRON_SECRET_KEY, credenciales de pago, etc.

# Base de datos (crea el schema desde cero — ver database/migrations/README
# para el detalle de qué aplica cada migración)
DB_NAME=misrifas MYSQL_USER=... MYSQL_PWD=... bash database/migrations/install.sh

# Permisos: Apache necesita escribir en logs/ y en las carpetas de subida
mkdir -p logs public/assets/uploads public/uploads uploads
chown -R www-data:www-data logs public/assets/uploads public/uploads uploads
chmod -R 755 logs public/assets/uploads public/uploads uploads
```

Apuntar el DocumentRoot del VirtualHost a la **raíz del proyecto**, no a
`public/` — el `.htaccess` de la raíz reescribe todo hacia
`public/index.php` como front controller. El `.htaccess` ya **no** usa
`RewriteBase` (la sustitución relativa `index.php` se resuelve contra el
directorio del `.htaccess`), así que funciona igual sirviendo desde la raíz
del dominio (`misrifas.online`) que desde un subdirectorio local. Requiere
`AllowOverride All` en el `<Directory>` (con `None` se ignora el `.htaccess`
entero, incluido el fix del header `Authorization` — ver sección 5).

## 3. CSS compilado (Tailwind)

Todas las páginas cargan `public/css/tailwind.min.css` en vez del script de
`cdn.tailwindcss.com` (migrado en Fase 4 — cdn.tailwindcss.com está
explícitamente marcado como "no usar en producción" por el propio Tailwind).
Ese archivo **sí está versionado en git** — un `git pull` en el VPS ya lo
trae actualizado, no hace falta correr `npm` ahí.

Si se edita cualquier `<style>` de una página o se agregan clases nuevas de
Tailwind, hay que recompilar ANTES de hacer commit:

```bash
npm install
npm run build:css
git add public/css/tailwind.min.css
```

El archivo fuente es `public/css/tailwind-input.css`. El bloque
`@theme { --color-primary: ... }` ahí define el dorado de marca — si se
cambia el acento del sitio, es el único lugar que hay que tocar.
**Importante:** todo el CSS propio de ese archivo vive dentro de
`@layer base { ... }` / `@layer components { ... }` — nunca agregar reglas
sueltas fuera de un `@layer`, porque CSS sin capa siempre gana sobre
cualquier utilidad de Tailwind sin importar la especificidad (así se rompió
silenciosamente `text-primary` en varios enlaces durante esta misma fase).

## 4. HTTPS

`.htaccess` ya fuerza HTTPS a nivel de aplicación (redirect 301, excluyendo
`localhost`/`127.0.0.1` para no romper desarrollo local). Falta la parte de
infraestructura:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d misrifas.online -d www.misrifas.online
```

Certbot configura el VirtualHost HTTPS y el renovado automático. Verificar
después con `curl -I http://misrifas.online` que responde 301 hacia `https://`.
(El auto-instalador de la sección 2 ya hace este paso.)

## 5. mod_fcgid / mod_cgi y el header Authorization (importante)

Toda la autenticación de vendors/admin/usuarios de este proyecto usa Bearer
tokens vía el header `Authorization`. Apache bajo mod_fcgid/mod_cgi
**descarta ese header antes de que llegue a PHP por defecto** — no es un
bug de la aplicación, es el comportamiento estándar de ese SAPI. Sin el fix,
el login funciona (no necesita el header) pero **ninguna llamada
autenticada después del login funciona nunca** (401 "No hay token" en todo).

El `.htaccess` de este proyecto ya trae el fix (`RewriteRule` que reenvía
`%{HTTP:Authorization}` como `HTTP_AUTHORIZATION`), así que si Apache tiene
`mod_rewrite` activo debería funcionar solo. Para confirmar que el header
realmente llega, se puede probar con un endpoint temporal:

```php
<?php header('Content-Type: application/json');
echo json_encode(['auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? null]);
```

```bash
curl -s https://tu-dominio/ese-archivo.php -H "Authorization: Bearer test123"
# debe devolver {"auth":"Bearer test123"}, no {"auth":null}
```

Si sigue devolviendo `null` pese al `.htaccess`, revisar si el VirtualHost
tiene `AllowOverride All` (si es `None`, las reglas del `.htaccess` se
ignoran por completo) o si el SAPI es PHP-FPM (que normalmente sí pasa el
header solo, sin necesitar el `RewriteRule`).

## 6. Backups de base de datos

`database/backup.sh` hace `mysqldump` comprimido con rotación automática
(default: conserva 14 días). Lee las credenciales del `.env` del proyecto.

```bash
chmod +x database/backup.sh
./database/backup.sh   # prueba manual primero

# Cron diario a las 3am
crontab -e
0 3 * * * /var/www/misrifas/database/backup.sh >> /var/www/misrifas/logs/backup.log 2>&1
```

Los dumps quedan en `database/backups/` (gitignored). Considerar copiarlos
además a almacenamiento externo (S3, otro servidor) — este script solo
protege contra corrupción/borrado accidental en la BD, no contra que el VPS
completo se pierda.

## 7. Cron jobs de la aplicación

Además del backup, estos scripts en `cron/` necesitan ejecutarse
periódicamente. Los que aceptan `?secret=` también se pueden disparar por
HTTP (usando `CRON_SECRET_KEY` del `.env`) si se prefiere un servicio
externo de cron en vez de crontab del sistema.

```cron
# Expira/libera reservas de boletos vencidas — cada minuto, es la más sensible
# a tiempo. Único cron de expiración: libera tickets, sincroniza numero_reservas
# y cancela payment_intents. (release_reservations.php quedó unificado aquí.)
* * * * * php /var/www/misrifas/cron/expire-reservations.php >> /var/www/misrifas/logs/cron.log 2>&1

# Recordatorios (WhatsApp/email) — cada hora
0 * * * * php /var/www/misrifas/cron/send_reminders.php >> /var/www/misrifas/logs/cron.log 2>&1

# Verificación de comisiones vencidas — diario 4am
0 4 * * * php /var/www/misrifas/cron/check_commissions.php >> /var/www/misrifas/logs/cron.log 2>&1

# Resultados de lotería + procesar sorteos — diario, después de que
# cierren los sorteos del día (ajustar hora según las loterías reales
# configuradas en la tabla `lotteries`, columna draw_time)
30 22 * * * php /var/www/misrifas/cron/fetch_lottery_results.php >> /var/www/misrifas/logs/cron.log 2>&1
0 23 * * * php /var/www/misrifas/cron/process_draws.php >> /var/www/misrifas/logs/cron.log 2>&1

# Notificaciones pendientes en cola — cada 10 minutos
*/10 * * * * php /var/www/misrifas/cron/process_notifications.php >> /var/www/misrifas/logs/cron.log 2>&1

# El Tapazo (feature aparte, minijuego) — cada 10 segundos vía servicio
# systemd/supervisor, no crontab (la frecuencia es demasiado alta para cron
# estándar) — ver cron/INICIAR_SERVICIO_TAPAZO.bat como referencia de la
# versión Windows/desarrollo, adaptar a un .service de systemd en el VPS.
```

## 8. Correo saliente — servidor open-source propio (Postal)

La app **solo envía** correo (notificaciones de resultados + campañas) y habla
SMTP estándar (`api/services/MailService.php`). Para no depender de Gmail/Brevo
ni de sus límites, lo recomendado es un servidor de correo self-hosted en el
propio VPS. Comparativa (evaluada en 2026):

| Opción | Encaja porque | Peso |
|--------|---------------|------|
| **Postal** ✅ | Servidor de **envío transaccional** self-hosted (un Mailgun/SendGrid propio): SMTP + API + DKIM + colas + tracking de entregabilidad. Es exactamente el caso de MisRifas. | Docker, medio |
| **Stalwart** | Servidor completo (envía y recibe) en **un solo binario**, moderno y ligero. Buena alternativa si además quieres buzones. | Ligero |
| Mailcow / Mailu | Suite de correo completa con buzones/webmail. **Sobredimensionado** para solo enviar. | Pesado |

Instalación asistida: `bash deploy/install-vps.sh --mail` (instala Docker y
deja las instrucciones de Postal). Luego, en Postal: crear un *mail server*,
generar credenciales SMTP y publicar los registros **SPF, DKIM y DMARC** de
`misrifas.online` en el DNS (sin ellos el correo cae a spam). Finalmente en
`.env`: `EMAIL_ENABLED=true`, `SMTP_HOST=127.0.0.1`, `SMTP_PORT=25` (o el que
exponga Postal), `SMTP_USER`/`SMTP_PASS` de Postal, `EMAIL_FROM_ADDRESS=no-reply@misrifas.online`.
No hay que tocar código: `MailService.php` ya envía por SMTP.

## 9. SMS — gateway open-source (Gammu SMSD)

El proyecto ya trae `sms-service/` que encola SMS en la **bandeja de salida de
Gammu SMSD** (open-source). Gammu es el estándar para SMS por **módem/dongle GSM
USB** con AT commands — requiere hardware (un módem GSM con SIM colombiana).

- Instalación asistida: `bash deploy/install-vps.sh --sms` (instala `gammu` +
  `gammu-smsd` y deja los pasos del módem).
- Se corrigió el path del microservicio: antes estaba hardcodeado a
  `C:\xampp\...` (no existía en Linux). Ahora se toma de `GAMMU_SMSD_DB` en
  `.env`; apúntalo al SQLite de Gammu (`${APP_DIR}/sms-service/gammu.db`) si usas
  el backend sqlite.
- Capa de gestión opcional sobre Gammu: **playSMS** o **Kalkun** (UI web + bulk).
  Para SMPP hacia una carrier en vez de módem, **Jasmin** o **Kannel**.

Si no quieres hardware, un proveedor cloud (Twilio o uno colombiano) es más
simple, pero no es self-hosted/open-source — fuera del alcance de esta guía.

## 10. Checklist pre-lanzamiento

- [ ] `.env` completo con secretos reales (nunca copiar el `.env` de
      desarrollo tal cual — regenerar `APP_SECRET_KEY`/`CRON_SECRET_KEY`).
- [ ] `APP_ENV=production` y `APP_DEBUG=false` en `.env`.
- [ ] HTTPS funcionando (certbot) y el `.htaccess` redirigiendo correctamente.
- [ ] Header `Authorization` confirmado llegando a PHP (sección 5).
- [ ] `database/backup.sh` probado manualmente y agendado en cron.
- [ ] Cron jobs de la sección 7 agendados.
- [ ] `public/css/tailwind.min.css` presente y actualizado (no hay
      `cdn.tailwindcss.com` en ninguna página — verificar con
      `grep -r "cdn.tailwindcss.com" public/`).
- [ ] Credenciales de pago reales (Wompi como mínimo) configuradas y
      probadas con una transacción de prueba antes de anunciar el sitio.
- [ ] `uploads/.htaccess` presente (bloquea ejecución de PHP en esa carpeta).
- [ ] Primer backup manual corrido y verificado (`gunzip -c backup.sql.gz | head`).
