#!/bin/sh
# Arranque de los contenedores app, worker y scheduler.
#   CONTAINER_ROLE=app       ejecuta las migraciones antes de iniciar el servidor web (FrankenPHP).
#   CONTAINER_ROLE=worker|scheduler solo prepara las cachés.
set -e

cd /var/www/html

mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    php artisan migrate --force
fi

php artisan optimize

# El volumen storage lo comparten los tres contenedores; el servidor web, el
# worker y el scheduler escriben en él como www-data.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
    # El servidor web no necesita root: corre como www-data.
    exec su-exec www-data "$@"
fi

exec "$@"
