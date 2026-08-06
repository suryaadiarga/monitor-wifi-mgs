#!/usr/bin/env bash
set -Eeuo pipefail

HEALTH_CONFIG_FILE="${HEALTH_CONFIG_FILE:-/etc/isp-manager/health.env}"
if [[ -r "$HEALTH_CONFIG_FILE" ]]; then
  while IFS='=' read -r key value; do
    [[ "$key" =~ ^(APP_DIR|HEALTH_URL|HEALTH_HOST|DISK_WARN_PERCENT|STRICT|CHECK_CONFIG_SYNTAX)$ ]] || continue
    [[ -v "$key" ]] && continue
    if [[ "$value" == \"*\" && "$value" == *\" ]]; then value="${value:1:${#value}-2}"; fi
    value="${value//\\\"/\"}"
    value="${value//\\\\/\\}"
    printf -v "$key" '%s' "$value"
    export "$key"
  done <"$HEALTH_CONFIG_FILE"
fi

APP_DIR="${APP_DIR:-/var/www/isp-manager}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/up}"
DISK_WARN_PERCENT="${DISK_WARN_PERCENT:-85}"
STRICT="${STRICT:-0}"
CHECK_CONFIG_SYNTAX="${CHECK_CONFIG_SYNTAX:-0}"
failures=0
warnings=0

ok() { printf 'OK    %s\n' "$*"; }
warn() { printf 'WARN  %s\n' "$*"; warnings=$((warnings + 1)); }
fail() { printf 'FAIL  %s\n' "$*"; failures=$((failures + 1)); }

check_service() {
  local service="$1" required="$2"
  local load_state
  load_state="$(systemctl show --property=LoadState --value "$service" 2>/dev/null || true)"
  if [[ "$load_state" != "loaded" ]]; then
    [[ "$required" == "required" ]] && fail "$service is not installed" || warn "$service is not installed"
  elif systemctl is-active --quiet "$service"; then
    ok "$service is active"
  else
    [[ "$required" == "required" ]] && fail "$service is not active" || warn "$service is not active"
  fi
}

check_if_enabled() {
  local service="$1"
  if systemctl is-enabled --quiet "$service" 2>/dev/null || systemctl is-active --quiet "$service" 2>/dev/null; then
    check_service "$service" required
  fi
}

echo "ISP Manager healthcheck $(date --iso-8601=seconds)"
for service in nginx.service php8.3-fpm.service mariadb.service redis-server.service mongod.service freeradius.service isp-manager-queue.service isp-manager-scheduler.service isp-manager-frontend.service genieacs@cwmp.service genieacs@nbi.service genieacs@fs.service genieacs@ui.service; do
  check_service "$service" required
done
for service in wg-quick@wg0.service strongswan-starter.service prometheus-node-exporter.service snmpd.service; do
  check_if_enabled "$service"
done

curl_args=(--silent --show-error --max-time 10 --fail)
if [[ -n "${HEALTH_HOST:-}" ]]; then curl_args+=(-H "Host: ${HEALTH_HOST}"); fi
if response="$(curl "${curl_args[@]}" "$HEALTH_URL" 2>&1)"; then
  ok "HTTP health endpoint responded successfully: $HEALTH_URL"
else
  fail "HTTP health endpoint failed: $HEALTH_URL (${response:0:160})"
fi

if redis-cli -h "${REDIS_HOST:-127.0.0.1}" -p "${REDIS_PORT:-6379}" ping 2>/dev/null | grep -qx PONG; then
  ok "Redis ping"
else
  fail "Redis ping failed"
fi

if mongosh --quiet "mongodb://127.0.0.1:27017/genieacs" --eval 'if (db.runCommand({ping: 1}).ok !== 1) quit(1)' >/dev/null 2>&1; then
  ok "MongoDB ping"
else
  fail "MongoDB ping failed"
fi

genie_ui_code="$(curl --silent --show-error --max-time 10 --output /dev/null --write-out '%{http_code}' http://127.0.0.1:3001/ || true)"
if [[ "$genie_ui_code" =~ ^[234][0-9][0-9]$ ]]; then
  ok "GenieACS UI responded on 127.0.0.1:3001 (HTTP $genie_ui_code)"
else
  fail "GenieACS UI is unhealthy on 127.0.0.1:3001 (HTTP ${genie_ui_code:-000})"
fi

if [[ -f "$APP_DIR/backend/artisan" ]] && (cd "$APP_DIR/backend" && runuser -u www-data -- php artisan isp:health >/dev/null 2>&1); then
  ok "Application worker, scheduler, and router sync health"
else
  fail "Application worker, scheduler, or router sync health failed"
fi

disk_percent="$(df -P "$APP_DIR" 2>/dev/null | awk 'NR==2 {gsub(/%/, "", $5); print $5}' || true)"
if [[ "$disk_percent" =~ ^[0-9]+$ ]]; then
  if (( disk_percent >= DISK_WARN_PERCENT )); then warn "Disk usage is ${disk_percent}%"; else ok "Disk usage is ${disk_percent}%"; fi
else
  fail "Cannot read disk usage for $APP_DIR"
fi

if [[ "$CHECK_CONFIG_SYNTAX" == "1" ]]; then
  if command -v freeradius >/dev/null && freeradius -XC >/dev/null 2>&1; then
    ok "FreeRADIUS configuration syntax"
  else
    fail "FreeRADIUS configuration validation failed"
  fi
fi

echo "summary failures=$failures warnings=$warnings"
if (( failures > 0 || (STRICT == 1 && warnings > 0) )); then exit 1; fi
