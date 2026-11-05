FROM php:8.4-cli AS php-base

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl unzip libpng-dev libonig-dev libxml2-dev libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql bcmath mbstring opcache pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS dependencies

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

FROM php-base AS production

COPY --chown=www-data:www-data . .
COPY --from=dependencies /var/www/html/vendor /var/www/html/vendor
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/quran-entrypoint
RUN chmod +x /usr/local/bin/quran-entrypoint

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["/usr/local/bin/quran-entrypoint"]
