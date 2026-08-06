#!/usr/bin/env bash
set -Eeuo pipefail

MODE=plan
for arg in "$@"; do
  case "$arg" in
    --plan) MODE=plan ;;
    --apply) MODE=apply ;;
    *) echo "Usage: $0 [--plan|--apply]" >&2; exit 2 ;;
  esac
done

if [[ "$MODE" == apply && "$EUID" -ne 0 ]]; then
  echo "ERROR: --apply requires root." >&2
  exit 1
fi

if [[ "$MODE" == plan ]]; then
  echo "PLAN  cap MariaDB buffer pool, Redis memory, MongoDB WiredTiger cache, PHP-FPM workers, and ISP Manager services"
  echo "PLAN  keep the profile reversible through dedicated config/drop-in files"
  exit 0
fi

install -d -m 0755 /etc/mysql/mariadb.conf.d
cat >/etc/mysql/mariadb.conf.d/60-isp-manager-low-memory.cnf <<'EOF'
[mysqld]
innodb_buffer_pool_size=96M
innodb_log_buffer_size=8M
max_connections=40
table_open_cache=256
performance_schema=OFF
EOF

if [[ -f /etc/php/8.3/fpm/pool.d/www.conf ]]; then
  sed -ri 's/^pm = .*/pm = dynamic/' /etc/php/8.3/fpm/pool.d/www.conf
  sed -ri 's/^pm\.max_children = .*/pm.max_children = 2/' /etc/php/8.3/fpm/pool.d/www.conf
  sed -ri 's/^pm\.start_servers = .*/pm.start_servers = 1/' /etc/php/8.3/fpm/pool.d/www.conf
  sed -ri 's/^pm\.min_spare_servers = .*/pm.min_spare_servers = 1/' /etc/php/8.3/fpm/pool.d/www.conf
  sed -ri 's/^pm\.max_spare_servers = .*/pm.max_spare_servers = 2/' /etc/php/8.3/fpm/pool.d/www.conf
fi

install -d -m 0755 /etc/systemd/system/redis-server.service.d
cat >/etc/systemd/system/redis-server.service.d/isp-manager-low-memory.conf <<'EOF'
[Service]
ExecStart=
ExecStart=/usr/bin/redis-server /etc/redis/redis.conf --supervised systemd --maxmemory 64mb --maxmemory-policy noeviction
MemoryHigh=80M
MemoryMax=112M
EOF

install -d -m 0755 /etc/systemd/system/mongod.service.d
cat >/etc/systemd/system/mongod.service.d/isp-manager-low-memory.conf <<'EOF'
[Service]
ExecStart=
ExecStart=/usr/bin/mongod --config /etc/mongod.conf --wiredTigerCacheSizeGB 0.25
MemoryHigh=384M
MemoryMax=512M
EOF

write_limit() {
  local unit="$1" high="$2" max="$3" node_options="${4:-}"
  [[ -f "/etc/systemd/system/$unit" ]] || return 0
  install -d -m 0755 "/etc/systemd/system/${unit}.d"
  {
    echo '[Service]'
    [[ -z "$node_options" ]] || printf 'Environment=NODE_OPTIONS=%s\n' "$node_options"
    printf 'MemoryHigh=%s\nMemoryMax=%s\n' "$high" "$max"
  } >"/etc/systemd/system/${unit}.d/isp-manager-low-memory.conf"
}

write_limit isp-manager-frontend.service 192M 288M --max-old-space-size=224
write_limit isp-manager-queue.service 128M 192M
write_limit isp-manager-scheduler.service 80M 128M
write_limit 'genieacs@.service' 128M 192M --max-old-space-size=144

systemctl daemon-reload
for service in mariadb php8.3-fpm redis-server mongod isp-manager-frontend isp-manager-queue isp-manager-scheduler; do
  systemctl try-restart "$service" 2>/dev/null || true
done
for service in cwmp nbi fs ui; do
  systemctl try-restart "genieacs@${service}.service" 2>/dev/null || true
done

echo "Low-memory profile applied. Dedicated files can be removed to roll it back."
