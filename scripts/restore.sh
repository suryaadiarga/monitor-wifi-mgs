#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

MODE="plan"
SNAPSHOT=""
CONFIRM=""
MONGO_DROP=0
BACKUP_CONFIG_FILE="${BACKUP_CONFIG_FILE:-/etc/isp-manager/backup.env}"

load_config_defaults() {
  local file="$1" key value
  [[ -r "$file" ]] || return 0
  while IFS='=' read -r key value; do
    [[ "$key" =~ ^[A-Z][A-Z0-9_]*$ ]] || continue
    case "$key" in
      APP_DIR|BACKUP_ROOT|BACKUP_ENCRYPTION_KEY_FILE|RADIUS_DB_HOST|RADIUS_DB_PORT|RADIUS_DB_NAME|RADIUS_DB_USER|RADIUS_DB_PASSWORD|MONGO_URI) ;;
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
BACKUP_ENCRYPTION_KEY_FILE="${BACKUP_ENCRYPTION_KEY_FILE:-/etc/isp-manager/backup-passphrase}"
MONGO_URI="${MONGO_URI:-mongodb://127.0.0.1:27017/genieacs}"

usage() {
  cat <<'EOF'
Usage:
  restore.sh --snapshot /absolute/path [--plan]
  restore.sh --snapshot /absolute/path --apply --confirm RESTORE:<snapshot-name>

Optional destructive MongoDB replacement:
  add --mongo-drop and confirm RESTORE:<snapshot-name>:DROP_MONGO

Restore always verifies checksums and creates a fresh safety backup first.
EOF
}

while (($#)); do
  case "$1" in
    --snapshot) SNAPSHOT="${2:-}"; shift 2 ;;
    --confirm) CONFIRM="${2:-}"; shift 2 ;;
    --plan) MODE="plan"; shift ;;
    --apply) MODE="apply"; shift ;;
    --mongo-drop) MONGO_DROP=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

[[ -n "$SNAPSHOT" ]] || { usage >&2; exit 2; }
SNAPSHOT="$(realpath -e "$SNAPSHOT")"
[[ -d "$SNAPSHOT" && -f "$SNAPSHOT/SHA256SUMS" ]] || { echo "ERROR: invalid snapshot." >&2; exit 1; }
snapshot_name="$(basename "$SNAPSHOT")"
expected="RESTORE:$snapshot_name"
(( MONGO_DROP == 1 )) && expected="${expected}:DROP_MONGO"

echo "Snapshot: $SNAPSHOT"
echo "Target application: $APP_DIR"
echo "Mongo mode: $([[ $MONGO_DROP == 1 ]] && echo replace-collections || echo merge-without-drop)"
echo "The MariaDB SQL dump may recreate tables. No action has run yet."
(cd "$SNAPSHOT" && sha256sum -c SHA256SUMS)

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  create a new safety backup"
  echo "PLAN  enter Laravel maintenance mode and stop app workers"
  echo "PLAN  restore MariaDB, MongoDB, application files, encrypted .env, and encrypted service configuration"
  echo "PLAN  required confirmation for apply: $expected"
  exit 0
fi

[[ "$EUID" -eq 0 ]] || { echo "ERROR: --apply requires root." >&2; exit 1; }
[[ "$CONFIRM" == "$expected" ]] || { echo "ERROR: confirmation mismatch; expected exactly: $expected" >&2; exit 1; }
[[ -s "$BACKUP_ENCRYPTION_KEY_FILE" ]] || { echo "ERROR: backup key is missing." >&2; exit 1; }

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$script_dir/backup.sh" --apply

laravel_env="$APP_DIR/backend/.env"
env_get() {
  local key="$1" file="$2" value
  [[ -r "$file" ]] || return 1
  value="$(sed -nE "s/^${key}=(.*)$/\1/p" "$file" | tail -n1)"
  if [[ "$value" == \"*\" && "$value" == *\" ]]; then value="${value:1:${#value}-2}"; fi
  if [[ "$value" == \'*\' && "$value" == *\' ]]; then value="${value:1:${#value}-2}"; fi
  printf '%s' "$value"
}

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
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ && "$RADIUS_DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || { echo "ERROR: invalid database name." >&2; exit 1; }

mysql_cnf="$(mktemp)"
mongo_config="$(mktemp)"
config_tar="$(mktemp)"
env_tar="$(mktemp)"
services=(isp-manager-queue.service isp-manager-scheduler.service isp-manager-frontend.service)
RESTORE_SUCCESS=0

cleanup() {
  rm -f "$mysql_cnf" "$mongo_config" "$config_tar" "$env_tar"
  if [[ "$RESTORE_SUCCESS" == "1" ]]; then
    systemctl start "${services[@]}" >/dev/null 2>&1 || true
    if [[ -x "$APP_DIR/backend/artisan" ]]; then
      (cd "$APP_DIR/backend" && sudo -u www-data php artisan up) >/dev/null 2>&1 || true
    fi
  else
    echo "ERROR: restore did not complete; maintenance mode and stopped workers are preserved for safe investigation." >&2
  fi
}
trap cleanup EXIT

if [[ -x "$APP_DIR/backend/artisan" ]]; then
  (cd "$APP_DIR/backend" && sudo -u www-data php artisan down --retry=60) || true
fi
systemctl stop "${services[@]}" >/dev/null 2>&1 || true

mysql_option_file() {
  local host="$1" port="$2" user="$3" password="$4" escaped
  escaped="${password//\\/\\\\}"
  escaped="${escaped//\"/\\\"}"
  printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\nprotocol=tcp\n' "$host" "$port" "$user" "$escaped" >"$mysql_cnf"
  chmod 0600 "$mysql_cnf"
}

restore_database() {
  local archive="$1" host="$2" port="$3" user="$4" password="$5" database="$6"
  [[ -f "$archive" ]] || return 0
  mysql_option_file "$host" "$port" "$user" "$password"
  gpg --batch --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt "$archive" | gzip -dc | mariadb --defaults-extra-file="$mysql_cnf" "$database"
}

restore_database "$SNAPSHOT/app-mariadb.sql.gz.gpg" "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASSWORD" "$DB_NAME"
restore_database "$SNAPSHOT/radius-mariadb.sql.gz.gpg" "$RADIUS_DB_HOST" "$RADIUS_DB_PORT" "$RADIUS_DB_USER" "$RADIUS_DB_PASSWORD" "$RADIUS_DB_NAME"

if [[ -f "$SNAPSHOT/mongodb.archive.gz.gpg" ]]; then
  escaped_mongo="${MONGO_URI//\'/\'\'}"
  printf "uri: '%s'\n" "$escaped_mongo" >"$mongo_config"
  mongo_args=(--config="$mongo_config" --archive --gzip)
  (( MONGO_DROP == 1 )) && mongo_args+=(--drop)
  gpg --batch --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt "$SNAPSHOT/mongodb.archive.gz.gpg" | mongorestore "${mongo_args[@]}"
fi

if [[ -f "$SNAPSHOT/application.tar.gz.gpg" ]]; then
  gpg --batch --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt "$SNAPSHOT/application.tar.gz.gpg" | tar -xzf - -C "$(dirname "$APP_DIR")" --no-same-owner
fi
if [[ -f "$SNAPSHOT/laravel-env.gpg" ]]; then
  install -d -m 0750 -o www-data -g www-data "$APP_DIR/backend"
  gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt --output "$laravel_env" "$SNAPSHOT/laravel-env.gpg"
  chown root:www-data "$laravel_env"
  chmod 0640 "$laravel_env"
fi
if [[ -f "$SNAPSHOT/application-envs.tar.gz.gpg" ]]; then
  gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt --output "$env_tar" "$SNAPSHOT/application-envs.tar.gz.gpg"
  tar -xzf "$env_tar" -C "$APP_DIR" --no-same-owner
fi
if [[ -f "$SNAPSHOT/system-configs.tar.gz.gpg" ]]; then
  gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$BACKUP_ENCRYPTION_KEY_FILE" \
    --decrypt --output "$config_tar" "$SNAPSHOT/system-configs.tar.gz.gpg"
  tar -xzf "$config_tar" -C /
fi

chown -R www-data:www-data "$APP_DIR/backend/storage" "$APP_DIR/backend/bootstrap/cache" "$APP_DIR/frontend/.next" 2>/dev/null || true
if [[ -x "$APP_DIR/backend/artisan" ]]; then
  (cd "$APP_DIR/backend" && sudo -u www-data php artisan optimize:clear)
fi
systemctl daemon-reload
RESTORE_SUCCESS=1
cleanup
trap - EXIT
echo "Restore completed. Run deployment/deploy.sh --plan, then --apply, to reconcile dependencies and migrations."
