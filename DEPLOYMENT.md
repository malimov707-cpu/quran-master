# Deployment

## Docker

```sh
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
```

Set unique production secrets for `APP_KEY`, database credentials, object storage and external providers. TLS termination should be added through the public reverse proxy.

## Release order

1. Back up the database.
2. Build and push immutable images.
3. Run migrations in maintenance mode.
4. Deploy application workers and queue workers.
5. Run health and smoke tests.
6. Enable external traffic.


## Backup va Restore

- **Avtomatik backup:** docker compose'da `backup` servisi har kuni soat 03:00 da `pg_dump` ishga tushiradi (gzip, SHA butunlik tekshiruvi bilan). Fayllar `backups:` volumeda, 14 kun retention (`BACKUP_RETENTION_DAYS`).
- **Qo'lda backup:** `docker compose exec backup /scripts/backup.sh`
- **Restore:** `docker compose exec app php scripts/restore.sh /backups/db_YYYYMMDD_HHMMSS.sql.gz` — DIQQAT: baza to'liq almashtiriladi, avval `docker compose down app worker` qiling.
- Backuplarni ofsite saqlash: `backups` katalogini S3/glkash ga sinxronlash tavsiya etiladi (`rclone sync backups remote:quran-backups`).
- Restore'dan keyin: `php artisan migrate --force`, keyin health check `/up`.
