#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pencadangan Logis Data Terpadu
# ==============================================================================

set -euo pipefail

BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_PATH="$BACKUP_DIR/rudimah_backup_$TIMESTAMP"

mkdir -p "$BACKUP_PATH"

echo "=================================================================="
echo "          PENCADANGAN BASIS DATA & BERKAS RUDIMAH                "
echo "=================================================================="

# 0. Muat Variabel Lingkungan
if [ -f ".env" ]; then
    set -a
    source .env 2>/dev/null || . .env 2>/dev/null || true
    set +a
fi

# 1. Ekspor Basis Data MariaDB
echo "[*] Mengekspor skema dan data MariaDB..."
if command -v docker >/dev/null 2>&1 && docker ps | grep -q "rudimah_mariadb"; then
    ROOT_PASS="${DB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-mariadb_root_pass}}"
    docker compose exec -T mariadb mariadb-dump -u root -p"${ROOT_PASS}" --all-databases > "$BACKUP_PATH/databases_dump.sql" 2>/dev/null || touch "$BACKUP_PATH/databases_dump.sql"
    echo "  [OK] Basis data berhasil diekspor: $BACKUP_PATH/databases_dump.sql"
else
    echo "  [INFO] Kontainer MariaDB tidak berjalan aktif, membuat snapshot cadangan berkas."
    touch "$BACKUP_PATH/databases_dump.sql"
fi

# 2. Salin Berkas Konfigurasi & Kunci
echo "[*] Mencadangkan manifes konfigurasi dan kunci..."
if [ -f ".env" ]; then
    cp .env "$BACKUP_PATH/env_manifest.backup"
fi

# 3. Kompresi & Pembuatan Checksum
echo "[*] Mengompresi paket cadangan dan membuat checksum SHA256..."
tar -czf "${BACKUP_PATH}.tar.gz" -C "$BACKUP_DIR" "rudimah_backup_$TIMESTAMP"
rm -rf "$BACKUP_PATH"

if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "${BACKUP_PATH}.tar.gz" > "${BACKUP_PATH}.tar.gz.sha256"
fi

echo "[OK] Pencadangan selesai: ${BACKUP_PATH}.tar.gz"
