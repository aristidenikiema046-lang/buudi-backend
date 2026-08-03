#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY manquant : genere-le en local avec 'php artisan key:generate --show' et ajoute-le dans .env sur le serveur." >&2
    exit 1
fi

# Seul le service "app" (CMD php-fpm) sert le repertoire public/ partage avec
# nginx via le volume nomme "public_data". On le resynchronise a chaque
# demarrage depuis la copie figee dans l'image (public-dist) : sinon un
# volume deja peuple par un deploiement precedent garderait des assets Vite
# et un lien storage/ perimes au lieu du contenu de cette image.
# queue/scheduler ne montent pas ce volume et n'en ont pas besoin.
if [ "$1" = "php-fpm" ]; then
    find /var/www/html/public -mindepth 1 -delete
    cp -a /var/www/html/public-dist/. /var/www/html/public/
    chown -R www-data:www-data /var/www/html/public
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force

if [ "$1" = "php-fpm" ]; then
    php artisan storage:link
fi

exec "$@"
