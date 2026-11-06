# Backup and Restore Guide

## Backup

The PostgreSQL-only commands are:

```bash
php artisan database:backup --disk=backups
php artisan database:restore <filename> --disk=backups --confirm=RESTORE
```

`database:backup` requires PostgreSQL, `pg_dump`, object storage, network access, and configured backup credentials. Restore requires explicit confirmation and PostgreSQL/`psql`.

## Restore procedure

1. Isolate the target environment.
2. Verify backup name and checksum.
3. Create a fresh database or restore environment.
4. Restore the dump.
5. Run `php artisan migrate --force`.
6. Verify row counts, authentication, Quran integrity, and critical relationships.
7. Run application health and login checks.
8. Promote only after smoke tests pass.

## Current status

Commands are implemented. A real backup/restore rehearsal is BLOCKED until production-compatible PostgreSQL, backup storage, and credentials exist.
