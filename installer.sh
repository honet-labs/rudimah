#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH : Skrip Instalasi Interaktif dan Terautomasi End-to-End
# Platform Ruang Digital Manajemen Harian
# ==============================================================================

set -euo pipefail

# Inisialisasi variabel default
NON_INTERACTIVE=false
DRY_RUN=false
CONFIG_FILE=""
APP_PORT=80
HTTPS_PORT=443
HOST_IP="127.0.0.1"
DB_HOST="mariadb"
DB_PORT=3306
DB_PLATFORM_NAME="rudimah_platform"
DB_PEKAN_NAME="rudimah_pekan"
DB_USER="rudimah_user"
DB_PASS=""
ROOT_DB_PASS=""
ADMIN_EMAIL="admin@rudimah.id"
ADMIN_PASS=""
AI_PROVIDER="gemini"
AUTO_START=true

# Deteksi IP server host jika memungkinkan
if command -v hostname >/dev/null 2>&1; then
    DETECTED_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "127.0.0.1")
    if [ -n "$DETECTED_IP" ]; then
        HOST_IP="$DETECTED_IP"
    fi
fi

print_help() {
    cat << EOF
Penggunaan: ./installer.sh [OPSI]

Opsi yang tersedia:
  --config <path>      Gunakan file konfigurasi otomatis
  --non-interactive    Jalankan instalasi dan deployment langsung tanpa konfirmasi
  --dry-run            Simulasikan tahapan tanpa mengubah sistem
  --no-start           Hanya buat konfigurasi .env tanpa menyalakan kontainer
  --help               Tampilkan panduan bantuan ini

EOF
    exit 0
}

# Parsing argumen baris perintah
while [[ $# -gt 0 ]]; do
    case "$1" in
        --non-interactive) NON_INTERACTIVE=true; shift ;;
        --dry-run) DRY_RUN=true; shift ;;
        --no-start) AUTO_START=false; shift ;;
        --config) CONFIG_FILE="$2"; shift 2 ;;
        --help) print_help ;;
        *) echo "[!] Opsi tidak dikenali: $1"; exit 1 ;;
    esac
done

echo "=================================================================="
echo "          WIZARD INSTALASI PLATFORM RUDIMAH                      "
echo "          Ruang Digital Manajemen Harian                         "
echo "=================================================================="

# 1. Pemeriksaan Pra-syarat (Preflight)
echo "[*] Menjalankan pemeriksaan pra-syarat sistem..."

if ! command -v docker >/dev/null 2>&1; then
    echo "[!] Kesalahan: Docker belum terpasang di sistem ini."
    echo "    Silakan pasang Docker terlebih dahulu: https://docs.docker.com/engine/install/"
    exit 1
else
    echo "  [OK] Docker terdeteksi: $(docker --version)"
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "[!] Kesalahan: Docker Compose (v2) belum terpasang."
    exit 1
else
    echo "  [OK] Docker Compose terdeteksi: $(docker compose version --short 2>/dev/null || echo 'v2')"
fi

# 2. Input Konfigurasi (Interaktif jika bukan non-interactive)
if [ "$NON_INTERACTIVE" = false ] && [ -z "$CONFIG_FILE" ]; then
    echo ""
    echo "Silakan masukkan parameter instalasi (tekan Enter untuk memakai nilai default):"
    
    read -rp "Port HTTP publik [default: 80]: " input_http
    APP_PORT="${input_http:-80}"

    read -rp "Port HTTPS publik [default: 443]: " input_https
    HTTPS_PORT="${input_https:-443}"

    read -rp "Nama basis data Platform [default: rudimah_platform]: " input_p_db
    DB_PLATFORM_NAME="${input_p_db:-rudimah_platform}"

    read -rp "Nama basis data Modul PEKAN [default: rudimah_pekan]: " input_m_db
    DB_PEKAN_NAME="${input_m_db:-rudimah_pekan}"

    read -rp "Username basis data [default: rudimah_user]: " input_db_user
    DB_USER="${input_db_user:-rudimah_user}"

    read -rp "Email administrator utama [default: admin@rudimah.id]: " input_admin_email
    ADMIN_EMAIL="${input_admin_email:-admin@rudimah.id}"

    read -rp "Penyedia AI default (gemini/openai/hermes/openclaw/mock) [default: gemini]: " input_ai
    AI_PROVIDER="${input_ai:-gemini}"
fi

# Muat dari file konfigurasi bila diberikan
if [ -n "$CONFIG_FILE" ] && [ -f "$CONFIG_FILE" ]; then
    echo "[*] Memuat konfigurasi dari $CONFIG_FILE..."
    # shellcheck disable=SC1090
    source "$CONFIG_FILE"
fi

# Generate password acak jika kosong
if [ -z "$DB_PASS" ]; then
    DB_PASS=$(openssl rand -hex 16 2>/dev/null || echo "SecretRudimahDbPass2026")
fi

if [ -z "$ROOT_DB_PASS" ]; then
    ROOT_DB_PASS=$(openssl rand -hex 16 2>/dev/null || echo "SecretRudimahRootPass2026")
fi

if [ -z "$ADMIN_PASS" ]; then
    ADMIN_PASS=$(openssl rand -hex 12 2>/dev/null || echo "AdminRUDIMAH2026!")
fi

APP_KEY="base64:$(openssl rand -base64 32 2>/dev/null || echo "KjU2aGs4OWpmODkzag==")"

echo ""
echo "[*] Ringkasan Konfigurasi RUDIMAH:"
echo "    - Host / IP Akses     : $HOST_IP"
echo "    - Port HTTP           : $APP_PORT"
echo "    - Port HTTPS          : $HTTPS_PORT"
echo "    - Platform Database   : $DB_PLATFORM_NAME"
echo "    - PEKAN Database      : $DB_PEKAN_NAME"
echo "    - Database User       : $DB_USER"
echo "    - Akun Administrator  : $ADMIN_EMAIL"
echo "    - Default AI Provider : $AI_PROVIDER"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo "[*] Mode simulasi (--dry-run) aktif. Tidak ada perubahan yang dilakukan."
    exit 0
fi

# 3. Pembuatan Berkas Konfigurasi .env
echo "[*] Menulis konfigurasi ke berkas .env..."
umask 077

cat > .env << EOF
APP_NAME=RUDIMAH
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=http://$HOST_IP:$APP_PORT
APP_TIMEZONE=Asia/Jakarta

HTTP_PORT=$APP_PORT
HTTPS_PORT=$HTTPS_PORT

DB_CONNECTION=platform
DB_PLATFORM_HOST=$DB_HOST
DB_PLATFORM_PORT=$DB_PORT
DB_PLATFORM_DATABASE=$DB_PLATFORM_NAME
DB_PLATFORM_USERNAME=$DB_USER
DB_PLATFORM_PASSWORD=$DB_PASS
DB_ROOT_PASSWORD=$ROOT_DB_PASS

DB_PEKAN_HOST=$DB_HOST
DB_PEKAN_PORT=$DB_PORT
DB_PEKAN_DATABASE=$DB_PEKAN_NAME
DB_PEKAN_USERNAME=$DB_USER
DB_PEKAN_PASSWORD=$DB_PASS

INITIAL_ADMIN_EMAIL=$ADMIN_EMAIL
INITIAL_ADMIN_PASSWORD=$ADMIN_PASS

AUTH_MODEL=App\Core\Identity\Models\User
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=file

REDIS_HOST=redis
REDIS_PORT=6379

AI_DEFAULT_PROVIDER=$AI_PROVIDER
OCR_PROVIDER=$AI_PROVIDER
OCR_AUTO_COMMIT=false
EOF

chmod 600 .env
echo "  [OK] Berkas .env berhasil dibuat dengan hak akses terbatas (0600)."

# Jika opsi --no-start diberikan, berhenti di sini
if [ "$AUTO_START" = false ]; then
    echo ""
    echo "[*] Konfigurasi selesai (--no-start aktif)."
    echo "    Untuk menyalakan sistem secara manual, jalankan: docker compose up -d"
    exit 0
fi

# 4. Otomasi Orkestrasi Docker dan Deployment End-to-End
echo ""
echo "=================================================================="
echo "          MEMULAI DEPLOYMENT OTOMATIS KONTAINER DOCKER           "
chmod +x artisan *.sh docker/mariadb/*.sh 2>/dev/null || true

echo "[1/6] Membangun dan menjalankan kontainer Docker..."
docker compose up -d --build

echo "[2/6] Menunggu MariaDB dan Redis siap menerima koneksi..."
RETRIES=30
while [ $RETRIES -gt 0 ]; do
    if docker compose exec -T mariadb mariadb-admin ping -h localhost -u root -p"${ROOT_DB_PASS}" --silent >/dev/null 2>&1; then
        echo "  [OK] MariaDB siap."
        break
    fi
    echo "  ... Menunggu MariaDB siap ($RETRIES tersisa)"
    sleep 2
    RETRIES=$((RETRIES - 1))
done

if [ $RETRIES -eq 0 ]; then
    echo "[!] Kesalahan: MariaDB gagal memulai dalam batas waktu yang ditentukan."
    exit 1
fi

echo "[3/6] Memverifikasi ketersediaan basis data Platform dan PEKAN..."
docker compose exec -T mariadb mariadb -u root -p"${ROOT_DB_PASS}" -e "
    CREATE DATABASE IF NOT EXISTS \`${DB_PLATFORM_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS \`${DB_PEKAN_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${DB_PLATFORM_NAME}\`.* TO '${DB_USER}'@'%';
    GRANT ALL PRIVILEGES ON \`${DB_PEKAN_NAME}\`.* TO '${DB_USER}'@'%';
    FLUSH PRIVILEGES;
" || true

echo "[4/6] Menjalankan migrasi basis data Laravel..."
docker compose exec -T app php artisan migrate --force

echo "[5/6] Menginisialisasi akun administrator dan data awal..."
docker compose exec -T app php artisan db:seed --force

echo "[6/6] Menyiapkan tautan penyimpanan dan membersihkan cache..."
docker compose exec -T app php artisan storage:link || true
docker compose exec -T app php artisan optimize:clear || true
docker compose exec -T app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
docker compose exec -T app chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo ""
echo "=================================================================="
echo "          INSTALASI DAN DEPLOYMENT RUDIMAH BERHASIL SELESAI       "
echo "=================================================================="
echo ""
echo "Portal RUDIMAH telah aktif dan siap digunakan:"
echo "  URL Platform   : http://$HOST_IP:$APP_PORT"
echo "  Email Admin    : $ADMIN_EMAIL"
echo "  Password Admin : $ADMIN_PASS"
echo ""
echo "Catatan Operasional:"
echo "  - Cek status sistem : bash status.sh"
echo "  - Cadangkan data    : bash backup.sh"
echo "  - Pembaruan sistem  : bash update.sh"
echo "  - Rekonfigurasi     : bash reconfigure.sh"
echo "  - Reset Password    : bash reset-admin-password.sh"
echo "  - Hentikan layanan  : docker compose down"
echo "=================================================================="
