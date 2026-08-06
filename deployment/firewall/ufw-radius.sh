#!/usr/bin/env bash
set -Eeuo pipefail

MODE="plan"
RADIUS_ALLOWED_IPS="${RADIUS_ALLOWED_IPS:-}"
CWMP_ALLOWED_IPS="${CWMP_ALLOWED_IPS:-}"
SSH_ALLOW_FROM="${SSH_ALLOW_FROM:-}"
WIREGUARD_PORT="${WIREGUARD_PORT:-51820}"
ENABLE_WIREGUARD="${ENABLE_WIREGUARD:-0}"
ENABLE_IKEV2="${ENABLE_IKEV2:-0}"

for arg in "$@"; do
  case "$arg" in
    --plan) MODE="plan" ;;
    --apply) MODE="apply" ;;
    *) echo "Usage: $0 [--plan|--apply]" >&2; exit 2 ;;
  esac
done

if [[ "$MODE" == "apply" && "$EUID" -ne 0 ]]; then
  echo "ERROR: --apply requires root." >&2
  exit 1
fi

run() {
  if [[ "$MODE" == "plan" ]]; then
    printf 'PLAN  '; printf '%q ' "$@"; printf '\n'
  else
    "$@"
  fi
}

validate_cidr_list() {
  local name="$1" value="$2"
  if grep -Eq '(^|,)[[:space:]]*(0\.0\.0\.0/0|::/0)[[:space:]]*(,|$)' <<<"$value"; then
    echo "ERROR: $name cannot contain 0.0.0.0/0 or ::/0." >&2
    exit 1
  fi
}

allow_list() {
  local list="$1" port="$2" proto="$3" label="$4" item
  [[ -z "$list" ]] && return 0
  IFS=',' read -r -a items <<<"$list"
  for item in "${items[@]}"; do
    item="${item//[[:space:]]/}"
    [[ -z "$item" ]] && continue
    run ufw allow proto "$proto" from "$item" to any port "$port" comment "$label"
  done
}

validate_cidr_list RADIUS_ALLOWED_IPS "$RADIUS_ALLOWED_IPS"
validate_cidr_list CWMP_ALLOWED_IPS "$CWMP_ALLOWED_IPS"

# Add an SSH rule before changing the incoming default.
if [[ -n "$SSH_ALLOW_FROM" ]]; then
  validate_cidr_list SSH_ALLOW_FROM "$SSH_ALLOW_FROM"
  allow_list "$SSH_ALLOW_FROM" 22 tcp "ISP Manager SSH"
else
  run ufw allow 22/tcp comment "ISP Manager SSH"
fi

run ufw default deny incoming
run ufw default allow outgoing
run ufw allow 80/tcp comment "ISP Manager HTTP"
run ufw allow 443/tcp comment "ISP Manager HTTPS"

# Remove only generic public RADIUS rules, then add explicit NAS CIDRs.
if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  remove generic allow rules for 1812/udp and 1813/udp if present"
else
  ufw --force delete allow 1812/udp >/dev/null 2>&1 || true
  ufw --force delete allow 1813/udp >/dev/null 2>&1 || true
  ufw --force delete allow 7547/tcp >/dev/null 2>&1 || true
fi
allow_list "$RADIUS_ALLOWED_IPS" 1812 udp "FreeRADIUS auth NAS only"
allow_list "$RADIUS_ALLOWED_IPS" 1813 udp "FreeRADIUS accounting NAS only"

# CWMP is reachable only from the managed ONT/private network.
allow_list "$CWMP_ALLOWED_IPS" 7547 tcp "GenieACS CWMP private only"

if [[ "$ENABLE_WIREGUARD" == "1" ]]; then
  run ufw allow "${WIREGUARD_PORT}/udp" comment "WireGuard"
fi
if [[ "$ENABLE_IKEV2" == "1" ]]; then
  run ufw allow 500/udp comment "IKEv2"
  run ufw allow 4500/udp comment "IKEv2 NAT-T"
fi

run ufw --force enable
if [[ "$MODE" == "apply" ]]; then
  ufw status verbose
  if ufw status | grep -E '181(2|3)(/udp)?[[:space:]]+ALLOW[[:space:]]+(IN[[:space:]]+)?Anywhere' >/dev/null; then
    echo "ERROR: a public RADIUS allow rule still exists; delete that numbered rule explicitly." >&2
    exit 1
  fi
else
  echo "PLAN  MariaDB, Redis, MongoDB, NBI/UI, SNMP, MikroTik API, and OLT ports remain closed publicly."
fi
