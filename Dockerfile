# ---- Stage 1: build des assets front (Vite) ----
FROM node:20-alpine AS assets
WORKDIR /var/www/html
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

# ---- Stage 2: dependances PHP (composer) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---- Stage 3: image finale PHP-FPM ----
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        libpq \
        libzip \
        icu-libs \
        oniguruma \
        $PHPIZE_DEPS \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        zip \
        intl \
        opcache \
    && apk del $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev oniguruma-dev

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /var/www/html/public/build ./public/build

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
