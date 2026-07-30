#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY manquant : genere-le en local avec 'php artisan key:generate --show' et ajoute-le dans .env sur le serveur." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force

php artisan storage:link || true

exec "$@"
