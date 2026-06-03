#!/usr/bin/env bash
set -euo pipefail

# ===== CONFIG =====
BACKUP_DEV="${1:-/dev/sdb1}"            # ex: /dev/sdb1
BACKUP_MOUNT="/backup"
BACKUP_DIR="${BACKUP_MOUNT}/sistema_cameras"
APP_DIR="/var/www/sistema-cameras-v1.0"

DB_NAME="cftv_gml"
DB_USER="gmluser"
DB_PASS="${CAMERAS_DB_PASS:-SENHA_FORTE_AQUI}"   # export CAMERAS_DB_PASS antes de executar

RETENTION_DAYS=30
CRON_SCHEDULE="0 2 * * *"               # diario as 02:00
FORMAT_DISK="${FORMAT_DISK:-0}"         # 1 = formatar disco
FORCE_FORMAT="${FORCE_FORMAT:-0}"       # 1 = pular confirmacao
TEST_RESTORE="${TEST_RESTORE:-0}"       # 1 = testar restore (exige permissao para CREATE/DROP DATABASE)
# ==================

if [[ $EUID -ne 0 ]]; then
  echo "Execute como root: sudo bash $0 /dev/sdb1"
  exit 1
fi

if [[ ! -b "$BACKUP_DEV" ]]; then
  echo "Dispositivo nao encontrado: $BACKUP_DEV"
  exit 1
fi

if [[ "$DB_PASS" == "SENHA_FORTE_AQUI" || -z "$DB_PASS" ]]; then
  echo "Defina a senha do banco via variavel CAMERAS_DB_PASS antes de executar."
  echo "Exemplo: export CAMERAS_DB_PASS='sua_senha_forte'"
  exit 1
fi

# Evita usar o mesmo disco do sistema por engano.
ROOT_SOURCE="$(findmnt -no SOURCE / || true)"
if [[ -n "$ROOT_SOURCE" && "$BACKUP_DEV" == "$ROOT_SOURCE" ]]; then
  echo "ERRO: BACKUP_DEV aponta para o disco raiz do sistema ($ROOT_SOURCE)."
  exit 1
fi

echo "[1/8] Preparando dispositivo $BACKUP_DEV..."
if [[ "$FORMAT_DISK" == "1" ]]; then
  if [[ "$FORCE_FORMAT" != "1" ]]; then
    echo "ATENCAO: isto vai APAGAR TODOS os dados de $BACKUP_DEV."
    read -r -p "Confirmar formatacao? (digite SIM): " CONFIRM
    [[ "$CONFIRM" == "SIM" ]] || { echo "Abortado."; exit 1; }
  fi
  mkfs.ext4 -F "$BACKUP_DEV"
else
  if ! blkid "$BACKUP_DEV" >/dev/null 2>&1; then
    echo "Dispositivo sem filesystem. Para formatar, rode com FORMAT_DISK=1."
    exit 1
  fi
fi

echo "[2/8] Criando ponto de montagem..."
mkdir -p "$BACKUP_MOUNT"

echo "[3/8] Obtendo UUID..."
UUID="$(blkid -s UUID -o value "$BACKUP_DEV" || true)"
[[ -n "$UUID" ]] || { echo "Falha ao obter UUID de $BACKUP_DEV"; exit 1; }

echo "[4/8] Configurando /etc/fstab..."
if ! grep -qE "^[#[:space:]]*UUID=${UUID}[[:space:]]+${BACKUP_MOUNT}[[:space:]]" /etc/fstab; then
  echo "UUID=$UUID  $BACKUP_MOUNT  ext4  defaults,nofail  0  2" >> /etc/fstab
fi

echo "[5/8] Montando disco..."
mount "$BACKUP_MOUNT" || true
mountpoint -q "$BACKUP_MOUNT" || { echo "Falha: $BACKUP_MOUNT nao montado"; exit 1; }

echo "[6/8] Ajustando permissoes..."
mkdir -p "$BACKUP_DIR"
chown root:root "$BACKUP_MOUNT" "$BACKUP_DIR"
chmod 700 "$BACKUP_MOUNT" "$BACKUP_DIR"

echo "[7/8] Criando script de backup..."
cat > /usr/local/bin/backup_sistema_cameras.sh <<EOF
#!/usr/bin/env bash
set -euo pipefail
umask 077

BACKUP_MOUNT="$BACKUP_MOUNT"
DEST="$BACKUP_DIR"
APP_DIR="$APP_DIR"
DB_NAME="$DB_NAME"
DB_USER="$DB_USER"
DB_PASS='$DB_PASS'
RETENTION_DAYS="$RETENTION_DAYS"
TEST_RESTORE="$TEST_RESTORE"
DATA=\$(date +%F_%H-%M)
DB_FILE="\$DEST/db_\$DATA.sql"
UPLOADS_FILE="\$DEST/uploads_\$DATA.tar.gz"
SUM_FILE="\$DEST/checksum_\$DATA.sha256"

mountpoint -q "\$BACKUP_MOUNT" || { echo "ERRO: \$BACKUP_MOUNT nao montado"; exit 1; }
mkdir -p "\$DEST"

# 1) Backup
mysqldump --single-transaction --routines --triggers -u "\$DB_USER" -p"\$DB_PASS" "\$DB_NAME" > "\$DB_FILE"
tar -czf "\$UPLOADS_FILE" -C "\$APP_DIR" public/uploads
sha256sum "\$DB_FILE" "\$UPLOADS_FILE" > "\$SUM_FILE"

# 2) Teste opcional de restore em banco temporario
if [[ "\$TEST_RESTORE" == "1" ]]; then
  TMP_DB="\${DB_NAME}_restore_test"
  mysql -u "\$DB_USER" -p"\$DB_PASS" -e "DROP DATABASE IF EXISTS \\\`\$TMP_DB\\\`;"
  mysql -u "\$DB_USER" -p"\$DB_PASS" -e "CREATE DATABASE \\\`\$TMP_DB\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -u "\$DB_USER" -p"\$DB_PASS" "\$TMP_DB" < "\$DB_FILE"

  TABLES=\$(mysql -N -s -u "\$DB_USER" -p"\$DB_PASS" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='\$TMP_DB';")
  [[ "\$TABLES" -ge 1 ]] || { echo "ERRO: restore de teste sem tabelas."; exit 1; }

  mysql -u "\$DB_USER" -p"\$DB_PASS" -e "DROP DATABASE IF EXISTS \\\`\$TMP_DB\\\`;"
  echo "OK: backup + restore teste concluido em \$DATA (tabelas restauradas: \$TABLES)"
else
  echo "OK: backup concluido em \$DATA"
fi

# 3) Retencao
find "\$DEST" -maxdepth 1 -type f \\( -name 'db_*.sql' -o -name 'uploads_*.tar.gz' -o -name 'checksum_*.sha256' \\) -mtime +\$RETENTION_DAYS -delete
EOF

chmod 700 /usr/local/bin/backup_sistema_cameras.sh

echo "[8/8] Configurando cron root..."
TMP_CRON="$(mktemp)"
crontab -l 2>/dev/null | grep -v backup_sistema_cameras.sh > "$TMP_CRON" || true
echo "$CRON_SCHEDULE /usr/local/bin/backup_sistema_cameras.sh >> /var/log/backup_sistema_cameras.log 2>&1" >> "$TMP_CRON"
crontab "$TMP_CRON"
rm -f "$TMP_CRON"

echo
echo "Concluido."
echo "Teste agora:"
echo "  sudo /usr/local/bin/backup_sistema_cameras.sh"
echo "Verifique:"
echo "  ls -lh $BACKUP_DIR"
echo "  tail -n 100 /var/log/backup_sistema_cameras.log"
