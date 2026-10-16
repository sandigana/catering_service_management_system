# Dockerfile
# Production image for Render: builds Vite assets, installs PHP deps, serves Laravel with Apache.

# ---- Stage 1: front-end assets (Vite + Tailwind) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- Stage 2: PHP dependencies (no dev packages) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

# ---- Stage 3: runtime ----
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql pgsql bcmath opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Apache serves /public and listens on Render's $PORT (10000 by default).
ENV PORT=10000 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri 's/Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername
COPY docker/apache.conf /etc/apache2/conf-available/laravel.conf
RUN a2enconf laravel

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Re-generate the optimized autoloader and discover packages.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer dump-autoload --no-dev --optimize --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000
CMD ["sh", "docker/start.sh"]
