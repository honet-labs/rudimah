#!/usr/bin/env bash
# ==============================================================================
# RUDIMAH - Skrip Deteksi Otomatis & Tuning Performa Sistem Host
# Mendeteksi OS, CPU Cores, dan RAM untuk optimalisasi MariaDB, PHP-FPM,
# Nginx, dan Redis secara dinamis dan aman.
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "$ROOT_DIR"

echo "=================================================================="
echo "          DETEKSI SPESIFIKASI & AUTO-TUNING PERFORMA RUDIMAH     "
echo "=================================================================="

# 1. Deteksi Sistem Operasi (OS & Arsitektur)
OS_NAME="Linux"
if [ -f /etc/os-release ]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_NAME="${PRETTY_NAME:-$NAME $VERSION}"
elif command -v lsb_release >/dev/null 2>&1; then
    OS_NAME=$(lsb_release -ds 2>/dev/null || echo "Linux")
elif [ -f /etc/redhat-release ]; then
    OS_NAME=$(cat /etc/redhat-release)
elif [ -f /etc/debian_version ]; then
    OS_NAME="Debian $(cat /etc/debian_version)"
else
    OS_NAME="$(uname -s) $(uname -r)"
fi
KERNEL_VER="$(uname -r)"
ARCH="$(uname -m)"

# 2. Deteksi CPU Cores
CPU_CORES=1
if command -v nproc >/dev/null 2>&1; then
    CPU_CORES=$(nproc)
elif [ -f /proc/cpuinfo ]; then
    CPU_CORES=$(grep -c ^processor /proc/cpuinfo || echo 1)
elif command -v sysctl >/dev/null 2>&1; then
    CPU_CORES=$(sysctl -n hw.ncpu 2>/dev/null || echo 1)
fi
[ "$CPU_CORES" -lt 1 ] && CPU_CORES=1

CPU_MODEL=$(grep -m1 "model name" /proc/cpuinfo 2>/dev/null | cut -d: -f2- | sed 's/^[ \t]*//' || true)
[ -z "$CPU_MODEL" ] && CPU_MODEL="Generic Processor"

# 3. Deteksi Total RAM / Memori Sistem (dalam MB)
TOTAL_RAM_KB=0
if [ -f /proc/meminfo ]; then
    TOTAL_RAM_KB=$(awk '/MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)
elif command -v sysctl >/dev/null 2>&1; then
    TOTAL_RAM_BYTES=$(sysctl -n hw.memsize 2>/dev/null || echo 0)
    TOTAL_RAM_KB=$(( TOTAL_RAM_BYTES / 1024 ))
fi

if [ -z "$TOTAL_RAM_KB" ] || [ "$TOTAL_RAM_KB" -le 0 ]; then
    if command -v free >/dev/null 2>&1; then
        TOTAL_RAM_MB=$(free -m 2>/dev/null | awk '/Mem:/ {print $2}')
        TOTAL_RAM_KB=$(( TOTAL_RAM_MB * 1024 ))
    fi
fi

TOTAL_RAM_MB=$(( TOTAL_RAM_KB / 1024 ))
# Default fallback jika deteksi gagal (misal container terbatas)
if [ "$TOTAL_RAM_MB" -lt 512 ]; then
    TOTAL_RAM_MB=2048
fi

TOTAL_RAM_GB=$(awk -v mb="$TOTAL_RAM_MB" 'BEGIN {printf "%.1f", mb/1024}')

# 4. Deteksi Ruang Disk
DISK_AVAIL_MB=$(df -m . 2>/dev/null | awk 'NR==2 {print $4}' || echo 0)
DISK_AVAIL_GB=$(awk -v mb="$DISK_AVAIL_MB" 'BEGIN {printf "%.1f", mb/1024}')

echo "[*] Spesifikasi Sistem Host Terdeteksi:"
echo "    - Sistem Operasi : $OS_NAME ($ARCH, Kernel $KERNEL_VER)"
echo "    - Prosessor (CPU): $CPU_CORES Core ($CPU_MODEL)"
echo "    - Memori (RAM)   : ${TOTAL_RAM_MB} MB (~${TOTAL_RAM_GB} GB)"
echo "    - Ruang Disk Sisa: ~${DISK_AVAIL_GB} GB"
echo ""

# ==============================================================================
# KALKULASI PARAMETER TUNING
# ==============================================================================

# --- MariaDB InnoDB Tuning ---
if [ "$TOTAL_RAM_MB" -le 2048 ]; then
    INNODB_BUFFER_POOL="256M"
    INNODB_LOG_FILE="64M"
    INNODB_INSTANCES=1
elif [ "$TOTAL_RAM_MB" -le 4096 ]; then
    INNODB_BUFFER_POOL="1024M"
    INNODB_LOG_FILE="128M"
    INNODB_INSTANCES=$(( CPU_CORES > 4 ? 4 : CPU_CORES ))
elif [ "$TOTAL_RAM_MB" -le 8192 ]; then
    INNODB_BUFFER_POOL="3072M"
    INNODB_LOG_FILE="256M"
    INNODB_INSTANCES=$(( CPU_CORES > 4 ? 4 : CPU_CORES ))
elif [ "$TOTAL_RAM_MB" -le 16384 ]; then
    INNODB_BUFFER_POOL="6144M"
    INNODB_LOG_FILE="512M"
    INNODB_INSTANCES=$(( CPU_CORES > 8 ? 8 : CPU_CORES ))
else
    CALC_POOL=$(( (TOTAL_RAM_MB * 45) / 100 ))
    INNODB_BUFFER_POOL="${CALC_POOL}M"
    INNODB_LOG_FILE="512M"
    INNODB_INSTANCES=$(( CPU_CORES > 8 ? 8 : CPU_CORES ))
fi
[ "$INNODB_INSTANCES" -lt 1 ] && INNODB_INSTANCES=1

MARIADB_MAX_CONN=$(( 80 + (CPU_CORES * 25) ))
[ "$MARIADB_MAX_CONN" -lt 100 ] && MARIADB_MAX_CONN=100
[ "$MARIADB_MAX_CONN" -gt 400 ] && MARIADB_MAX_CONN=400

TABLE_OPEN_CACHE=$(( CPU_CORES * 400 ))
[ "$TABLE_OPEN_CACHE" -lt 400 ] && TABLE_OPEN_CACHE=400
[ "$TABLE_OPEN_CACHE" -gt 4000 ] && TABLE_OPEN_CACHE=4000

# --- PHP-FPM Pool Tuning ---
# Alokasi ~30% RAM untuk PHP-FPM pool, ~35MB per worker
CALC_CHILDREN=$(( (TOTAL_RAM_MB * 30 / 100) / 35 ))
if [ "$CALC_CHILDREN" -lt 15 ]; then
    PM_MAX_CHILDREN=15
elif [ "$CALC_CHILDREN" -gt 150 ]; then
    PM_MAX_CHILDREN=150
else
    PM_MAX_CHILDREN=$CALC_CHILDREN
fi

PM_START_SERVERS=$(( CPU_CORES * 2 ))
[ "$PM_START_SERVERS" -lt 4 ] && PM_START_SERVERS=4
HALF_CHILDREN=$(( PM_MAX_CHILDREN / 2 ))
[ "$PM_START_SERVERS" -gt "$HALF_CHILDREN" ] && PM_START_SERVERS=$HALF_CHILDREN

PM_MIN_SPARE=$(( CPU_CORES ))
[ "$PM_MIN_SPARE" -lt 2 ] && PM_MIN_SPARE=2
[ "$PM_MIN_SPARE" -gt "$PM_START_SERVERS" ] && PM_MIN_SPARE=$PM_START_SERVERS

PM_MAX_SPARE=$(( CPU_CORES * 3 ))
[ "$PM_MAX_SPARE" -lt 6 ] && PM_MAX_SPARE=6
[ "$PM_MAX_SPARE" -gt "$PM_MAX_CHILDREN" ] && PM_MAX_SPARE=$PM_MAX_CHILDREN

LISTEN_BACKLOG=$(( CPU_CORES * 256 ))
[ "$LISTEN_BACKLOG" -lt 1024 ] && LISTEN_BACKLOG=1024

# --- PHP Runtime Limits (php.ini) ---
if [ "$TOTAL_RAM_MB" -le 2048 ]; then
    PHP_MEM_LIMIT="256M"
    OPCACHE_MEM="128"
elif [ "$TOTAL_RAM_MB" -le 8192 ]; then
    PHP_MEM_LIMIT="384M"
    OPCACHE_MEM="256"
else
    PHP_MEM_LIMIT="512M"
    OPCACHE_MEM="384"
fi

# --- Redis Memory Limit ---
if [ "$TOTAL_RAM_MB" -le 2048 ]; then
    REDIS_MAXMEM="128mb"
elif [ "$TOTAL_RAM_MB" -le 4096 ]; then
    REDIS_MAXMEM="256mb"
elif [ "$TOTAL_RAM_MB" -le 8192 ]; then
    REDIS_MAXMEM="512mb"
else
    REDIS_MAXMEM="1024mb"
fi

# --- Nginx FastCGI Keepalive ---
NGINX_KEEPALIVE=$(( CPU_CORES * 16 ))
[ "$NGINX_KEEPALIVE" -lt 32 ] && NGINX_KEEPALIVE=32
[ "$NGINX_KEEPALIVE" -gt 128 ] && NGINX_KEEPALIVE=128

echo "[*] Profil Tuning Dihitung:"
echo "    - MariaDB Buffer Pool     : $INNODB_BUFFER_POOL ($INNODB_INSTANCES instances, log $INNODB_LOG_FILE)"
echo "    - MariaDB Max Connections : $MARIADB_MAX_CONN"
echo "    - PHP-FPM Workers (Pool)  : $PM_MAX_CHILDREN (start: $PM_START_SERVERS, spare: $PM_MIN_SPARE-$PM_MAX_SPARE)"
echo "    - PHP Memory Limit        : $PHP_MEM_LIMIT (Opcache: ${OPCACHE_MEM}M)"
echo "    - Redis Max Memory        : $REDIS_MAXMEM"
echo "    - Nginx Keepalive Upstream: $NGINX_KEEPALIVE"
echo ""

# ==============================================================================
# PENERAPAN KONFIGURASI KE FILE SISTEM
# ==============================================================================

# 1. Update docker/mariadb/custom.cnf
mkdir -p docker/mariadb
cat > docker/mariadb/custom.cnf << EOF
[mysqld]
# Konfigurasi Tuning MariaDB Teroptimasi Otomatis RUDIMAH
# Dihasilkan pada $(date) berdasarkan spesifikasi sistem: ${TOTAL_RAM_MB}MB RAM, ${CPU_CORES} CPU Core

default-storage-engine = InnoDB
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Tuning InnoDB Buffer Pool
innodb_buffer_pool_size = ${INNODB_BUFFER_POOL}
innodb_buffer_pool_instances = ${INNODB_INSTANCES}
innodb_log_file_size = ${INNODB_LOG_FILE}
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Koneksi & Buffer Kueri
max_connections = ${MARIADB_MAX_CONN}
table_open_cache = ${TABLE_OPEN_CACHE}
wait_timeout = 600
interactive_timeout = 600

# Logging Slow Query
slow_query_log = 1
long_query_time = 2
EOF
echo "  [OK] Konfigurasi MariaDB diperbarui (docker/mariadb/custom.cnf)"

# 2. Update docker/php/zz-docker.conf
mkdir -p docker/php
cat > docker/php/zz-docker.conf << EOF
[global]
daemonize = no
error_log = /proc/self/fd/2
log_level = warning

[www]
listen = 9000
listen.backlog = ${LISTEN_BACKLOG}
user = www-data
group = www-data

pm = dynamic
pm.max_children = ${PM_MAX_CHILDREN}
pm.start_servers = ${PM_START_SERVERS}
pm.min_spare_servers = ${PM_MIN_SPARE}
pm.max_spare_servers = ${PM_MAX_SPARE}
pm.max_requests = 1000

pm.status_path = /fpm-status
ping.path = /fpm-ping

catch_workers_output = yes
decorate_workers_output = no
clear_env = no
EOF
echo "  [OK] Konfigurasi PHP-FPM Pool diperbarui (docker/php/zz-docker.conf)"

# 3. Update docker/php/php.ini
cat > docker/php/php.ini << EOF
[PHP]
memory_limit = ${PHP_MEM_LIMIT}
upload_max_filesize = 20M
post_max_size = 25M
max_execution_time = 60
date.timezone = Asia/Jakarta
expose_php = Off

[opcache]
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = ${OPCACHE_MEM}
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
EOF
echo "  [OK] Konfigurasi PHP Runtime diperbarui (docker/php/php.ini)"

# 4. Update docker/redis/redis.conf
mkdir -p docker/redis
cat > docker/redis/redis.conf << EOF
# Konfigurasi Redis RUDIMAH Teroptimasi Otomatis
bind 0.0.0.0
protected-mode no
port 6379

# Memori & Kebijakan Eviction
maxmemory ${REDIS_MAXMEM}
maxmemory-policy noeviction

# Persistensi AOF
appendonly yes
appendfsync everysec
EOF
echo "  [OK] Konfigurasi Redis diperbarui (docker/redis/redis.conf)"

# 5. Update Keepalive di docker/nginx/http.conf jika ada
if [ -f docker/nginx/http.conf ]; then
    sed -i "s/keepalive [0-9]\+;/keepalive ${NGINX_KEEPALIVE};/" docker/nginx/http.conf 2>/dev/null || true
    echo "  [OK] Konfigurasi Nginx FastCGI keepalive diperbarui ($NGINX_KEEPALIVE)"
fi

echo ""
echo "[OK] Seluruh parameter performa berhasil dituning sesuai spesifikasi perangkat keras!"
echo "=================================================================="
