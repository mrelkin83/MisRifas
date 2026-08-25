#!/usr/bin/env bash
# Backup de la base de datos de MisRifas — plataforma de pagos reales,
# no tener esto en producción era el hallazgo CRÍTICO de la auditoría de
# Fase 4 (hardening VPS): sin esto, un DROP TABLE accidental o una fila
# corrupta significa perder ventas/boletos reales sin forma de recuperarlos.
#
# Uso:
#   ./backup.sh                    # lee credenciales de .env en la raíz del proyecto
#   DB_NAME=foo DB_USER=bar ./backup.sh   # override manual
#
# Pensado para correr por cron en el VPS, ej. diario a las 3am:
#   0 3 * * * /ruta/al/proyecto/database/backup.sh >> /ruta/al/proyecto/logs/backup.log 2>&1
#
# Guarda los dumps comprimidos en database/backups/ (gitignored) y borra
# los que tengan más de RETENTION_DAYS días (default 14) para no llenar
# el disco del VPS indefinidamente.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
BACKUP_DIR="$SCRIPT_DIR/backups"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

# Cargar .env si las variables no vienen ya seteadas en el entorno
if [ -f "$ENV_FILE" ]; then
  set -a
  # shellcheck disable=SC1090
  source <(grep -v '^\s*#' "$ENV_FILE" | grep '=')
  set +a
fi

DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
  echo "!! Falta DB_NAME/DB_USER (revisa .env o exporta las variables antes de correr el script)" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DUMP_FILE="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.sql.gz"

MYSQLDUMP_ARGS=(-h "$DB_HOST" -u "$DB_USER" --single-transaction --quick --routines --triggers --default-character-set=utf8mb4)
if [ -n "$DB_PASS" ]; then
  MYSQLDUMP_ARGS+=(-p"$DB_PASS")
fi

echo "==> Volcando '$DB_NAME' a $DUMP_FILE"
mysqldump "${MYSQLDUMP_ARGS[@]}" "$DB_NAME" | gzip > "$DUMP_FILE"

SIZE="$(du -h "$DUMP_FILE" | cut -f1)"
echo "==> Backup completo ($SIZE)"

echo "==> Borrando backups de más de $RETENTION_DAYS días"
find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -print -delete

echo "==> Backups actuales:"
ls -lh "$BACKUP_DIR"/*.sql.gz 2>/dev/null || echo "(ninguno todavía)"
