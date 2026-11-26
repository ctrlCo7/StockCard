# Production image for the Stock Card & Inventory Management System.
# PHP 8.4 + Nginx (serversideup/php), serving on port 8080 inside the container.
FROM serversideup/php:8.4-fpm-nginx

# GD is needed for images in PDFs (signatures) and is not in the base image.
USER root
RUN install-php-extensions gd
USER www-data

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true

WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached between code changes.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
