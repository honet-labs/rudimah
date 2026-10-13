#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Penghapusan Deployment
# ==============================================================================

set -euo pipefail

PURGE_DATA=false

for arg in "$@"; do
    if [ "$arg" = "--purge-data" ]; then
        PURGE_DATA=true
    fi
done

echo "=================================================================="
echo "          PENGHAPUSAN INSTALASI RUDIMAH                          "
echo "=================================================================="

if [ "$PURGE_DATA" = true ]; then
    echo "[PERINGATAN] Anda memilih opsi --purge-data."
    echo "Tindakan ini akan MENGHAPUS SELURUH BASIS DATA, BERKAS LAMPIRAN, DAN KREDENSIAL."
    read -rp "Ketik 'HAPUS DATA SAYA' untuk mengonfirmasi: " confirm1
    if [ "$confirm1" != "HAPUS DATA SAYA" ]; then
        echo "[*] Konfirmasi pertama dibatalkan. Penghentian dibatalkan."
        exit 1
    fi

    read -rp "Konfirmasi kedua: Apakah Anda benar-benar yakin? (ya/tidak): " confirm2
    if [ "$confirm2" != "ya" ]; then
        echo "[*] Dibatalkan pada konfirmasi kedua."
        exit 1
    fi

    echo "[*] Menghentikan kontainer dan menghapus seluruh volume data..."
    if command -v docker >/dev/null 2>&1; then
        docker compose down -v --remove-orphans || true
    fi
    rm -rf storage/app/receipts/* || true
    echo "[OK] Seluruh data RUDIMAH telah dibersihkan."
else
    echo "[*] Menjalankan penghentian standar (data persisten, backup, dan kunci tetap disimpan)..."
    if command -v docker >/dev/null 2>&1; then
        docker compose down --remove-orphans || true
    fi
    echo "[OK] Kontainer dan jaringan proyek telah dihentikan."
    echo "     Volume database, berkas lampiran, dan konfigurasi .env tetap aman tersimpan."
fi
