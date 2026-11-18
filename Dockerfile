# syntax=docker/dockerfile:1
#
# Imagen de producción: Laravel (PHP 8.4 en FrankenPHP, sobre Alpine) con el frontend React ya compilado.
# La usan los servicios app, worker y scheduler de docker-compose.yml.

# ---------------------------------------------------------------------------
# 1. Base PHP con las extensiones que necesita el proyecto
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1.13.5-php8.4-alpine AS base

# OPcache ya viene en la imagen. curl: healthcheck. su-exec: Caddy corre como www-data.
RUN install-php-extensions pdo_mysql zip gd pcntl \
    && apk add --no-cache curl su-exec git unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# 2. Dependencias PHP (capa aparte para aprovechar la caché de Docker)
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# ---------------------------------------------------------------------------
# 3. Frontend: compila React/Vite a public/build
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# tsconfig.json resuelve "ziggy-js" desde vendor/tightenco/ziggy.
COPY --from=vendor /var/www/html/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
COPY resources ./resources
COPY vite.config.js tsconfig.json tailwind.config.js postcss.config.js components.json ./

ARG VITE_APP_NAME="Training Corporation"
ENV VITE_APP_NAME=${VITE_APP_NAME}
RUN npm run build

# ---------------------------------------------------------------------------
# 4. Imagen final
# ---------------------------------------------------------------------------
FROM base AS app

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && chown -R www-data:www-data /config /data

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

# La imagen base revisa la API de administración de Caddy, que aquí está apagada.
# El healthcheck de app lo define docker-compose.yml; worker y scheduler no sirven HTTP.
HEALTHCHECK NONE

EXPOSE 8080
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
