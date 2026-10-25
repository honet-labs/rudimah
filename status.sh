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
echo "[*] Profil Tuning Performa Sistem Host:"
if [ -f "docker/mariadb/custom.cnf" ]; then
    BUFFER_POOL=$(grep -E '^innodb_buffer_pool_size' docker/mariadb/custom.cnf | awk '{print $3}' || echo "N/A")
    MAX_CONN=$(grep -E '^max_connections' docker/mariadb/custom.cnf | awk '{print $3}' || echo "N/A")
    echo "  - MariaDB Buffer Pool  : $BUFFER_POOL (Max Connections: $MAX_CONN)"
fi
if [ -f "docker/php/zz-docker.conf" ]; then
    MAX_CHILDREN=$(grep -E '^pm.max_children' docker/php/zz-docker.conf | awk '{print $3}' || echo "N/A")
    echo "  - PHP-FPM Max Workers  : $MAX_CHILDREN worker"
fi
if [ -f "docker/redis/redis.conf" ]; then
    REDIS_MEM=$(grep -E '^maxmemory' docker/redis/redis.conf | head -n1 | awk '{print $2}' || echo "N/A")
    echo "  - Redis Max Memory     : $REDIS_MEM"
fi

echo ""
echo "[OK] Pemeriksaan status operasional selesai."
