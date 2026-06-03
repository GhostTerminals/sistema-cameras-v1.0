#!/usr/bin/env bash
set -euo pipefail

RUN_BACKUP_TEST="${RUN_BACKUP_TEST:-1}"
BACKUP_SCRIPT="${BACKUP_SCRIPT:-/usr/local/bin/backup_sistema_cameras.sh}"
BACKUP_MOUNT="${BACKUP_MOUNT:-/backup}"
BACKUP_DIR="${BACKUP_DIR:-/backup/sistema_cameras}"
BACKUP_LOG="${BACKUP_LOG:-/var/log/backup_sistema_cameras.log}"

pass() { echo "[OK]   $1"; }
fail() { echo "[ERRO] $1"; exit 1; }

echo "=== TESTE + AUDITORIA (STRICT) ==="

# 0) Executar backup de teste (opcional)
[[ -x "$BACKUP_SCRIPT" ]] || fail "Script de backup ausente/sem permissao: $BACKUP_SCRIPT"
if [[ "$RUN_BACKUP_TEST" == "1" ]]; then
  if "$BACKUP_SCRIPT"; then
    pass "Backup de teste executado"
  else
    [[ -f "$BACKUP_LOG" ]] && tail -n 80 "$BACKUP_LOG" || true
    fail "Backup de teste falhou"
  fi
else
  pass "Execucao de backup de teste ignorada (RUN_BACKUP_TEST=0)"
fi

# 1) Montagem
findmnt "$BACKUP_MOUNT" >/dev/null 2>&1 || fail "$BACKUP_MOUNT nao montado"
pass "$BACKUP_MOUNT montado"

# 2) fstab
grep -qE "[[:space:]]${BACKUP_MOUNT}[[:space:]]+ext4[[:space:]]" /etc/fstab || fail "/etc/fstab sem entrada valida para $BACKUP_MOUNT"
pass "fstab ok"

# 3) Diretorio e permissoes
[[ -d "$BACKUP_DIR" ]] || fail "Diretorio nao existe: $BACKUP_DIR"
perm="$(stat -c "%a %U:%G" "$BACKUP_DIR")"
[[ "$perm" == "700 root:root" ]] || fail "Permissao inesperada em $BACKUP_DIR: $perm"
pass "Permissoes do destino ok"

# 4) Cron
crontab -l 2>/dev/null | grep -q "backup_sistema_cameras.sh" || fail "Cron de backup nao configurado"
pass "Cron configurado"

# 5) Artefatos recentes
latest_sql="$(ls -1t "$BACKUP_DIR"/db_*.sql 2>/dev/null | head -n1 || true)"
latest_tar="$(ls -1t "$BACKUP_DIR"/uploads_*.tar.gz 2>/dev/null | head -n1 || true)"
latest_sum="$(ls -1t "$BACKUP_DIR"/checksum_*.sha256 2>/dev/null | head -n1 || true)"

[[ -n "$latest_sql" ]] || fail "Sem dump SQL"
[[ -n "$latest_tar" ]] || fail "Sem backup fotos"
[[ -n "$latest_sum" ]] || fail "Sem checksum"
pass "Arquivos de backup encontrados"

# 6) Checksum
(
  cd "$BACKUP_DIR"
  sha256sum -c "$(basename "$latest_sum")" >/tmp/backup_checksum.out 2>&1
) || {
  sed -n "1,40p" /tmp/backup_checksum.out || true
  fail "Checksum invalido"
}
pass "Checksum validado"

# 7) Log
[[ -f "$BACKUP_LOG" ]] || fail "Log de backup nao encontrado: $BACKUP_LOG"
pass "Log encontrado"
tail -n 20 "$BACKUP_LOG"

echo "=== SUCESSO TOTAL ==="
