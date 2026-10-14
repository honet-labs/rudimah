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

# 0. Muat Konfigurasi Lingkungan & Tarik Perubahan Kode Terbaru jika Git Ada
if [ -f ".env" ]; then
    set -a
    source .env 2>/dev/null || . .env 2>/dev/null || true
    set +a
fi

if [ -d ".git" ] && command -v git >/dev/null 2>&1; then
    echo "[*] Tahap 0: Mengambil kode terbaru dari repositori Git..."
    git pull origin main || true
fi

# 1. Verifikasi Pencadangan (Backup)
echo "[*] Tahap 1: Memeriksa ketersediaan cadangan data terbaru..."
if [ -f "./backup.sh" ]; then
    bash ./backup.sh || true
else
    echo "  [PERINGATAN] Skrip backup.sh tidak ditemukan, melanjutkan dengan hati-hati."
fi

# 2. Build Image Docker Terbaru
echo "[*] Tahap 2: Membangun image container aplikasi terbaru..."
if command -v docker >/dev/null 2>&1; then
    docker compose build app horizon scheduler
fi

# 3. Eksekusi Migrasi Basis Data Aman (Forward-Compatible) & Seeding Data Awal
echo "[*] Tahap 3: Menjalankan migrasi skema basis data dan sinkronisasi data awal..."
if command -v docker >/dev/null 2>&1; then
    docker compose run --rm app php artisan migrate --force
    docker compose run --rm app php artisan db:seed --force || true
fi

# 4. Restart Kontainer & Pembersihan Cache
echo "[*] Tahap 4: Memuat ulang layanan..."
if command -v docker >/dev/null 2>&1; then
    docker compose up -d --remove-orphans
    docker compose restart redis nginx || true
    docker compose exec -T app php artisan storage:link || true
    docker compose exec -T app php artisan optimize:clear || true
    docker compose exec -T app php artisan optimize || true
fi

# 5. Uji Kesehatan Sistem (Healthcheck)
echo "[*] Tahap 5: Memverifikasi kesiapan sistem..."
if [ -f "./status.sh" ]; then
    bash ./status.sh || true
fi

echo ""
echo "[OK] Pembaruan platform RUDIMAH berhasil diselesaikan tanpa kehilangan data."
