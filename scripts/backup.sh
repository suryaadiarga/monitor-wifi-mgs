#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

MODE="plan"
BACKUP_CONFIG_FILE="${BACKUP_CONFIG_FILE:-/etc/isp-manager/backup.env}"

load_config_defaults() {
  local file="$1" key value
  [[ -r "$file" ]] || return 0
  while IFS='=' read -r key value; do
    [[ "$key" =~ ^[A-Z][A-Z0-9_]*$ ]] || continue
    case "$key" in
      APP_DIR|BACKUP_ROOT|BACKUP_ENCRYPTION_KEY_FILE|BACKUP_DAILY_RETENTION_DAYS|BACKUP_WEEKLY_RETENTION_DAYS|BACKUP_MONTHLY_RETENTION_DAYS|RADIUS_DB_HOST|RADIUS_DB_PORT|RADIUS_DB_NAME|RADIUS_DB_USER|RADIUS_DB_PASSWORD|MONGO_URI|TELEGRAM_BOT_TOKEN|TELEGRAM_CHAT_ID) ;;
      *) continue ;;
    esac
    [[ -v "$key" ]] && continue
    if [[ "$value" == \"*\" && "$value" == *\" ]]; then value="${value:1:${#value}-2}"; fi
    value="${value//\\\"/\"}"
    value="${value//\\\\/\\}"
    printf -v "$key" '%s' "$value"
    export "$key"
  done <"$file"
}

load_config_defaults "$BACKUP_CONFIG_FILE"

APP_DIR="${APP_DIR:-/var/www/isp-manager}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/isp-manager}"
BACKUP_ENCRYPTION_KEY_FILE="${BACKUP_ENCRYPTION_KEY_FILE:-/etc/isp-manager/backup-passphrase}"
BACKUP_DAILY_RETENTION_DAYS="${BACKUP_DAILY_RETENTION_DAYS:-14}"
BACKUP_WEEKLY_RETENTION_DAYS="${BACKUP_WEEKLY_RETENTION_DAYS:-56}"
BACKUP_MONTHLY_RETENTION_DAYS="${BACKUP_MONTHLY_RETENTION_DAYS:-365}"
MONGO_URI="${MONGO_URI:-mongodb://127.0.0.1:27017/genieacs}"

usage() {
  cat <<'EOF'
Usage: backup.sh [--plan|--apply]

--plan   Print the backup scope without writing data (default).
--apply  Create and verify an encrypted backup snapshot. Requires root.
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

env_get() {
  local key="$1" file="$2" value
  [[ -r "$file" ]] || return 1
  value="$(sed -nE "s/^${key}=(.*)$/\1/p" "$file" | tail -n1)"
  if [[ "$value" == \"*\" && "$value" == *\" ]]; then value="${value:1:${#value}-2}"; fi
  if [[ "$value" == \'*\' && "$value" == *\' ]]; then value="${value:1:${#value}-2}"; fi
  printf '%s' "$value"
}

laravel_env="$APP_DIR/backend/.env"
DB_HOST="${DB_HOST:-$(env_get DB_HOST "$laravel_env" || echo 127.0.0.1)}"
DB_PORT="${DB_PORT:-$(env_get DB_PORT "$laravel_env" || echo 3306)}"
DB_NAME="${DB_NAME:-$(env_get DB_DATABASE "$laravel_env" || true)}"
DB_USER="${DB_USER:-$(env_get DB_USERNAME "$laravel_env" || true)}"
DB_PASSWORD="${DB_PASSWORD:-$(env_get DB_PASSWORD "$laravel_env" || true)}"
RADIUS_DB_HOST="${RADIUS_DB_HOST:-127.0.0.1}"
RADIUS_DB_PORT="${RADIUS_DB_PORT:-3306}"
RADIUS_DB_NAME="${RADIUS_DB_NAME:-isp_manager_radius}"
RADIUS_DB_USER="${RADIUS_DB_USER:-isp_radius}"
RADIUS_DB_PASSWORD="${RADIUS_DB_PASSWORD:-}"

if [[ "$MODE" == "plan" ]]; then
  cat <<EOF
PLAN  destination: $BACKUP_ROOT
PLAN  MariaDB databases: ${DB_NAME:-<not configured>}, ${RADIUS_DB_NAME:-<not configured>}
PLAN  MongoDB archive: configured URI (credential hidden)
PLAN  application tree: $APP_DIR (excluding .env, dependencies, caches, and logs)
PLAN  encrypted secrets/config: application env files, deploy config, Nginx, FreeRADIUS, GenieACS, WireGuard, strongSwan
PLAN  retention: daily=${BACKUP_DAILY_RETENTION_DAYS}d weekly=${BACKUP_WEEKLY_RETENTION_DAYS}d monthly=${BACKUP_MONTHLY_RETENTION_DAYS}d
PLAN  no database or existing configuration will be modified
EOF
  exit 0
fi

if [[ "$EUID" -ne 0 ]]; then
  echo "ERROR: --apply requires root." >&2
  exit 1
fi
for command in tar gzip sha256sum gpg mariadb-dump flock; do
  command -v "$command" >/dev/null || { echo "ERROR: missing command: $command" >&2; exit 1; }
done
[[ "$BACKUP_ROOT" == /* && "$BACKUP_ROOT" != "/" ]] || { echo "ERROR: unsafe BACKUP_ROOT." >&2; exit 1; }
[[ -d "$APP_DIR" ]] || { echo "ERROR: application directory does not exist: $APP_DIR" >&2; exit 1; }
[[ -s "$BACKUP_ENCRYPTION_KEY_FILE" ]] || { echo "ERROR: backup key is missing: $BACKUP_ENCRYPTION_KEY_FILE" >&2; exit 1; }
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$RADIUS_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "ERROR: invalid database name." >&2; exit 1; }
for retention in "$BACKUP_DAILY_RETENTION_DAYS" "$BACKUP_WEEKLY_RETENTION_DAYS" "$BACKUP_MONTHLY_RETENTION_DAYS"; do
  [[ "$retention" =~ ^[0-9]+$ ]] || { echo "ERROR: retention values must be non-negative integers." >&2; exit 1; }
done

install -d -m 0700 "$BACKUP_ROOT" "$BACKUP_ROOT/daily" "$BACKUP_ROOT/weekly" "$BACKUP_ROOT/monthly"
exec 9>"/run/lock/isp-manager-backup.lock"
flock -n 9 || { echo "ERROR: another backup is running." >&2; exit 1; }

timestamp="$(date +%Y%m%d-%H%M%S)"
stage="$BACKUP_ROOT/.staging-${timestamp}-$$"
snapshot="$BACKUP_ROOT/daily/$timestamp"
mysql_cnf="$(mktemp)"
mongo_config="$(mktemp)"

notify_failure() {
  local status=$?
  rm -rf -- "$stage" 2>/dev/null || true
  rm -f "$mysql_cnf" "$mongo_config"
  if [[ -n "${TELEGRAM_BOT_TOKEN:-}" && -n "${TELEGRAM_CHAT_ID:-}" && -x "$(command -v curl || true)" ]]; then
    curl_cfg="$(mktemp)"
    printf 'url = "https://api.telegram.org/bot%s/sendMessage"\ndata = "chat_id=%s"\ndata = "text=ISP Manager backup FAILED on %s at %s"\nsilent\nshow-error\n' \
      "$TELEGRAM_BOT_TOKEN" "$TELEGRAM_CHAT_ID" "$(hostname)" "$timestamp" >"$curl_cfg"
    curl --config "$curl_cfg" >/dev/null 2>&1 || true
    rm -f "$curl_cfg"
  fi
  exit "$status"
}
trap notify_failure ERR INT TERM

mkdir "$stage"

mysql_option_file() {
  local host="$1" port="$2" user="$3" password="$4" output="$5" escaped
  escaped="${password//\\/\\\\}"
  escaped="${escaped//\"/\\\"}"
  printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\nprotocol=tcp\n' "$host" "$port" "$user" "$escaped" >"$output"
  chmod 0600 "$output"
}

dump_database() {
  local label="$1" host="$2" port="$3" user="$4" password="$5" database="$6"
  mysql_option_file "$host" "$port" "$user" "$password" "$mysql_cnf"
  mariadb-dump --defaults-extra-file="$mysql_cnf" --single-transaction --routines --events --triggers --hex-blob "$database" \
    | gzip -9 \
    | gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
        --symmetric --cipher-algo AES256 --output "$stage/${label}-mariadb.sql.gz.gpg"
  gpg --batch --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt "$stage/${label}-mariadb.sql.gz.gpg" | gzip -t
}

dump_database app "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASSWORD" "$DB_NAME"
dump_database radius "$RADIUS_DB_HOST" "$RADIUS_DB_PORT" "$RADIUS_DB_USER" "$RADIUS_DB_PASSWORD" "$RADIUS_DB_NAME"

if command -v mongodump >/dev/null; then
  escaped_mongo="${MONGO_URI//\'/\'\'}"
  printf "uri: '%s'\n" "$escaped_mongo" >"$mongo_config"
  chmod 0600 "$mongo_config"
  mongodump --config="$mongo_config" --archive --gzip \
    | gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
        --symmetric --cipher-algo AES256 --output "$stage/mongodb.archive.gz.gpg"
else
  echo "ERROR: mongodump is required for a complete backup." >&2
  false
fi

app_parent="$(dirname "$APP_DIR")"
app_name="$(basename "$APP_DIR")"
tar -C "$app_parent" -czf - \
  --exclude="$app_name/backend/.env" \
  --exclude="$app_name/**/.env" \
  --exclude="$app_name/**/.env.*" \
  --exclude="$app_name/backend/vendor" \
  --exclude="$app_name/backend/storage/logs/*" \
  --exclude="$app_name/backend/bootstrap/cache/*" \
  --exclude="$app_name/frontend/node_modules" \
  --exclude="$app_name/frontend/.next/cache" \
  --exclude="$app_name/.git" \
  "$app_name" \
  | gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
      --symmetric --cipher-algo AES256 --output "$stage/application.tar.gz.gpg"
gpg --batch --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
  --decrypt "$stage/application.tar.gz.gpg" | tar -tzf - >/dev/null

if [[ -f "$laravel_env" ]]; then
  gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --symmetric --cipher-algo AES256 --output "$stage/laravel-env.gpg" "$laravel_env"
fi

mapfile -d '' application_envs < <(find "$APP_DIR" -type f -name '.env*' ! -name '*.example' -printf '%P\0')
if (( ${#application_envs[@]} > 0 )); then
  tar -C "$APP_DIR" -czf - -- "${application_envs[@]}" \
    | gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
        --symmetric --cipher-algo AES256 --output "$stage/application-envs.tar.gz.gpg"
fi

config_paths=()
for path in etc/isp-manager etc/nginx etc/freeradius etc/genieacs etc/wireguard etc/ipsec.conf etc/ipsec.secrets etc/swanctl; do
  [[ -e "/$path" ]] && config_paths+=("$path")
done
if (( ${#config_paths[@]} > 0 )); then
  tar -C / -czf - "${config_paths[@]}" \
    | gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
        --symmetric --cipher-algo AES256 --output "$stage/system-configs.tar.gz.gpg"
fi

cat >"$stage/manifest.txt" <<EOF
created_at=$(date --iso-8601=seconds)
hostname=$(hostname -f 2>/dev/null || hostname)
app_dir=$APP_DIR
app_database=$DB_NAME
radius_database=$RADIUS_DB_NAME
mongo_uri=redacted
EOF
(cd "$stage" && sha256sum -- * >SHA256SUMS && sha256sum -c SHA256SUMS)

mv "$stage" "$snapshot"
if [[ "$(date +%u)" == "7" ]]; then cp -al "$snapshot" "$BACKUP_ROOT/weekly/$timestamp"; fi
if [[ "$(date +%d)" == "01" ]]; then cp -al "$snapshot" "$BACKUP_ROOT/monthly/$timestamp"; fi

find "$BACKUP_ROOT/daily" -mindepth 1 -maxdepth 1 -type d -mtime "+$BACKUP_DAILY_RETENTION_DAYS" -exec rm -rf -- {} +
find "$BACKUP_ROOT/weekly" -mindepth 1 -maxdepth 1 -type d -mtime "+$BACKUP_WEEKLY_RETENTION_DAYS" -exec rm -rf -- {} +
find "$BACKUP_ROOT/monthly" -mindepth 1 -maxdepth 1 -type d -mtime "+$BACKUP_MONTHLY_RETENTION_DAYS" -exec rm -rf -- {} +

trap - ERR INT TERM
rm -f "$mysql_cnf" "$mongo_config"
echo "Backup verified: $snapshot"
