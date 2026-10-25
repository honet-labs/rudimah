#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Rekonfigurasi Pengaturan Sistem
# ==============================================================================

if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -e

echo "=================================================================="
echo "          REKONFIGURASI PENGATURAN PLATFORM RUDIMAH              "
echo "=================================================================="

if [ ! -f ".env" ]; then
    echo "[!] Berkas .env belum ditemukan. Silakan jalankan ./installer.sh terlebih dahulu."
    exit 1
fi

echo "[*] Membuat salinan cadangan konfigurasi saat ini..."
cp .env ".env.backup_$(date +%s)"

echo ""
echo "Pilih parameter yang ingin Anda ubah:"
echo "1) Ubah Port HTTP / HTTPS"
echo "2) Ubah Pengaturan Kredensial Basis Data"
echo "3) Deteksi Ulang Spesifikasi Server & Re-Tuning Performa (OS/CPU/RAM)"
echo "4) Batal"

read -rp "Pilihan Anda [1-4]: " choice

case "$choice" in
    1)
        read -rp "Port HTTP baru: " new_http
        read -rp "Port HTTPS baru: " new_https
        if [ -n "$new_http" ]; then
            sed -i "s/^HTTP_PORT=.*/HTTP_PORT=$new_http/" .env
        fi
        if [ -n "$new_https" ]; then
            sed -i "s/^HTTPS_PORT=.*/HTTPS_PORT=$new_https/" .env
        fi
        echo "[OK] Port berhasil diperbarui."
        ;;
    2)
        read -rp "Host Database baru: " new_db_host
        if [ -n "$new_db_host" ]; then
            sed -i "s/^DB_PLATFORM_HOST=.*/DB_PLATFORM_HOST=$new_db_host/" .env
            sed -i "s/^DB_PEKAN_HOST=.*/DB_PEKAN_HOST=$new_db_host/" .env
        fi
        echo "[OK] Host basis data berhasil diperbarui."
        ;;
    3)
        echo "[*] Menjalankan deteksi hardware dan kalkulasi profil tuning baru..."
        if [ -f "scripts/tune-system.sh" ]; then
            bash scripts/tune-system.sh
        fi
        echo "[OK] Profil tuning performa berhasil disesuaikan dengan hardware terkini."
        ;;
    *)
        echo "[*] Rekonfigurasi dibatalkan."
        exit 0
        ;;
esac

echo "[*] Muat ulang kontainer aplikasi dengan konfigurasi baru..."
if command -v docker >/dev/null 2>&1; then
    docker compose up -d --remove-orphans
fi
echo "[OK] Rekonfigurasi selesai diterapkan."
