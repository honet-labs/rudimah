# Multi-stage Dockerfile untuk Platform RUDIMAH
# Stage 1: Build Frontend Assets
FROM node:22-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm install --no-audit
COPY . .
RUN npm run build

# Stage 2: PHP Application Runner
FROM php:8.3-fpm-alpine

# Install System Dependencies & PHP Extensions
RUN apk add --no-cache \
    nginx \
    mariadb-client \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    icu-dev \
    oniguruma-dev \
    supervisor \
    bash \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    intl \
    bcmath \
    opcache

# Install Redis Extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1

# Copy Application Code
COPY . .
COPY --from=frontend-builder /app/public/build ./public/build

# Install Composer Dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Setup Storage & Cache Directories
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache public

# Copy PHP Config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

EXPOSE 80 443

CMD ["php-fpm"]
