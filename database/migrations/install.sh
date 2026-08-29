#!/usr/bin/env bash
# Reconstruye la base de datos de MisRifas desde cero, aplicando las
# migraciones en el UNICO orden que produce el schema real del proyecto.
#
# Uso:
#   ./install.sh                       # usa mysql -u root sin password
#   MYSQL_USER=foo MYSQL_PWD=bar ./install.sh
#   DB_NAME=misrifas_test ./install.sh # instalar en otra BD (ej. para pruebas)
#
# El script hace DROP + CREATE de la base de datos indicada antes de aplicar
# las migraciones. NO tiene guardas de "estás seguro" a propósito: está
# pensado para entornos de desarrollo/CI, no para correrlo a ciegas contra
# producción. En producción, usar mysqldump antes si hay datos reales.

set -euo pipefail

DB_NAME="${DB_NAME:-misrifas}"
MYSQL_USER="${MYSQL_USER:-root}"
# --default-character-set=utf8mb4 es obligatorio: el cliente mysql en
# Windows arranca en cp850 (codepage de consola) por defecto, no utf8mb4.
# Sin esto, cada archivo .sql (que SI esta en UTF-8 real) se reinterpreta
# como cp850 al importarse y cada tilde/enie queda corrupta en la BD
# ("Loter├¡a" en vez de "Lotería") - la conexion de la app (PDO, que si
# fuerza utf8mb4) nunca ve el problema porque el dano ya esta hecho en los
# bytes guardados.
MYSQL_ARGS=(-u "$MYSQL_USER" --default-character-set=utf8mb4)
if [ -n "${MYSQL_PWD:-}" ]; then
  MYSQL_ARGS+=(-p"$MYSQL_PWD")
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Orden real de aplicación (ver README.md de este directorio para el detalle
# de qué crea cada archivo). Los archivos en legacy_archive/ NO se aplican:
# son duplicados o incompatibles con este orden.
MIGRATIONS=(
  "000_setup_completo_legacy.sql"
  "v2.1_fix_schema.sql"
  "v3.0_saas_multi_vendor.sql"
  "v3.1_pagos_transaccionales.sql"
  "v3.2_buyer_auth_columns.sql"
  "v3.3_whatsapp_engine.sql"
  "v3.4_fix_created_by_fk.sql"
  "v3.5_pago_modo_default.sql"
  "v3.6_commission_recalc_on_update.sql"
  "v3.7_invalidate_legacy_tokens.sql"
)

echo "==> Recreando base de datos '$DB_NAME'"
mysql "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

for file in "${MIGRATIONS[@]}"; do
  path="$SCRIPT_DIR/$file"
  if [ ! -f "$path" ]; then
    echo "!! Falta $file en $SCRIPT_DIR" >&2
    exit 1
  fi
  echo "==> Aplicando $file"
  mysql "${MYSQL_ARGS[@]}" "$DB_NAME" < "$path"
done

echo "==> Listo. Tablas creadas:"
mysql "${MYSQL_ARGS[@]}" "$DB_NAME" -e "SHOW TABLES;"
