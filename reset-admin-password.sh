#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pengaturan Ulang Kata Sandi Akun Administrator
# Ruang Digital Manajemen Harian
# ==============================================================================

set -euo pipefail

echo "=================================================================="
echo "          PENGATURAN ULANG KATA SANDI ADMINISTRATOR              "
echo "          Platform RUDIMAH (Ruang Digital Manajemen Harian)      "
echo "=================================================================="

# 1. Pemuatan Konfigurasi Lingkungan
if [ -f ".env" ]; then
    set -a
    source .env 2>/dev/null || . .env 2>/dev/null || true
    set +a
fi

# 2. Verifikasi Ketersediaan Docker
if ! command -v docker >/dev/null 2>&1; then
    echo "[!] Kesalahan: Docker CLI tidak terdeteksi pada sistem ini."
    exit 1
fi

# 3. Eksekusi Perintah Artisan Reset Password
if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^rudimah_app$"; then
    echo "[*] Menjalankan pembaruan kata sandi pada kontainer aktif (rudimah_app)..."
    if [ -t 0 ]; then
        docker compose exec app php artisan admin:reset-password "$@"
    else
        docker compose exec -T app php artisan admin:reset-password "$@"
    fi
else
    echo "[*] Kontainer utama tidak aktif, menjalankan container runner sementara..."
    if [ -t 0 ]; then
        docker compose run --rm app php artisan admin:reset-password "$@"
    else
        docker compose run -T --rm app php artisan admin:reset-password "$@"
    fi
fi

echo ""
echo "[OK] Proses pengaturan ulang kata sandi selesai."
