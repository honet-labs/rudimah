#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pemeriksaan Kesiapan Sistem Host (Preflight)
# ==============================================================================

set -euo pipefail

echo "=================================================================="
echo "          PEMERIKSAAN KESIAPAN SISTEM HOST (PREFLIGHT)           "
echo "=================================================================="

# 1. Cek Ruang Disk
AVAILABLE_DISK_KB=$(df . | awk 'NR==2 {print $4}')
REQUIRED_DISK_KB=2097152 # 2 GB

if [ "$AVAILABLE_DISK_KB" -ge "$REQUIRED_DISK_KB" ]; then
    echo "  [OK] Ruang disk mencukupi (> 2GB)"
else
    echo "  [PERINGATAN] Ruang disk kurang dari 2GB"
fi

# 2. Cek Utilitas Docker
if command -v docker >/dev/null 2>&1; then
    echo "  [OK] Docker Engine terpasang"
else
    echo "  [PERINGATAN] Docker Engine tidak ditemukan"
fi

if docker compose version >/dev/null 2>&1; then
    echo "  [OK] Docker Compose plugin terpasang"
else
    echo "  [PERINGATAN] Docker Compose tidak ditemukan"
fi

echo "[OK] Pemeriksaan pra-syarat selesai."
