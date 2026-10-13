#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Pembaruan Sertifikat SSL Let's Encrypt
# ==============================================================================

set -euo pipefail

echo "[*] Menjalankan pembaruan sertifikat SSL Let's Encrypt..."

if command -v docker >/dev/null 2>&1 && docker ps | grep -q "rudimah_nginx"; then
    docker compose run --rm certbot renew --webroot -w /var/www/certbot --quiet
    docker compose exec nginx nginx -s reload
    echo "[OK] Sertifikat SSL berhasil diperbarui dan Nginx telah dimuat ulang."
else
    echo "[INFO] Kontainer Nginx belum berjalan aktif."
fi
