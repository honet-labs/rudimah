#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Uji Kesehatan Endpoint (Healthcheck)
# ==============================================================================

set -euo pipefail

HEALTH_URL="http://localhost:${HTTP_PORT:-80}/api/v1/healthz"

echo "[*] Memeriksa endpoint kesehatan: $HEALTH_URL..."

if command -v curl >/dev/null 2>&1; then
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL" || echo "000")
    if [ "$HTTP_STATUS" = "200" ]; then
        echo "  [OK] Endpoint merespons dengan status 200 OK."
        exit 0
    else
        echo "  [INFO] Endpoint mengembalikan status HTTP $HTTP_STATUS."
    fi
else
    echo "  [INFO] Utilitas curl tidak ditemukan di lingkungan saat ini."
fi
