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

ENV COMPOSER_IPRESOLVE=4 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_PROCESS_TIMEOUT=2000 \
    COMPOSER_MAX_PARALLEL_HTTP=2

# Copy Application Code
COPY . .
COPY --from=frontend-builder /app/public/build ./public/build
COPY --from=frontend-builder /app/public/build /var/www/html/build_dist
RUN cp -rf /var/www/html/public /var/www/html/public_dist

# Install Composer Dependencies dengan resolusi IPv4 eksplisit dan retry otomatis
RUN composer config --global process-timeout 2000 \
    && SUCCESS=0; \
    for i in 1 2 3 4 5; do \
        echo "[*] Menjalankan composer install via IPv4 (percobaan $i/5)..." && \
        COMPOSER_IPRESOLVE=4 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --no-audit && { SUCCESS=1; break; } || \
        (echo "[!] Percobaan $i gagal, mencoba kembali dalam 5 detik..." && sleep 5); \
    done; \
    if [ "$SUCCESS" -ne 1 ] || [ ! -f /var/www/html/vendor/autoload.php ]; then \
        echo "[!] ERROR: Gagal menginstal dependensi Composer via Packagist!"; \
        exit 1; \
    fi

# Setup Storage & Cache Directories
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache public

# Copy PHP Config & Entrypoint
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
