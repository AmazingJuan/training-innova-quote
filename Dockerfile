# Imagen de producción desde la rama deployment, donde public/build ya viene compilado.
#
# FrankenPHP sobre Alpine: PHP 8.4 embebido en un servidor web (Caddy). Ocupa
# ~300 MB en disco, frente a ~800 MB de php:8.4-apache sobre Debian.
FROM dunglas/frankenphp:1.13.5-php8.4-alpine

# OPcache ya viene en la imagen. curl: healthcheck del contenedor. su-exec: el
# servidor web corre como www-data después de que el arranque (root) ajusta permisos.
RUN install-php-extensions pdo_mysql zip gd pcntl \
    && apk add --no-cache curl su-exec \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && chown -R www-data:www-data /config /data

WORKDIR /var/www/html

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database/migrations ./database/migrations
COPY database/seeders ./database/seeders
COPY public ./public
COPY resources/views ./resources/views
COPY routes ./routes
COPY vendor ./vendor
COPY artisan composer.json composer.lock ./
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/app-entrypoint

# La imagen base revisa la API de administración de Caddy, que aquí está apagada.
# El healthcheck de app lo define docker-compose.yml; worker y scheduler no sirven HTTP.
HEALTHCHECK NONE

EXPOSE 8080
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
