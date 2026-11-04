# Imagen de producción desde la rama deployment, donde public/build ya viene compilado.
FROM php:8.4-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APACHE_MAX_WORKERS=40

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql zip gd opcache pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/apache/app.conf /etc/apache2/conf-enabled/zz-app.conf

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

EXPOSE 80
ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]