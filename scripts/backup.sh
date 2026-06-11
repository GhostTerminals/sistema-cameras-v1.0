#!/bin/bash
set -euo pipefail

BACKUP_DIR="/opt/backups"
RETENTION_DAYS=7
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${BACKUP_DIR}/backup.log"

mkdir -p "${BACKUP_DIR}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG_FILE}"
}

log "=== Iniciando backup ==="

# MySQL dump cameras
log "Dump cameras-db..."
docker exec cameras-db mysqldump -u root -pBXvqILfsqXV3q7zbL2J9g2DpStzoUPpi \
  --all-databases --single-transaction --routines --triggers --events 2>/dev/null | \
  gzip > "${BACKUP_DIR}/cameras-db_${DATE}.sql.gz"

# MySQL dump visitantes
log "Dump visitantes-db..."
docker exec visitantes-db mysqldump -u root -pSGVUUmBV1e0ztGTqVeZxqqQCSpAySGcU \
  --all-databases --single-transaction --routines --triggers --events 2>/dev/null | \
  gzip > "${BACKUP_DIR}/visitantes-db_${DATE}.sql.gz"

# Volume fotos_visitantes
log "Backup fotos_visitantes..."
docker run --rm \
  -v sistema-visitantes-v10_fotos_visitantes:/data \
  -v "${BACKUP_DIR}:/backup" \
  alpine tar czf "/backup/fotos_visitantes_${DATE}.tar.gz" -C /data .

# Cleanup old backups
log "Removendo backups com mais de ${RETENTION_DAYS} dias..."
find "${BACKUP_DIR}" \( -name "*.sql.gz" -o -name "*.tar.gz" \) -type f -mtime +${RETENTION_DAYS} -delete -print

log "=== Backup concluido ==="
echo "Backup salvo em: ${BACKUP_DIR}"
