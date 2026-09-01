#!/usr/bin/env bash
# ============================================================================
# MisRifas — Auto-instalador para VPS (Ubuntu/Debian)
# ----------------------------------------------------------------------------
# Automatiza el despliegue descrito en DEPLOY.md: paquetes, PHP, MySQL, Apache
# (DocumentRoot en la RAÍZ del proyecto, AllowOverride All), HTTPS con certbot,
# permisos, cron y, opcionalmente, el servidor de correo open-source (Postal)
# y el gateway SMS open-source (Gammu SMSD).
#
# USO (como root, vía SSH en el VPS):
#   bash install-vps.sh                 # instalación base (web + BD + HTTPS + cron)
#   bash install-vps.sh --mail          # + servidor de correo Postal (Docker)
#   bash install-vps.sh --sms           # + Gammu SMSD (requiere módem GSM USB)
#   bash install-vps.sh --mail --sms    # todo
#   bash install-vps.sh --skip-certbot  # sin HTTPS (p. ej. detrás de un proxy)
#
# Idempotente en lo posible: reejecutarlo no rehace lo ya hecho. Revisa las
# variables de CONFIGURACIÓN antes de correrlo.
# ============================================================================
set -euo pipefail

# ── CONFIGURACIÓN ───────────────────────────────────────────────────────────
DOMAIN="misrifas.online"
WWW_DOMAIN="www.misrifas.online"
CERTBOT_EMAIL="admin@misrifas.online"     # correo para avisos de Let's Encrypt
APP_DIR="/var/www/misrifas"
REPO_URL="https://github.com/mrelkin83/MisRifas.git"
PHP_VERSION="auto"                        # auto = detectar del repo (8.4/8.3/…); o fija p.ej. "8.4"
DB_NAME="misrifas"
DB_USER="misrifas"
WEB_USER="www-data"
# ────────────────────────────────────────────────────────────────────────────

WITH_MAIL=false
WITH_SMS=false
SKIP_CERTBOT=false
for arg in "$@"; do
  case "$arg" in
    --mail) WITH_MAIL=true ;;
    --sms) WITH_SMS=true ;;
    --skip-certbot) SKIP_CERTBOT=true ;;
    *) echo "Argumento desconocido: $arg"; exit 1 ;;
  esac
done

log()  { echo -e "\n\033[1;33m==> $*\033[0m"; }
ok()   { echo -e "\033[1;32m  ✓ $*\033[0m"; }
warn() { echo -e "\033[1;31m  ! $*\033[0m"; }

[ "$(id -u)" -eq 0 ] || { warn "Ejecuta como root (sudo bash install-vps.sh)"; exit 1; }

# ── 1. Paquetes base ────────────────────────────────────────────────────────
detect_php_version() {
  # Si PHP_VERSION es fija y existe en los repos, usarla. Si es "auto",
  # preferir 8.4/8.3 (versiones probadas); si no están, caer a la que provea
  # el metapaquete `php` del repo default (p.ej. 8.5 en Ubuntu 26.04).
  apt-get update -qq -y || true
  if [ "$PHP_VERSION" != "auto" ]; then
    apt-cache policy "php${PHP_VERSION}" 2>/dev/null | grep -q 'Candidate: [0-9]' && return
    warn "php${PHP_VERSION} no está en los repos; autodetectando…"
  fi
  local v
  for v in 8.4 8.3; do
    if apt-cache policy "php${v}" 2>/dev/null | grep -q 'Candidate: [0-9]'; then PHP_VERSION="$v"; return; fi
  done
  # Fallback: versión del metapaquete `php`
  v="$(apt-cache policy php 2>/dev/null | awk '/Candidate/{print $2}' | grep -oE '[0-9]+\.[0-9]+' | head -1)"
  PHP_VERSION="${v:-8.4}"
  warn "Usando PHP ${PHP_VERSION} (repo default). El proyecto se probó en 8.3/8.4; verifica el sitio tras instalar."
}

install_packages() {
  log "Instalando paquetes del sistema"
  export DEBIAN_FRONTEND=noninteractive
  detect_php_version
  log "PHP objetivo: ${PHP_VERSION}"
  apt-get install -y \
    apache2 \
    "php${PHP_VERSION}" "libapache2-mod-php${PHP_VERSION}" \
    "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-curl" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-intl" "php${PHP_VERSION}-sqlite3" \
    mariadb-server git unzip curl
  # Composer
  if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
  fi
  a2enmod rewrite ssl >/dev/null
  ok "Paquetes instalados"
}

# ── 2. Código + dependencias ────────────────────────────────────────────────
deploy_code() {
  log "Desplegando código en ${APP_DIR}"
  if [ -d "${APP_DIR}/.git" ]; then
    git -C "${APP_DIR}" pull --ff-only
  else
    git clone "${REPO_URL}" "${APP_DIR}"
  fi
  cd "${APP_DIR}"
  composer install --no-dev --optimize-autoloader --no-interaction
  ok "Código desplegado"
}

# ── 3. .env ─────────────────────────────────────────────────────────────────
setup_env() {
  log "Configurando .env"
  cd "${APP_DIR}"
  if [ ! -f .env ]; then
    cp .env.example .env
    local secret cron dbpass
    secret="$(openssl rand -hex 32)"
    cron="$(openssl rand -hex 32)"
    dbpass="$(openssl rand -hex 20)"
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
    sed -i "s|^APP_SECRET_KEY=.*|APP_SECRET_KEY=${secret}|" .env
    sed -i "s|^CRON_SECRET_KEY=.*|CRON_SECRET_KEY=${cron}|" .env
    sed -i "s|^DB_NAME=.*|DB_NAME=${DB_NAME}|" .env
    sed -i "s|^DB_USER=.*|DB_USER=${DB_USER}|" .env
    sed -i "s|^DB_PASS=.*|DB_PASS=${dbpass}|" .env
    # El servidor web (www-data) tiene que poder LEER el .env; con chmod 600
    # y owner root, la app no obtiene las credenciales de BD -> DB_ERROR.
    chown "${WEB_USER}:${WEB_USER}" .env
    chmod 640 .env
    warn ".env creado (DB_PASS autogenerada). FALTA completar a mano: credenciales de pago (Wompi) y SMTP/SMS."
  else
    ok ".env ya existe, no se toca"
  fi
}

# ── 4. Base de datos ────────────────────────────────────────────────────────
setup_database() {
  log "Creando base de datos ${DB_NAME}"
  local dbpass
  dbpass="$(grep -E '^DB_PASS=' "${APP_DIR}/.env" | cut -d= -f2-)"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${dbpass}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
  # Aplicar migraciones si la tabla principal aún no existe
  if ! mysql -u root "${DB_NAME}" -e "SELECT 1 FROM raffles LIMIT 1" >/dev/null 2>&1; then
    MYSQL_USER=root DB_NAME="${DB_NAME}" bash "${APP_DIR}/database/migrations/install.sh"
    ok "Migraciones aplicadas"
  else
    ok "El schema ya existe, no se reaplica"
  fi
}

# ── 5. Permisos ─────────────────────────────────────────────────────────────
setup_permissions() {
  log "Ajustando permisos"
  cd "${APP_DIR}"
  mkdir -p logs public/assets/uploads public/uploads uploads
  chown -R "${WEB_USER}:${WEB_USER}" logs public/assets/uploads public/uploads uploads
  chmod -R 755 logs public/assets/uploads public/uploads uploads
  ok "Permisos aplicados"
}

# ── 6. VirtualHost Apache ───────────────────────────────────────────────────
# DocumentRoot en la RAÍZ del proyecto (no public/): el .htaccess reescribe
# todo hacia public/index.php como front controller. AllowOverride All es
# imprescindible (sin él se ignoran .htaccess y el fix del header Authorization).
setup_apache() {
  log "Configurando VirtualHost para ${DOMAIN}"
  cat > "/etc/apache2/sites-available/${DOMAIN}.conf" <<CONF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias ${WWW_DOMAIN}
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/misrifas-error.log
    CustomLog \${APACHE_LOG_DIR}/misrifas-access.log combined
</VirtualHost>
CONF
  a2ensite "${DOMAIN}.conf" >/dev/null
  a2dissite 000-default.conf >/dev/null 2>&1 || true
  apache2ctl configtest
  systemctl reload apache2
  ok "VirtualHost activo"
}

# ── 7. HTTPS ────────────────────────────────────────────────────────────────
# HTTPS autofirmado en el origin: sirve para Cloudflare en modo "Full" (que
# acepta certificados autofirmados) y para tener 443 activo aunque el dominio
# esté detrás de un proxy y certbot no pueda validar por HTTP-01.
setup_https_selfsigned() {
  log "Configurando HTTPS autofirmado en el origin (para Cloudflare Full)"
  a2enmod ssl >/dev/null 2>&1
  if [ ! -f /etc/ssl/certs/misrifas-origin.crt ]; then
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
      -keyout /etc/ssl/private/misrifas-origin.key \
      -out /etc/ssl/certs/misrifas-origin.crt \
      -subj "/CN=${DOMAIN}/O=MisRifas" >/dev/null 2>&1
  fi
  cat > "/etc/apache2/sites-available/${DOMAIN}-ssl.conf" <<CONF
<VirtualHost *:443>
    ServerName ${DOMAIN}
    ServerAlias ${WWW_DOMAIN}
    DocumentRoot ${APP_DIR}
    <Directory ${APP_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/misrifas-origin.crt
    SSLCertificateKeyFile /etc/ssl/private/misrifas-origin.key
    ErrorLog \${APACHE_LOG_DIR}/misrifas-ssl-error.log
    CustomLog \${APACHE_LOG_DIR}/misrifas-ssl-access.log combined
</VirtualHost>
CONF
  a2ensite "${DOMAIN}-ssl.conf" >/dev/null
  apache2ctl configtest && systemctl reload apache2
  warn "Cert autofirmado. Para 'Full (strict)' de Cloudflare, reemplázalo por un"
  warn "Cloudflare Origin Certificate. Y abre el puerto 443 en el Security Group."
  ok "HTTPS (autofirmado) activo en :443"
}

setup_https() {
  if $SKIP_CERTBOT; then
    warn "Saltando certbot (--skip-certbot) — usando HTTPS autofirmado para Cloudflare"
    setup_https_selfsigned
    return
  fi
  log "Emitiendo certificado TLS con certbot"
  apt-get install -y certbot python3-certbot-apache
  if certbot --apache --non-interactive --agree-tos -m "${CERTBOT_EMAIL}" \
       -d "${DOMAIN}" -d "${WWW_DOMAIN}"; then
    ok "HTTPS con Let's Encrypt configurado"
  else
    warn "certbot falló (¿DNS de ${DOMAIN} apuntando a este VPS, o está tras un proxy?)"
    warn "Cayendo a HTTPS autofirmado para el origin"
    setup_https_selfsigned
  fi
}

# ── 8. Cron jobs ────────────────────────────────────────────────────────────
setup_cron() {
  log "Instalando cron jobs de la aplicación"
  local cronfile="/etc/cron.d/misrifas"
  cat > "$cronfile" <<CRON
# MisRifas — cron jobs (gestionado por install-vps.sh)
* * * * * ${WEB_USER} php ${APP_DIR}/cron/expire-reservations.php >> ${APP_DIR}/logs/cron.log 2>&1
0 * * * * ${WEB_USER} php ${APP_DIR}/cron/send_reminders.php >> ${APP_DIR}/logs/cron.log 2>&1
0 4 * * * ${WEB_USER} php ${APP_DIR}/cron/check_commissions.php >> ${APP_DIR}/logs/cron.log 2>&1
# Barrido nocturno: las loterías publican a horas DISTINTAS (10:30pm, 11pm,
# 11:30pm…). Ambos crons son idempotentes (fetch: ON DUPLICATE KEY; draws:
# solo rifas activas vencidas CON resultado verificado de hoy), así que se
# Corren TODO el día cada 15 min: el scraper decide por sí solo si a cada
# lotería ya le llegó su hora de sorteo (calendario administrable en el
# panel, con margen de 15 min) — una franja fija aquí mentiría en cuanto
# el admin cambiara un horario. Cuando no hay nada que hacer, salen en ms.
*/15 * * * * ${WEB_USER} php ${APP_DIR}/cron/fetch_lottery_results.php >> ${APP_DIR}/logs/cron.log 2>&1
5,20,35,50 * * * * ${WEB_USER} php ${APP_DIR}/cron/process_draws.php >> ${APP_DIR}/logs/cron.log 2>&1
*/10 * * * * ${WEB_USER} php ${APP_DIR}/cron/process_notifications.php >> ${APP_DIR}/logs/cron.log 2>&1
0 3 * * * root ${APP_DIR}/database/backup.sh >> ${APP_DIR}/logs/backup.log 2>&1
CRON
  chmod 644 "$cronfile"
  ok "Cron instalado en $cronfile"
}

# ── 9. Servidor de correo open-source: Postal (opcional) ────────────────────
# Postal es un servidor de correo self-hosted orientado a envío transaccional
# (como un Mailgun/SendGrid propio): expone SMTP + API + DKIM + tracking. Es la
# mejor opción para esta app, que solo ENVÍA. Corre en Docker.
install_mail() {
  log "Instalando servidor de correo Postal (Docker)"
  if ! command -v docker >/dev/null; then
    curl -fsSL https://get.docker.com | sh
  fi
  warn "Postal necesita configuración interactiva (dominio de correo, DKIM, DNS)."
  cat <<EOF

  Postal no se puede automatizar del todo sin decisiones de DNS. Pasos:
    1. git clone https://github.com/postalserver/install /opt/postal/install
    2. /opt/postal/install/bin/postal bootstrap ${DOMAIN}
    3. postal initialize && postal make-user
    4. En el panel de Postal: crear un "mail server", generar credenciales SMTP.
    5. En DNS de ${DOMAIN}: agregar los registros SPF, DKIM y DMARC que Postal indique.
    6. En ${APP_DIR}/.env:
         EMAIL_ENABLED=true
         SMTP_HOST=127.0.0.1
         SMTP_PORT=25            # o el puerto SMTP que exponga Postal
         SMTP_USER=<credencial>  SMTP_PASS=<credencial>
         EMAIL_FROM_ADDRESS=no-reply@${DOMAIN}
  Doc: https://docs.postalserver.io/  ·  Alternativa 1-binario: Stalwart (https://stalw.art)
EOF
  ok "Docker listo; sigue los pasos de Postal de arriba"
}

# ── 10. Gateway SMS open-source: Gammu SMSD (opcional) ──────────────────────
# El microservicio sms-service/ encola SMS en la bandeja de salida de Gammu.
# Gammu necesita un módem/dongle GSM USB con SIM colombiana.
install_sms() {
  log "Instalando Gammu SMSD"
  apt-get install -y gammu gammu-smsd
  cat <<EOF

  Gammu SMSD instalado. Pasos para dejarlo funcionando:
    1. Conecta el módem GSM USB y detéctalo:  gammu-detect  (anota el puerto, p.ej. /dev/ttyUSB0)
    2. Configura /etc/gammu-smsdrc con el device y el backend. Para usar el
       microservicio PHP con SQLite:
         [gammu]
         device = /dev/ttyUSB0
         connection = at
         [smsd]
         service = sql
         driver = sqlite3
         database = ${APP_DIR}/sms-service/gammu.db
    3. Inicializa la BD SQLite de Gammu (tablas outbox/inbox/sentitems):
         gammu-smsd-inject  # o importa el schema SQL de Gammu para sqlite
    4. Habilita el servicio:  systemctl enable --now gammu-smsd
    5. En ${APP_DIR}/.env:
         SMS_ENABLED=true
         GAMMU_SMSD_DB=${APP_DIR}/sms-service/gammu.db
  Alternativa con UI de gestión/bulk sobre Gammu: playSMS o Kalkun.
  Doc: https://wammu.eu/gammu/
EOF
  ok "Gammu SMSD instalado; completa la configuración del módem"
}

# ── Orquestación ────────────────────────────────────────────────────────────
install_packages
deploy_code
setup_env
setup_database
setup_permissions
setup_apache
setup_https
setup_cron
$WITH_MAIL && install_mail || true
$WITH_SMS && install_sms || true

log "Instalación base completada para ${DOMAIN}"
echo -e "  Revisa: \033[1m${APP_DIR}/.env\033[0m (DB_PASS, Wompi, SMTP/SMS)"
echo -e "  Verifica: curl -I https://${DOMAIN}  (debe responder 200/301)"
echo -e "  Checklist final: ver DEPLOY.md sección 8."
