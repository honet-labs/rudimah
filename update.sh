#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pembaruan Sistem Terkendali (Non-Destruktif)
# ==============================================================================

if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -e

LOCK_FILE="/tmp/rudimah_update.lock"
trap 'rm -f "$LOCK_FILE"' EXIT INT TERM

if [ -f "$LOCK_FILE" ]; then
    OLD_PID=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
    if [ -n "$OLD_PID" ] && [ "$OLD_PID" != "$$" ] && kill -0 "$OLD_PID" 2>/dev/null; then
        echo "[!] Proses pembaruan lain sedang berjalan (PID $OLD_PID). Silakan tunggu hingga selesai."
        exit 1
    fi
    rm -f "$LOCK_FILE"
fi

echo $$ > "$LOCK_FILE"

echo "=================================================================="
echo "          PROSES PEMBARUAN SISTEM RUDIMAH                        "
echo "=================================================================="

# 0. Tarik Pembaruan Kode Terbaru dari Repositori Git jika Tersedia
if [ "${RUDIMAH_UPDATED_SELF:-0}" != "1" ] && [ -d ".git" ] && command -v git >/dev/null 2>&1; then
    echo "[*] Tahap 0: Mengambil kode terbaru dari repositori Git..."
    PULL_OUT=$(git pull origin main 2>&1 || true)
    echo "$PULL_OUT"
    if echo "$PULL_OUT" | grep -q -E "Fast-forward|Updating [0-9a-f]"; then
        export RUDIMAH_UPDATED_SELF=1
        rm -f "$LOCK_FILE"
        exec bash "$0" "$@"
    fi
fi

# 1. Pastikan Berkas .env Tersedia dan Baca Variabel Basis Data
if [ ! -f ".env" ]; then
    echo "[!] Berkas .env belum ditemukan. Silakan jalankan sh installer.sh terlebih dahulu."
    exit 1
fi

echo "[*] Tahap 1: Membaca konfigurasi lingkungan..."
DB_USER=$(grep -E '^DB_PLATFORM_USERNAME=' .env | cut -d '=' -f2- | tr -d '\r"' || echo "rudimah_user")
DB_PASS=$(grep -E '^DB_PLATFORM_PASSWORD=' .env | cut -d '=' -f2- | tr -d '\r"' || echo "")
DB_ROOT_PASS=$(grep -E '^DB_ROOT_PASSWORD=' .env | cut -d '=' -f2- | tr -d '\r"' || echo "mariadb_root_pass")
DB_PLATFORM_NAME=$(grep -E '^DB_PLATFORM_DATABASE=' .env | cut -d '=' -f2- | tr -d '\r"' || echo "rudimah_platform")
DB_PEKAN_NAME=$(grep -E '^DB_PEKAN_DATABASE=' .env | cut -d '=' -f2- | tr -d '\r"' || echo "rudimah_pekan")

# Pastikan AUTH_MODEL terkonfigurasi di .env
if ! grep -q '^AUTH_MODEL=' .env; then
    echo "AUTH_MODEL=App\\Core\\Identity\\Models\\User" >> .env
fi

# Pastikan SESSION_DRIVER file agar tidak memicu 500 error bila redis flushing
sed -i 's/^SESSION_DRIVER=redis/SESSION_DRIVER=file/' .env 2>/dev/null || true

# Deteksi root pass dari container jika mariadb aktif
if command -v docker >/dev/null 2>&1; then
    CONTAINER_ROOT_PASS=$(docker inspect rudimah_mariadb --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null | grep -E '^MYSQL_ROOT_PASSWORD=' | cut -d '=' -f2- | tr -d '\r"' || true)
    if [ -n "$CONTAINER_ROOT_PASS" ]; then
        DB_ROOT_PASS="$CONTAINER_ROOT_PASS"
    fi
fi

# 2. Pencadangan Otomatis Sebelum Pembaruan
echo "[*] Tahap 2: Menjalankan pencadangan data otomatis..."
if [ -f "./backup.sh" ]; then
    bash ./backup.sh || true
else
    echo "  [INFO] Skrip backup.sh dilewati."
fi

# 3. Membangun Image Container Aplikasi Terbaru
echo "[*] Tahap 3: Membangun image container aplikasi terbaru..."
if command -v docker >/dev/null 2>&1; then
    docker compose build app
fi

# 4. Sinkronisasi Hak Akses dan Pengguna Basis Data MariaDB
echo "[*] Tahap 4: Memverifikasi pengguna dan izin basis data..."
if command -v docker >/dev/null 2>&1; then
    docker compose up -d mariadb redis

    SYNC_SQL="
        CREATE DATABASE IF NOT EXISTS \`${DB_PLATFORM_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE DATABASE IF NOT EXISTS \`${DB_PEKAN_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
        ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
        CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
        ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
        GRANT ALL PRIVILEGES ON \`${DB_PLATFORM_NAME}\`.* TO '${DB_USER}'@'%';
        GRANT ALL PRIVILEGES ON \`${DB_PLATFORM_NAME}\`.* TO '${DB_USER}'@'localhost';
        GRANT ALL PRIVILEGES ON \`${DB_PEKAN_NAME}\`.* TO '${DB_USER}'@'%';
        GRANT ALL PRIVILEGES ON \`${DB_PEKAN_NAME}\`.* TO '${DB_USER}'@'localhost';
        ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_ROOT_PASS}';
        FLUSH PRIVILEGES;
    "

    EXECUTED=false
    # Cara 1: Unix socket sebagai OS root di dalam kontainer MariaDB (terbukti paling andal)
    if docker compose exec -u 0 -T mariadb mariadb -u root -e "$SYNC_SQL" >/dev/null 2>&1; then
        echo "  [OK] Pengguna '${DB_USER}' dan izin basis data berhasil disinkronkan (via unix socket root)."
        EXECUTED=true
    elif [ -n "$DB_ROOT_PASS" ] && docker compose exec -T mariadb mariadb -u root -p"${DB_ROOT_PASS}" -e "$SYNC_SQL" >/dev/null 2>&1; then
        echo "  [OK] Pengguna '${DB_USER}' berhasil disinkronkan (via password root .env)."
        EXECUTED=true
    elif docker compose exec -T mariadb mariadb -u root -pmariadb_root_pass -e "$SYNC_SQL" >/dev/null 2>&1; then
        echo "  [OK] Pengguna '${DB_USER}' berhasil disinkronkan (via password root default)."
        EXECUTED=true
    elif docker compose exec -T mariadb mariadb -u root -e "$SYNC_SQL" >/dev/null 2>&1; then
        echo "  [OK] Pengguna '${DB_USER}' berhasil disinkronkan tanpa password."
        EXECUTED=true
    fi

    if [ "$EXECUTED" = false ]; then
        echo "  [PERINGATAN] Mencoba sinkronisasi darurat ke MariaDB..."
        docker compose exec -u 0 -T mariadb sh -c "mariadb -u root << 'EOSQL'
$SYNC_SQL
EOSQL" 2>/dev/null || true
    fi
fi

# 5. Muat Ulang Kontainer dengan Kode Baru
echo "[*] Tahap 5: Memuat ulang layanan kontainer..."
if command -v docker >/dev/null 2>&1; then
    docker compose up -d --remove-orphans
    docker compose restart app horizon scheduler || true

    echo "  ... Menunggu kontainer aplikasi siap..."
    APP_RETRIES=20
    while [ $APP_RETRIES -gt 0 ]; do
        if docker compose exec -T app php -v >/dev/null 2>&1; then
            break
        fi
        sleep 2
        APP_RETRIES=$((APP_RETRIES - 1))
    done
fi

# 6. Eksekusi Migrasi Basis Data & Sinkronisasi Seeder
echo "[*] Tahap 6: Menjalankan migrasi basis data dan seeder..."
if command -v docker >/dev/null 2>&1; then
    docker compose exec -T app php artisan migrate --force
    docker compose exec -T app php artisan db:seed --force || true
fi

# 7. Pembersihan Cache dan Sinkronisasi Penyimpanan
echo "[*] Tahap 7: Membersihkan cache dan memperbarui aset..."
if command -v docker >/dev/null 2>&1; then
    docker compose exec -T app php artisan storage:link --force 2>/dev/null || true
    docker compose exec -T app php artisan optimize:clear 2>/dev/null || true
    docker compose exec -T app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
    docker compose exec -T app chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
fi

# 8. Uji Kesehatan Sistem
echo "[*] Tahap 8: Memverifikasi kesiapan sistem..."
if [ -f "./status.sh" ]; then
    bash ./status.sh || true
fi

echo ""
echo "=================================================================="
echo "  [OK] PEMBARUAN PLATFORM RUDIMAH BERHASIL DISELESAIKAN!          "
echo "=================================================================="
