# Production Deployment Guide

1. Provision PostgreSQL 17, Redis 7, private S3-compatible storage, HTTPS reverse proxy, and DNS.
2. Create secrets outside Git. Do not use Compose's development passwords or sample APP_KEY in production.
3. Copy `.env.example`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain`, database credentials, Redis credentials, S3 credentials, and backup disk credentials.
4. Run `docker compose build app worker nginx`.
5. Start PostgreSQL, Redis, and MinIO/S3.
6. Run `php artisan migrate --force`.
7. Run required verified imports and storage permission setup.
8. Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
9. Start app, queue worker, scheduler, and Nginx.
10. Verify `/api/v1/health`, application login, HTTPS, private file access, queue processing, and scheduled tasks.
11. Perform smoke, backup, and restore drills before public launch.

See `docker-compose.yml`, `Dockerfile`, and `docker/nginx/default.conf`.
