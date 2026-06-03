#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-./backups}"
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-cftv_gml}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_PATH="${BACKUP_DIR}/${DB_NAME}"
mkdir -p "$BACKUP_PATH"

FILENAME="${DB_NAME}-${TIMESTAMP}.sql"
FILEPATH="${BACKUP_PATH}/${FILENAME}"

echo "[Backup] Starting backup of ${DB_NAME}..."

MYSQL_PWD="${DB_PASS}" mysqldump \
    --host="${DB_HOST}" \
    --user="${DB_USER}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --databases "${DB_NAME}" > "$FILEPATH"

echo "[Backup] Saved: ${FILEPATH} ($(du -h "$FILEPATH" | cut -f1))"

gzip -f "$FILEPATH"
echo "[Backup] Compressed: ${FILEPATH}.gz"

find "$BACKUP_PATH" -name "*.sql.gz" -mtime +${RETENTION_DAYS} -delete
echo "[Backup] Old backups (>${RETENTION_DAYS}d) cleaned up"
echo "[Backup] Done!"
