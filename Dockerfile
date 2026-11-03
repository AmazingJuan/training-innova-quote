# syntax=docker/dockerfile:1
#
# Imagen de producción: Laravel (PHP 8.4 + Apache) con el frontend React ya compilado.
# La usan los servicios app, worker y scheduler de docker-compose.yml.

# ---------------------------------------------------------------------------
# 1. Base PHP con las extensiones que necesita el proyecto
# ---------------------------------------------------------------------------
FROM php:8.4-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl git unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql zip gd opcache pcntl \
    && rm -rf /var/lib/apt/lists/*

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

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APACHE_MAX_WORKERS=40

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/apache/app.conf /etc/apache2/conf-enabled/zz-app.conf

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 80
ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
