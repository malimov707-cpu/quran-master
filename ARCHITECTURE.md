# Architecture

## Runtime decision

The implemented product is a Laravel 13 modular monolith rather than the proposed Spring Boot stack. The repository already contains the Laravel foundation, migrations, web UI, API and mobile client; replacing it with Java would discard working code. A future Java migration must preserve the contracts below.

## Components

- Laravel API: `/api/v1`, Sanctum authentication
- Blade web application
- Expo React Native application
- PostgreSQL for production relational data
- Redis for cache, sessions and queues
- S3-compatible object storage for audio and documents
- AI providers behind replaceable application interfaces

## Trust boundaries

- All authorization decisions are enforced in the Laravel backend.
- Browser/mobile clients only present the resulting UI; they do not own business rules.
- User-supplied local dates use `APP_DISPLAY_TIMEZONE`. Database timestamps use UTC.
- Qur'an source text is imported through the verified import command and is not administrator-editable.
- AI output is advisory and separated from mentor-confirmed review.

## Deployment topology

Nginx proxies web/API requests to PHP-FPM-compatible application processes. PostgreSQL, Redis and MinIO are separate stateful services. Background jobs must run on dedicated workers.
