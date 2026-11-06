#!/bin/sh
set -eu

php artisan config:cache
php artisan migrate --force --no-interaction

if [ "${1:-}" = "queue" ]; then
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=300 --max-jobs=500
fi

if [ "${1:-}" = "schedule" ]; then
    exec php artisan schedule:work
fi

exec php artisan serve --host=0.0.0.0 --port=8000
