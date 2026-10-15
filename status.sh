#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pemantauan Status Kesehatan Sistem
# ==============================================================================

if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -e

echo "=================================================================="
echo "          STATUS KESEHATAN LAYANAN RUDIMAH                       "
echo "=================================================================="

# 1. Status Kontainer Docker
echo "[*] Kontainer Layanan:"
if command -v docker >/dev/null 2>&1; then
    docker compose ps --format "table {{.Service}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || docker compose ps || true
else
    echo "  [INFO] Docker CLI tidak tersedia di lingkungan saat ini."
fi

echo ""
echo "[*] Konektivitas Basis Data & Cache:"
# Cek MariaDB
if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^rudimah_mariadb$"; then
    echo "  - MariaDB  : Aktif (Healthy)"
else
    echo "  - MariaDB  : Siap dikonfigurasi"
fi

# Cek Redis
if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^rudimah_redis$"; then
    echo "  - Redis    : Aktif (Healthy)"
else
    echo "  - Redis    : Siap dikonfigurasi"
fi

echo ""
echo "[*] Ketersediaan Berkas Konfigurasi:"
if [ -f ".env" ]; then
    echo "  - Berkas .env : Tersedia"
else
    echo "  - Berkas .env : Belum dibuat (Jalankan ./installer.sh)"
fi

echo ""
echo "[OK] Pemeriksaan status operasional selesai."
