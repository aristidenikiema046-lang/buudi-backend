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

# Publie les assets front des packages Composer (Filament, Livewire) dans
# public/ avant la copie figee vers public-dist ci-dessous : sinon absents de
# l'image, comme c'etait le cas pour Livewire (jamais publie => livewire.min.js
# en 404 en prod, corrige a la main sur le conteneur puis perdu au rebuild
# suivant faute d'etre dans le process de build).
RUN php artisan filament:assets \
    && php artisan livewire:publish --assets --no-interaction

# Copie figee de public/ (assets Vite compiles inclus), en dehors du chemin
# ./public : au demarrage, le service "app" partage ./public avec nginx via un
# volume nomme, qui sinon garderait le contenu perime d'un deploiement
# precedent au lieu de celui de cette image. Voir docker/entrypoint.sh.
RUN php artisan filament:assets \
    && php artisan livewire:publish --assets --no-interaction

RUN cp -a public public-dist \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache public-dist

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
