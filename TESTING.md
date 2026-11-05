# Testing

Run the backend suite:

```sh
php artisan test
```

Run formatting:

```sh
vendor/bin/pint
```

Run mobile type checks:

```sh
cd mobile
npx tsc --noEmit
```

Release acceptance requires backend feature tests, permission tests, mobile API tests, real-device audio tests, database backup/restore, health checks and an end-to-end role workflow.
