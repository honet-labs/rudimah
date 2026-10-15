#!/usr/bin/env bash
set -e

echo "[*] Menginisialisasi basis data dual connection platform dan pekan..."

mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE:-rudimah_platform}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS \`${DB_PEKAN_DATABASE:-rudimah_pekan}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE:-rudimah_platform}\`.* TO '${MYSQL_USER}'@'%';
    GRANT ALL PRIVILEGES ON \`${DB_PEKAN_DATABASE:-rudimah_pekan}\`.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "[*] Inisialisasi basis data dual connection selesai."
