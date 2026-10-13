#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Instalasi Interaktif & Terautomasi
# Platform Ruang Digital Manajemen Harian
# ==============================================================================

set -euo pipefail

# Inisialisasi variabel default
NON_INTERACTIVE=false
DRY_RUN=false
CONFIG_FILE=""
APP_PORT=80
HTTPS_PORT=443
DB_HOST="mariadb"
DB_PORT=3306
DB_PLATFORM_NAME="rudimah_platform"
DB_PEKAN_NAME="rudimah_pekan"
DB_USER="rudimah_user"
DB_PASS=""
ROOT_DB_PASS=""
ADMIN_EMAIL="admin@rudimah.id"
ADMIN_PASS=""

print_help() {
    cat << EOF
Penggunaan: ./installer.sh [OPSI]

Opsi yang tersedia:
  --config <path>      Gunakan file konfigurasi otomatis
  --non-interactive    Jalankan instalasi tanpa konfirmasi interaktif
  --dry-run            Simulasikan tahapan tanpa menulis perubahan ke sistem
  --help               Tampilkan panduan bantuan ini

EOF
    exit 0
}

# Parsing argumen baris perintah
while [[ $# -gt 0 ]]; do
    case "$1" in
        --non-interactive) NON_INTERACTIVE=true; shift ;;
        --dry-run) DRY_RUN=true; shift ;;
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

if command -v docker >/dev/null 2>&1; then
    echo "  [OK] Docker terdeteksi: $(docker --version)"
else
    echo "  [PERINGATAN] Docker belum terpasang di sistem host."
fi

if docker compose version >/dev/null 2>&1; then
    echo "  [OK] Docker Compose terdeteksi"
else
    echo "  [PERINGATAN] Docker Compose belum terpasang di sistem host."
fi

# 2. Input Konfigurasi (Interaktif jika bukan non-interactive)
if [ "$NON_INTERACTIVE" = false ] && [ -z "$CONFIG_FILE" ]; then
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
echo "    - Port HTTP           : $APP_PORT"
echo "    - Port HTTPS          : $HTTPS_PORT"
echo "    - Platform Database   : $DB_PLATFORM_NAME"
echo "    - PEKAN Database      : $DB_PEKAN_NAME"
echo "    - Database User       : $DB_USER"
echo "    - Akun Administrator  : $ADMIN_EMAIL"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo "[*] Mode simulasi (--dry-run) aktif. Tidak ada berkas yang diubah."
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
APP_URL=http://localhost:$APP_PORT
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

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379

OCR_PROVIDER=local_vision
OCR_AUTO_COMMIT=false
EOF

chmod 600 .env
echo "  [OK] Berkas .env berhasil dibuat dengan hak akses terbatas (0600)."

echo ""
echo "[*] Instalasi konfigurasi RUDIMAH selesai."
echo "    Untuk menjalankan layanan, jalankan perintah: docker compose up -d"
echo "    Kredensial Admin Awal:"
echo "    Email    : $ADMIN_EMAIL"
echo "    Password : $ADMIN_PASS"
echo "    Simpan kredensial ini di tempat yang aman."
