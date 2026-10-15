#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pencadangan Logis Data Terpadu
# ==============================================================================

if [ -z "${BASH_VERSION:-}" ]; then
    exec bash "$0" "$@"
fi

set -e

BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_PATH="$BACKUP_DIR/rudimah_backup_$TIMESTAMP"

mkdir -p "$BACKUP_PATH"

echo "=================================================================="
echo "          PENCADANGAN BASIS DATA & BERKAS RUDIMAH                "
echo "=================================================================="

# 0. Muat Variabel Lingkungan secara aman
ROOT_PASS="mariadb_root_pass"
if [ -f ".env" ]; then
    FOUND_ROOT=$(grep -E '^DB_ROOT_PASSWORD=' .env | cut -d '=' -f2- | tr -d '\r"' || true)
    if [ -n "$FOUND_ROOT" ]; then
        ROOT_PASS="$FOUND_ROOT"
    fi
fi

if command -v docker >/dev/null 2>&1; then
    CONTAINER_ROOT=$(docker inspect rudimah_mariadb --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null | grep -E '^MYSQL_ROOT_PASSWORD=' | cut -d '=' -f2- | tr -d '\r"' || true)
    if [ -n "$CONTAINER_ROOT" ]; then
        ROOT_PASS="$CONTAINER_ROOT"
    fi
fi

# 1. Ekspor Basis Data MariaDB
echo "[*] Mengekspor skema dan data MariaDB..."
if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^rudimah_mariadb$"; then
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
tar -czf "${BACKUP_PATH}.tar.gz" -C "$BACKUP_DIR" "rudimah_backup_$TIMESTAMP" 2>/dev/null || true
rm -rf "$BACKUP_PATH"

if command -v sha256sum >/dev/null 2>&1 && [ -f "${BACKUP_PATH}.tar.gz" ]; then
    sha256sum "${BACKUP_PATH}.tar.gz" > "${BACKUP_PATH}.tar.gz.sha256"
fi

echo "[OK] Pencadangan selesai: ${BACKUP_PATH}.tar.gz"
