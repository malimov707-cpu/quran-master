# Web Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
php artisan migrate
php artisan serve
```

The Blade application is responsive and uses Laravel session authentication for browser users and Sanctum for API/mobile clients.
