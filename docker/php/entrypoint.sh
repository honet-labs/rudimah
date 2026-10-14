#!/usr/bin/env bash
set -e

# 1. Pastikan struktur direktori storage dan cache tersedia
mkdir -p \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# 2. Inisialisasi otomatis jika perintah adalah php-fpm (container app)
if [ "$1" = "php-fpm" ]; then
    echo "[*] Inisialisasi kontainer aplikasi RUDIMAH..."

    # Pastikan file .env ada
    if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    fi

    # Generate APP_KEY jika belum terisi
    if [ -z "${APP_KEY:-}" ]; then
        echo "[*] Menghasilkan APP_KEY..."
        php artisan key:generate --force || true
    fi

    # Sinkronisasi aset statis frontend dan publik ke volume
    if [ -d /var/www/html/public_dist ]; then
        echo "[*] Sinkronisasi aset statis frontend dan direktori publik..."
        cp -rf /var/www/html/public_dist/* /var/www/html/public/ 2>/dev/null || true
        chown -R www-data:www-data /var/www/html/public 2>/dev/null || true
        chmod -R 775 /var/www/html/public 2>/dev/null || true
    elif [ -d /var/www/html/build_dist ]; then
        echo "[*] Sinkronisasi aset statis frontend..."
        mkdir -p /var/www/html/public/build
        cp -rf /var/www/html/build_dist/* /var/www/html/public/build/ 2>/dev/null || true
        chown -R www-data:www-data /var/www/html/public 2>/dev/null || true
        chmod -R 775 /var/www/html/public 2>/dev/null || true
    fi

    # Tunggu MariaDB siap
    echo "[*] Memeriksa kesiapan basis data..."
    RETRIES=30
    while [ $RETRIES -gt 0 ]; do
        if php -r "
            \$host = getenv('DB_PLATFORM_HOST') ?: 'mariadb';
            \$port = getenv('DB_PLATFORM_PORT') ?: '3306';
            \$user = getenv('DB_PLATFORM_USERNAME') ?: 'rudimah_user';
            \$pass = getenv('DB_PLATFORM_PASSWORD') ?: '';
            try {
                new PDO(\"mysql:host=\$host;port=\$port\", \$user, \$pass);
                exit(0);
            } catch (Throwable \$e) {
                exit(1);
            }
        " >/dev/null 2>&1; then
            echo "  [OK] Basis data terhubung."
            break
        fi
        sleep 1
        RETRIES=$((RETRIES - 1))
    done

    # Jalankan migrasi dan seeding otomatis (aman & idempotent)
    echo "[*] Menjalankan migrasi basis data..."
    php artisan migrate --force || true

    echo "[*] Menjalankan seeder data awal..."
    php artisan db:seed --force || true

    # Buat symbolic link storage
    php artisan storage:link --force 2>/dev/null || true

    # Bersihkan cache optimasi
    php artisan optimize:clear 2>/dev/null || true
fi

# 3. Jeda inisialisasi jika proses adalah worker antrean
if [ "$1" = "php" ] && [ "${2:-}" = "artisan" ]; then
    echo "[*] Mempersiapkan worker antrean RUDIMAH..."
    sleep 3
fi

exec "$@"
