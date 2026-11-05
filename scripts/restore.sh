#!/usr/bin/env bash
# Restore: scripts/restore.sh storage/backups/db_YYYYMMDD_HHMMSS.sql.gz
# DIQQAT: mavjud ma'lumotlar ALMASHTIRILADI. Production'da avval down qiling.
set -euo pipefail

FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "Foydalanish: $0 <backup_fayli.sql.gz>" >&2
  exit 1
fi

echo "[$(date -Is)] RESTORE boshlanmoqda: $FILE"
echo "Ma'lumotlar bazasi to'liq almashtiriladi. 5 soniya ichida Ctrl+C bilan bekor qiling..."
sleep 5

gunzip -c "$FILE" | psql \
  -h "${DB_HOST:-db}" -U "${DB_USERNAME:-quran}" -d "${DB_DATABASE:-quran}"

echo "[$(date -Is)] Restore tugadi. Ilova holatini tekshiring: /api/v1/health (yoki /up)."
