#!/usr/bin/env bash
set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <backup-file.sql.gz|backup-file.sql>"
    exit 1
fi

BACKUP_FILE="$1"
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "[ERROR] File not found: $BACKUP_FILE"
    exit 1
fi

echo "[Restore] This will OVERWRITE the database!"
read -rp "Continue? (y/N) " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    echo "[Restore] Cancelled."
    exit 0
fi

if [[ "$BACKUP_FILE" == *.gz ]]; then
    echo "[Restore] Decompressing..."
    gunzip -c "$BACKUP_FILE" | MYSQL_PWD="${DB_PASS}" mysql --host="${DB_HOST}" --user="${DB_USER}"
else
    MYSQL_PWD="${DB_PASS}" mysql --host="${DB_HOST}" --user="${DB_USER}" < "$BACKUP_FILE"
fi

echo "[Restore] Done! Database restored from $BACKUP_FILE"
