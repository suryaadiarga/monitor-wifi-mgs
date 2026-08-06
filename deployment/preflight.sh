#!/usr/bin/env bash
set -Eeuo pipefail

MODE="plan"
APP_DIR="${APP_DIR:-/var/www/isp-manager}"
MIN_DISK_GB="${MIN_DISK_GB:-10}"
MIN_MEMORY_MB="${MIN_MEMORY_MB:-1900}"
REPORT_DIR="${REPORT_DIR:-/var/log/isp-manager/preflight}"

usage() {
  cat <<'EOF'
Usage: preflight.sh [--plan|--apply]

--plan   Audit only and write nothing (default).
--apply  Audit and save a timestamped report. Requires root.
EOF
}

for arg in "$@"; do
  case "$arg" in
    --plan) MODE="plan" ;;
    --apply) MODE="apply" ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $arg" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ "$MODE" == "apply" && "$EUID" -ne 0 ]]; then
  echo "ERROR: --apply requires root." >&2
  exit 1
fi

report_path=""
if [[ "$MODE" == "apply" ]]; then
  install -d -m 0750 "$REPORT_DIR"
  report_path="$REPORT_DIR/preflight-$(date +%Y%m%d-%H%M%S).log"
  exec > >(tee "$report_path") 2>&1
fi

failures=0
warnings=0

ok() { printf 'OK    %s\n' "$*"; }
warn() { printf 'WARN  %s\n' "$*"; warnings=$((warnings + 1)); }
fail() { printf 'FAIL  %s\n' "$*"; failures=$((failures + 1)); }

echo "ISP Manager preflight"
echo "timestamp=$(date --iso-8601=seconds) mode=$MODE host=$(hostname -f 2>/dev/null || hostname)"
echo "app_dir=$APP_DIR"

if [[ -r /etc/os-release ]]; then
  # shellcheck disable=SC1091
  source /etc/os-release
  if [[ "${ID:-}" == "ubuntu" && "${VERSION_ID:-}" == "24.04" ]]; then
    ok "Ubuntu 24.04 detected"
  else
    fail "Expected Ubuntu 24.04; detected ${PRETTY_NAME:-unknown}"
  fi
else
  fail "/etc/os-release is not readable"
fi

available_kb="$(df -Pk "${APP_DIR%/*}" 2>/dev/null | awk 'NR==2 {print $4}' || true)"
if [[ -z "$available_kb" ]]; then
  available_kb="$(df -Pk / | awk 'NR==2 {print $4}')"
fi
available_gb=$((available_kb / 1024 / 1024))
if (( available_gb >= MIN_DISK_GB )); then
  ok "Free disk ${available_gb} GiB"
else
  fail "Free disk ${available_gb} GiB; minimum ${MIN_DISK_GB} GiB"
fi

memory_mb="$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)"
if (( memory_mb >= MIN_MEMORY_MB )); then
  ok "Memory ${memory_mb} MiB"
else
  warn "Memory ${memory_mb} MiB; ${MIN_MEMORY_MB} MiB or more is recommended"
fi

if getent hosts archive.ubuntu.com >/dev/null 2>&1; then
  ok "DNS resolution works"
else
  fail "Cannot resolve archive.ubuntu.com"
fi

for port in 80 443 3000 3306 6379 1812 1813 27017 7547 7557 7567 3001; do
  listener="$(ss -H -lntu "sport = :$port" 2>/dev/null || true)"
  if [[ -n "$listener" ]]; then
    warn "Port $port already has a listener: $(echo "$listener" | head -n1)"
  else
    ok "Port $port is free"
  fi
done

for path in "$APP_DIR" /etc/nginx/sites-enabled/isp-manager.conf /etc/freeradius/3.0/mods-enabled/sql /etc/genieacs/genieacs.env; do
  if [[ -e "$path" ]]; then
    warn "Existing path will be preserved/backed up before update: $path"
  fi
done

if [[ -n "${RADIUS_ALLOWED_IPS:-}" ]]; then
  if grep -Eq '(^|,)[[:space:]]*(0\.0\.0\.0/0|::/0)[[:space:]]*(,|$)' <<<"$RADIUS_ALLOWED_IPS"; then
    fail "RADIUS_ALLOWED_IPS may not contain a global CIDR"
  else
    ok "RADIUS allowlist is configured"
  fi
else
  warn "RADIUS_ALLOWED_IPS is empty; UDP 1812/1813 will remain closed"
fi

echo "summary failures=$failures warnings=$warnings"
if [[ "$MODE" == "apply" ]]; then
  chmod 0640 "$report_path"
  echo "report=$report_path"
fi
(( failures == 0 ))
