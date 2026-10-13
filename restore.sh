#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pemulihan Bencana (Restore)
# ==============================================================================

set -euo pipefail

BACKUP_ARCHIVE="${1:-}"

if [ -z "$BACKUP_ARCHIVE" ]; then
    echo "Penggunaan: ./restore.sh <path_ke_file_backup.tar.gz>"
    exit 1
fi

if [ ! -f "$BACKUP_ARCHIVE" ]; then
    echo "[!] Berkas cadangan tidak ditemukan: $BACKUP_ARCHIVE"
    exit 1
fi

echo "=================================================================="
echo "          PEMULIHAN BASIS DATA & BERKAS RUDIMAH                  "
echo "=================================================================="

# 1. Verifikasi Integritas Checksum jika tersedia
CHECKSUM_FILE="${BACKUP_ARCHIVE}.sha256"
if [ -f "$CHECKSUM_FILE" ] && command -v sha256sum >/dev/null 2>&1; then
    echo "[*] Memverifikasi integritas checksum SHA256..."
    sha256sum -c "$CHECKSUM_FILE"
    echo "  [OK] Integritas berkas cadangan terverifikasi."
fi

read -rp "Apakah Anda yakin ingin memulihkan sistem dari cadangan ini? (ya/tidak): " confirm
if [ "$confirm" != "ya" ]; then
    echo "[*] Proses pemulihan dibatalkan oleh pengguna."
    exit 1
fi

RESTORE_TMP="/tmp/rudimah_restore_$(date +%s)"
mkdir -p "$RESTORE_TMP"
tar -xzf "$BACKUP_ARCHIVE" -C "$RESTORE_TMP"

# 2. Pulihkan Basis Data
DUMP_FILE=$(find "$RESTORE_TMP" -name "databases_dump.sql" | head -n 1)
if [ -n "$DUMP_FILE" ] && [ -s "$DUMP_FILE" ] && command -v docker >/dev/null 2>&1; then
    echo "[*] Mengimpor skema basis data ke MariaDB..."
    docker compose exec -T mariadb mariadb -u root -p"${DB_ROOT_PASSWORD:-mariadb_root_pass}" < "$DUMP_FILE"
    echo "  [OK] Basis data berhasil dipulihkan."
fi

rm -rf "$RESTORE_TMP"

echo "[OK] Proses pemulihan selesai. Menjalankan pemeriksaan status..."
if [ -f "./status.sh" ]; then
    ./status.sh
fi
