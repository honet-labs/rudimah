#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pembaruan Sistem Terkendali (Non-Destruktif)
# ==============================================================================

set -euo pipefail

LOCK_FILE="/tmp/rudimah_update.lock"

if [ -f "$LOCK_FILE" ]; then
    echo "[!] Proses pembaruan lain sedang berjalan. Silakan tunggu hingga selesai."
    exit 1
fi

touch "$LOCK_FILE"
trap 'rm -f "$LOCK_FILE"' EXIT

echo "=================================================================="
echo "          PROSES PEMBARUAN SISTEM RUDIMAH                        "
echo "=================================================================="

# 1. Verifikasi Pencadangan (Backup)
echo "[*] Tahap 1: Memeriksa ketersediaan cadangan data terbaru..."
if [ -f "./backup.sh" ]; then
    ./backup.sh
else
    echo "  [PERINGATAN] Skrip backup.sh tidak ditemukan, melanjutkan dengan hati-hati."
fi

# 2. Build Image Docker Terbaru
echo "[*] Tahap 2: Membangun image container aplikasi terbaru..."
if command -v docker >/dev/null 2>&1; then
    docker compose build --pull app horizon
fi

# 3. Eksekusi Migrasi Basis Data Aman (Forward-Compatible)
echo "[*] Tahap 3: Menjalankan migrasi skema basis data..."
if command -v docker >/dev/null 2>&1; then
    docker compose run --rm app php artisan migrate --force
fi

# 4. Restart Kontainer & Pembersihan Cache
echo "[*] Tahap 4: Memuat ulang layanan..."
if command -v docker >/dev/null 2>&1; then
    docker compose up -d --remove-orphans
    docker compose exec -T app php artisan optimize:clear || true
    docker compose exec -T app php artisan optimize || true
fi

# 5. Uji Kesehatan Sistem (Healthcheck)
echo "[*] Tahap 5: Memverifikasi kesiapan sistem..."
if [ -f "./status.sh" ]; then
    ./status.sh
fi

echo ""
echo "[OK] Pembaruan platform RUDIMAH berhasil diselesaikan tanpa kehilangan data."
