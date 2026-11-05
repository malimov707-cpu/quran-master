#!/usr/bin/env bash
# Database backup — cron uchun: 0 3 * * * /var/www/html/scripts/backup.sh
# Retention: 14 kun. Restore: scripts/restore.sh <file>
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/www/html/storage/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILE="$BACKUP_DIR/db_$TIMESTAMP.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date -Is)] Backup boshlanmoqda..."

pg_dump --no-owner --no-privileges \
  -h "${DB_HOST:-db}" -U "${DB_USERNAME:-quran}" -d "${DB_DATABASE:-quran}" \
  | gzip > "$FILE"

# Butunlik tekshiruvi
if [ "$(gzip -t "$FILE" && echo ok)" != "ok" ]; then
  echo "[$(date -Is)] XATO: backup fayli buzilgan — o'chiriladi" >&2
  rm -f "$FILE"
  exit 1
fi

SIZE=$(du -h "$FILE" | cut -f1)
echo "[$(date -Is)] Backup tayyor: $FILE ($SIZE)"

# Eski backuplarni o'chirish
find "$BACKUP_DIR" -name 'db_*.sql.gz' -mtime "+$RETENTION_DAYS" -delete
echo "[$(date -Is)] $RETENTION_DAYS kundan eski backuplar o'chirildi."
