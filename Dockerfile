# syntax=docker/dockerfile:1
#
# The previous build used a blanket `--ignore-platform-reqs`, which hid two
# real, unrelated problems (found by actually building this image, not by
# guessing):
#   1. mongodb/laravel-mongodb and REDIS_CLIENT=phpredis need the native
#      ext-mongodb/ext-redis PHP extensions, which the generic `composer:2`
#      builder image doesn't have. Fixed below with a *scoped* ignore for
#      just those two extensions during dependency resolution — the runtime
#      stage compiles them in for real, so the app has them when it matters.
#   2. composer.lock's resolved package versions actually need PHP >= 8.4.1
#      (composer.json's own "^8.3" undersells it) — confirmed by the runtime
#      stage's own `vendor/composer/platform_check.php` refusing to boot on
#      8.3.32. Fixed by running PHP 8.4 below instead of 8.3.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader \
        --ignore-platform-req=ext-mongodb --ignore-platform-req=ext-redis
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------- 2. Build frontend assets ----------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# ---------- 3. Runtime image ----------
FROM php:8.4-cli-alpine
WORKDIR /var/www/html

# mongodb/laravel-mongodb needs the native mongodb extension; REDIS_CLIENT=phpredis
# needs the native redis extension; pdo_pgsql needs libpq. All compiled here so
# nothing falls back to a broken/missing-extension state at runtime.
RUN apk add --no-cache libpq libzip icu-libs oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_pgsql zip intl bcmath opcache \
    && pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

# APP_KEY, DB_*, MONGODB_*, REDIS_* etc. are expected to be supplied as
# environment variables by the hosting platform (Dokploy), not baked into the
# image. Migrations run on every boot — safe/idempotent, no seeding here.
CMD ["sh", "-c", "php artisan storage:link --force && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=8000"]
