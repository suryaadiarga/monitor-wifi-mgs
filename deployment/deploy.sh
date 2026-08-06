#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

MODE="$([[ "${APPLY:-0}" == "1" ]] && echo apply || echo plan)"
ENV_FILE=""
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

usage() {
  cat <<'EOF'
Usage: deploy.sh [--plan|--apply] [--env-file /path/to/deploy.env]

Default is a read-only plan. Applying requires either --apply or APPLY=1.
The env file is trusted administrator-owned shell syntax; start from
deployment/.env.example and keep it outside the repository.
EOF
}

while (($#)); do
  case "$1" in
    --plan) MODE="plan"; shift ;;
    --apply) MODE="apply"; shift ;;
    --env-file) ENV_FILE="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ -n "$ENV_FILE" ]]; then
  [[ -r "$ENV_FILE" ]] || { echo "ERROR: cannot read env file: $ENV_FILE" >&2; exit 1; }
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

APP_NAME="${APP_NAME:-ISP Manager}"
APP_DIR="${APP_DIR:-/var/www/isp-manager}"
SERVER_NAME="${SERVER_NAME:-isp.example.net}"
APP_URL="${APP_URL:-https://$SERVER_NAME}"
APP_ENV="${APP_ENV:-production}"
APP_DEBUG="${APP_DEBUG:-false}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-isp_manager}"
DB_USER="${DB_USER:-isp_manager}"
DB_PASSWORD="${DB_PASSWORD:-}"
RADIUS_DB_HOST="${RADIUS_DB_HOST:-127.0.0.1}"
RADIUS_DB_PORT="${RADIUS_DB_PORT:-3306}"
RADIUS_DB_NAME="${RADIUS_DB_NAME:-isp_manager_radius}"
RADIUS_DB_USER="${RADIUS_DB_USER:-isp_radius}"
RADIUS_DB_PASSWORD="${RADIUS_DB_PASSWORD:-}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"
REDIS_PASSWORD="${REDIS_PASSWORD:-}"
NODE_MAJOR="${NODE_MAJOR:-22}"
MONGODB_MAJOR="${MONGODB_MAJOR:-8.0}"
AUTO_SWAP_MB="${AUTO_SWAP_MB:-0}"
ENABLE_HTTPS="${ENABLE_HTTPS:-0}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
RUN_SEEDER="${RUN_SEEDER:-0}"
SEED_CLASS="${SEED_CLASS:-DatabaseSeeder}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/isp-manager}"
BACKUP_ENCRYPTION_KEY_FILE="${BACKUP_ENCRYPTION_KEY_FILE:-/etc/isp-manager/backup-passphrase}"
BACKUP_DAILY_RETENTION_DAYS="${BACKUP_DAILY_RETENTION_DAYS:-14}"
BACKUP_WEEKLY_RETENTION_DAYS="${BACKUP_WEEKLY_RETENTION_DAYS:-56}"
BACKUP_MONTHLY_RETENTION_DAYS="${BACKUP_MONTHLY_RETENTION_DAYS:-365}"

if [[ "$MODE" == "apply" && "$EUID" -ne 0 ]]; then
  echo "ERROR: apply mode requires root." >&2
  exit 1
fi
[[ "$APP_DIR" == /* && "$APP_DIR" != "/" ]] || { echo "ERROR: APP_DIR must be a safe absolute path." >&2; exit 1; }
for name in DB_NAME DB_USER RADIUS_DB_NAME RADIUS_DB_USER; do
  value="${!name}"
  [[ "$value" =~ ^[A-Za-z0-9_]+$ ]] || { echo "ERROR: invalid $name." >&2; exit 1; }
done
if [[ "$MODE" == "apply" ]]; then
  [[ -n "$DB_PASSWORD" && -n "$RADIUS_DB_PASSWORD" ]] || { echo "ERROR: DB_PASSWORD and RADIUS_DB_PASSWORD are required." >&2; exit 1; }
  [[ "$DB_PASSWORD" =~ ^[A-Za-z0-9._~!@#%^+=,:/-]{16,}$ ]] || { echo "ERROR: DB_PASSWORD must be at least 16 characters and use deployment-safe characters." >&2; exit 1; }
  [[ "$RADIUS_DB_PASSWORD" =~ ^[A-Za-z0-9._~!@#%^+=,:/-]{16,}$ ]] || { echo "ERROR: RADIUS_DB_PASSWORD must be at least 16 characters and use deployment-safe characters." >&2; exit 1; }
  [[ "$DB_HOST" == "127.0.0.1" && "$RADIUS_DB_HOST" == "127.0.0.1" ]] || { echo "ERROR: this native installer creates local databases; DB hosts must be 127.0.0.1." >&2; exit 1; }
  [[ "$SERVER_NAME" != "isp.example.net" ]] || { echo "ERROR: set SERVER_NAME before apply." >&2; exit 1; }
  [[ "$AUTO_SWAP_MB" =~ ^[0-9]+$ ]] || { echo "ERROR: AUTO_SWAP_MB must be a non-negative integer." >&2; exit 1; }
fi

run() {
  if [[ "$MODE" == "plan" ]]; then
    printf 'PLAN  '; printf '%q ' "$@"; printf '\n'
  else
    "$@"
  fi
}

echo "ISP Manager deployment mode=$MODE"
echo "source=$SOURCE_DIR target=$APP_DIR server_name=$SERVER_NAME"
echo "Safety: no database DROP, migrate:fresh, UFW reset, or chmod 777 operation is used."

if [[ "$MODE" == "apply" ]]; then
  APP_DIR="$APP_DIR" RADIUS_ALLOWED_IPS="${RADIUS_ALLOWED_IPS:-}" REPORT_DIR=/var/log/isp-manager/preflight \
    "$SCRIPT_DIR/preflight.sh" --apply
else
  APP_DIR="$APP_DIR" RADIUS_ALLOWED_IPS="${RADIUS_ALLOWED_IPS:-}" "$SCRIPT_DIR/preflight.sh" --plan || true
fi

if [[ "$MODE" == "apply" && ! -s "$BACKUP_ENCRYPTION_KEY_FILE" ]]; then
  install -d -m 0700 "$(dirname "$BACKUP_ENCRYPTION_KEY_FILE")"
  openssl rand -base64 48 >"$BACKUP_ENCRYPTION_KEY_FILE"
  chmod 0600 "$BACKUP_ENCRYPTION_KEY_FILE"
  echo "Generated backup encryption key; copy it to an offline vault after deployment: $BACKUP_ENCRYPTION_KEY_FILE"
fi

if [[ "$MODE" == "apply" && -f "$APP_DIR/backend/.env" ]]; then
  echo "Existing installation detected; creating a verified backup before changes."
  if systemctl list-unit-files mongod.service --no-legend 2>/dev/null | grep -q '^mongod.service'; then
    systemctl start mongod
  fi
  APP_DIR="$APP_DIR" BACKUP_ROOT="$BACKUP_ROOT" BACKUP_ENCRYPTION_KEY_FILE="$BACKUP_ENCRYPTION_KEY_FILE" \
    RADIUS_DB_HOST="$RADIUS_DB_HOST" RADIUS_DB_PORT="$RADIUS_DB_PORT" RADIUS_DB_NAME="$RADIUS_DB_NAME" \
    RADIUS_DB_USER="$RADIUS_DB_USER" RADIUS_DB_PASSWORD="$RADIUS_DB_PASSWORD" \
    "$SOURCE_DIR/scripts/backup.sh" --apply
elif [[ "$MODE" == "plan" ]]; then
  echo "PLAN  back up any existing installation before package/configuration changes"
fi

if [[ "$MODE" == "apply" && "$AUTO_SWAP_MB" -gt 0 ]]; then
  swapfile=/swapfile.isp-manager
  if ! swapon --show=NAME --noheadings | grep -Fxq "$swapfile"; then
    if [[ ! -e "$swapfile" ]]; then
      fallocate -l "${AUTO_SWAP_MB}M" "$swapfile" || dd if=/dev/zero of="$swapfile" bs=1M count="$AUTO_SWAP_MB" status=progress
      chmod 0600 "$swapfile"
      mkswap "$swapfile"
    fi
    swapon "$swapfile"
  fi
  grep -Fq "$swapfile none swap sw 0 0" /etc/fstab || printf '%s\n' "$swapfile none swap sw 0 0" >>/etc/fstab
  echo "Swap profile ready: $swapfile (${AUTO_SWAP_MB} MiB requested)."
elif [[ "$MODE" == "plan" && "$AUTO_SWAP_MB" -gt 0 ]]; then
  echo "PLAN  create and persist a dedicated ${AUTO_SWAP_MB} MiB swapfile if it is not already active"
fi

base_packages=(
  ca-certificates curl gnupg jq lsb-release openssl rsync unzip git ufw logrotate gpg sudo
  nginx mariadb-server mariadb-client redis-server
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-redis php8.3-curl php8.3-mbstring php8.3-xml
  php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-snmp
  freeradius freeradius-mysql freeradius-utils
  wireguard strongswan strongswan-pki certbot python3-certbot-nginx
  snmp snmpd prometheus-node-exporter
)

run apt-get update
run env DEBIAN_FRONTEND=noninteractive apt-get install -y "${base_packages[@]}"

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  install Node.js ${NODE_MAJOR}.x from the signed NodeSource repository"
  echo "PLAN  install MongoDB ${MONGODB_MAJOR}.x from the signed MongoDB repository"
else
  install -d -m 0755 /etc/apt/keyrings
  curl --fail --silent --show-error --location https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
    | gpg --dearmor --yes --output /etc/apt/keyrings/nodesource.gpg
  chmod 0644 /etc/apt/keyrings/nodesource.gpg
  printf 'deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_%s.x nodistro main\n' "$NODE_MAJOR" \
    >/etc/apt/sources.list.d/nodesource.list

  curl --fail --silent --show-error --location "https://www.mongodb.org/static/pgp/server-${MONGODB_MAJOR}.asc" \
    | gpg --dearmor --yes --output "/etc/apt/keyrings/mongodb-server-${MONGODB_MAJOR}.gpg"
  chmod 0644 "/etc/apt/keyrings/mongodb-server-${MONGODB_MAJOR}.gpg"
  printf 'deb [arch=amd64,arm64 signed-by=/etc/apt/keyrings/mongodb-server-%s.gpg] https://repo.mongodb.org/apt/ubuntu noble/mongodb-org/%s multiverse\n' \
    "$MONGODB_MAJOR" "$MONGODB_MAJOR" >"/etc/apt/sources.list.d/mongodb-org-${MONGODB_MAJOR}.list"
  apt-get update
  env DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs mongodb-org mongodb-database-tools
fi

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  install Composer after validating the official installer SHA-384 signature"
elif ! command -v composer >/dev/null; then
  composer_installer="$(mktemp)"
  curl --fail --silent --show-error https://getcomposer.org/installer --output "$composer_installer"
  expected_sig="$(curl --fail --silent --show-error https://composer.github.io/installer.sig)"
  actual_sig="$(php -r "echo hash_file('sha384', '$composer_installer');")"
  [[ "$expected_sig" == "$actual_sig" ]] || { rm -f "$composer_installer"; echo "ERROR: Composer installer signature mismatch." >&2; exit 1; }
  php "$composer_installer" --quiet --install-dir=/usr/local/bin --filename=composer
  rm -f "$composer_installer"
fi

run systemctl enable --now nginx php8.3-fpm mariadb redis-server
run systemctl enable mongod

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  install GenieACS globally and run it as the unprivileged genieacs user"
else
  if ! command -v genieacs-cwmp >/dev/null; then npm install --global genieacs; fi
  if ! id -u genieacs >/dev/null 2>&1; then
    useradd --system --home-dir /var/lib/genieacs --create-home --shell /usr/sbin/nologin genieacs
  fi
  genieacs_package_dir="$(npm root --global)/genieacs"
  chown -R root:genieacs "$genieacs_package_dir"
  chmod -R g+rX "$genieacs_package_dir"
  install -d -m 0750 -o genieacs -g genieacs /var/log/genieacs /var/lib/genieacs
  install -d -m 0750 -o root -g genieacs /etc/genieacs
  if [[ ! -f /etc/genieacs/genieacs.env ]]; then
    jwt_secret="$(openssl rand -hex 32)"
    cat >/etc/genieacs/genieacs.env <<EOF
GENIEACS_MONGODB_CONNECTION_URL=mongodb://127.0.0.1:27017/genieacs
GENIEACS_CWMP_ACCESS_LOG_FILE=/var/log/genieacs/cwmp-access.log
GENIEACS_NBI_ACCESS_LOG_FILE=/var/log/genieacs/nbi-access.log
GENIEACS_FS_ACCESS_LOG_FILE=/var/log/genieacs/fs-access.log
GENIEACS_UI_ACCESS_LOG_FILE=/var/log/genieacs/ui-access.log
GENIEACS_DEBUG_FILE=/var/log/genieacs/debug.yaml
GENIEACS_EXT_DIR=/var/lib/genieacs/ext
GENIEACS_UI_JWT_SECRET=$jwt_secret
GENIEACS_CWMP_INTERFACE=0.0.0.0
GENIEACS_NBI_INTERFACE=127.0.0.1
GENIEACS_FS_INTERFACE=127.0.0.1
GENIEACS_UI_INTERFACE=127.0.0.1
GENIEACS_UI_PORT=3001
EOF
  fi
  if grep -qE '^[[:space:]]*GENIEACS_UI_PORT=' /etc/genieacs/genieacs.env; then
    sed -ri 's/^[[:space:]]*GENIEACS_UI_PORT=.*/GENIEACS_UI_PORT=3001/' /etc/genieacs/genieacs.env
  else
    printf '%s\n' 'GENIEACS_UI_PORT=3001' >>/etc/genieacs/genieacs.env
  fi
  if grep -qE '^[[:space:]]*GENIEACS_UI_INTERFACE=' /etc/genieacs/genieacs.env; then
    sed -ri 's/^[[:space:]]*GENIEACS_UI_INTERFACE=.*/GENIEACS_UI_INTERFACE=127.0.0.1/' /etc/genieacs/genieacs.env
  else
    printf '%s\n' 'GENIEACS_UI_INTERFACE=127.0.0.1' >>/etc/genieacs/genieacs.env
  fi
  chown root:genieacs /etc/genieacs/genieacs.env
  chmod 0640 /etc/genieacs/genieacs.env
fi

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  create MariaDB databases/users with CREATE IF NOT EXISTS; no existing database is dropped"
else
  sql_escape() { local value="$1"; value="${value//\\/\\\\}"; value="${value//\'/\'\'}"; printf '%s' "$value"; }
  app_password="$(sql_escape "$DB_PASSWORD")"
  radius_password="$(sql_escape "$RADIUS_DB_PASSWORD")"
  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$app_password';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$app_password';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
CREATE DATABASE IF NOT EXISTS \`$RADIUS_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$RADIUS_DB_USER'@'127.0.0.1' IDENTIFIED BY '$radius_password';
ALTER USER '$RADIUS_DB_USER'@'127.0.0.1' IDENTIFIED BY '$radius_password';
GRANT ALL PRIVILEGES ON \`$RADIUS_DB_NAME\`.* TO '$RADIUS_DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

  radius_table="$(mariadb --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$RADIUS_DB_NAME' AND table_name='radcheck'")"
  if [[ "$radius_table" == "0" ]]; then
    radius_schema="$(find /etc/freeradius -type f \( -path '*/mods-config/sql/main/mariadb/schema.sql' -o -path '*/mods-config/sql/main/mysql/schema.sql' \) -print -quit)"
    [[ -n "$radius_schema" ]] || { echo "ERROR: FreeRADIUS MariaDB schema not found." >&2; exit 1; }
    mariadb "$RADIUS_DB_NAME" <"$radius_schema"
  fi
fi

run install -d -m 0755 "$APP_DIR"
if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  copy repository into $APP_DIR without --delete; existing extra files are preserved"
else
  source_real="$(realpath -m "$SOURCE_DIR")"
  app_real="$(realpath -m "$APP_DIR")"
  if [[ "$source_real" != "$app_real" ]]; then
    rsync -a \
      --exclude='.git/' --exclude='backend/.env' --exclude='backend/vendor/' \
      --exclude='frontend/node_modules/' --exclude='frontend/.next/' \
      "$SOURCE_DIR/" "$APP_DIR/"
  fi
fi

set_env_value() {
  local file="$1" key="$2" value="$3" temp
  temp="$(mktemp)"
  awk -v key="$key" -v value="$value" '
    BEGIN { found=0 }
    index($0, key "=") == 1 { print key "=" value; found=1; next }
    { print }
    END { if (!found) print key "=" value }
  ' "$file" >"$temp"
  cat "$temp" >"$file"
  rm -f "$temp"
}

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  create backend/.env only if absent, inject server-side values, and generate APP_KEY without printing it"
else
  backend_env="$APP_DIR/backend/.env"
  if [[ ! -f "$backend_env" ]]; then cp "$APP_DIR/backend/.env.example" "$backend_env"; fi
  set_env_value "$backend_env" APP_NAME "\"$APP_NAME\""
  set_env_value "$backend_env" APP_ENV "$APP_ENV"
  set_env_value "$backend_env" APP_DEBUG "$APP_DEBUG"
  set_env_value "$backend_env" APP_URL "$APP_URL"
  set_env_value "$backend_env" APP_LOCALE id
  set_env_value "$backend_env" APP_FALLBACK_LOCALE id
  set_env_value "$backend_env" LOG_LEVEL warning
  set_env_value "$backend_env" DB_CONNECTION mysql
  set_env_value "$backend_env" DB_HOST "$DB_HOST"
  set_env_value "$backend_env" DB_PORT "$DB_PORT"
  set_env_value "$backend_env" DB_DATABASE "$DB_NAME"
  set_env_value "$backend_env" DB_USERNAME "$DB_USER"
  set_env_value "$backend_env" DB_PASSWORD "\"$DB_PASSWORD\""
  set_env_value "$backend_env" SESSION_DRIVER redis
  set_env_value "$backend_env" SESSION_SECURE_COOKIE "$([[ "$ENABLE_HTTPS" == "1" ]] && echo true || echo false)"
  set_env_value "$backend_env" SESSION_SAME_SITE lax
  set_env_value "$backend_env" SESSION_DOMAIN "$SERVER_NAME"
  set_env_value "$backend_env" QUEUE_CONNECTION redis
  set_env_value "$backend_env" CACHE_STORE redis
  set_env_value "$backend_env" REDIS_HOST "$REDIS_HOST"
  set_env_value "$backend_env" REDIS_PORT "$REDIS_PORT"
  set_env_value "$backend_env" REDIS_PASSWORD "$([[ -n "$REDIS_PASSWORD" ]] && printf '\"%s\"' "$REDIS_PASSWORD" || echo null)"
  set_env_value "$backend_env" SANCTUM_STATEFUL_DOMAINS "$SERVER_NAME"
  set_env_value "$backend_env" RADIUS_DB_HOST "$RADIUS_DB_HOST"
  set_env_value "$backend_env" RADIUS_DB_DRIVER mysql
  set_env_value "$backend_env" RADIUS_DB_PORT "$RADIUS_DB_PORT"
  set_env_value "$backend_env" RADIUS_DB_NAME "$RADIUS_DB_NAME"
  set_env_value "$backend_env" RADIUS_DB_DATABASE "$RADIUS_DB_NAME"
  set_env_value "$backend_env" RADIUS_DB_USERNAME "$RADIUS_DB_USER"
  set_env_value "$backend_env" RADIUS_DB_PASSWORD "\"$RADIUS_DB_PASSWORD\""
  chown root:www-data "$backend_env"
  chmod 0640 "$backend_env"
fi

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  composer install --no-dev, npm ci, Next.js build, Laravel migration, optional idempotent seeder"
else
  cd "$APP_DIR/backend"
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  chown -R root:www-data "$APP_DIR/backend"
  if ! grep -Eq '^APP_KEY=base64:.+' .env; then php artisan key:generate --force --no-interaction; fi
  install -d -m 0775 -o www-data -g www-data storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache
  sudo -u www-data php artisan migrate --force --no-interaction
  if [[ "$RUN_SEEDER" == "1" ]]; then sudo -u www-data php artisan db:seed --class="$SEED_CLASS" --force --no-interaction; fi
  sudo -u www-data php artisan storage:link --force || true
  sudo -u www-data php artisan optimize:clear
  sudo -u www-data php artisan config:cache
  sudo -u www-data php artisan route:cache
  sudo -u www-data php artisan view:cache

  cd "$APP_DIR/frontend"
  if [[ -f package-lock.json ]]; then npm ci; else npm install; fi
  npm run build
  chown -R root:www-data "$APP_DIR"
  chown -R www-data:www-data "$APP_DIR/backend/storage" "$APP_DIR/backend/bootstrap/cache" "$APP_DIR/frontend/.next"
fi

render_template() {
  local source="$1" destination="$2" content
  content="$(<"$source")"
  content="${content//@@APP_DIR@@/$APP_DIR}"
  content="${content//@@SERVER_NAME@@/$SERVER_NAME}"
  content="${content//@@RADIUS_DB_HOST@@/$RADIUS_DB_HOST}"
  content="${content//@@RADIUS_DB_PORT@@/$RADIUS_DB_PORT}"
  content="${content//@@RADIUS_DB_NAME@@/$RADIUS_DB_NAME}"
  content="${content//@@RADIUS_DB_USER@@/$RADIUS_DB_USER}"
  content="${content//@@RADIUS_DB_PASSWORD@@/$RADIUS_DB_PASSWORD}"
  printf '%s\n' "$content" >"$destination"
}

if [[ "$MODE" == "plan" ]]; then
  echo "PLAN  render Nginx, FreeRADIUS SQL, systemd, logrotate, backup and healthcheck configurations"
else
  install -d -m 0755 /etc/isp-manager /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/systemd/system
  render_template "$SCRIPT_DIR/nginx/isp-manager.conf" /etc/nginx/sites-available/isp-manager.conf
  ln -sfn /etc/nginx/sites-available/isp-manager.conf /etc/nginx/sites-enabled/isp-manager.conf
  rm -f /etc/nginx/sites-enabled/default

  radius_sql_path="$(find /etc/freeradius -type d -path '*/mods-available' -print -quit)/sql"
  [[ "$radius_sql_path" != "/sql" ]] || { echo "ERROR: FreeRADIUS mods-available not found." >&2; exit 1; }
  if [[ -e "$radius_sql_path" && ! -e "${radius_sql_path}.package" ]]; then cp -a "$radius_sql_path" "${radius_sql_path}.package"; fi
  render_template "$SCRIPT_DIR/freeradius/sql" "$radius_sql_path"
  radius_enabled="${radius_sql_path/mods-available/mods-enabled}"
  ln -sfn "$radius_sql_path" "$radius_enabled"
  chown root:freerad "$radius_sql_path"
  chmod 0640 "$radius_sql_path"

  for unit in "$SCRIPT_DIR"/systemd/*; do
    destination="/etc/systemd/system/$(basename "$unit")"
    render_template "$unit" "$destination"
    chmod 0644 "$destination"
  done
  render_template "$SCRIPT_DIR/logrotate/isp-manager" /etc/logrotate.d/isp-manager
  chmod 0644 /etc/logrotate.d/isp-manager

  install -m 0750 "$SOURCE_DIR/scripts/backup.sh" /usr/local/sbin/isp-manager-backup
  install -m 0750 "$SOURCE_DIR/scripts/restore.sh" /usr/local/sbin/isp-manager-restore
  install -m 0755 "$SOURCE_DIR/scripts/healthcheck.sh" /usr/local/sbin/isp-manager-healthcheck
  if [[ ! -s "$BACKUP_ENCRYPTION_KEY_FILE" ]]; then
    install -d -m 0700 "$(dirname "$BACKUP_ENCRYPTION_KEY_FILE")"
    openssl rand -base64 48 >"$BACKUP_ENCRYPTION_KEY_FILE"
    chmod 0600 "$BACKUP_ENCRYPTION_KEY_FILE"
  fi

  systemd_quote() { local value="$1"; value="${value//\\/\\\\}"; value="${value//\"/\\\"}"; printf '"%s"' "$value"; }
  cat >/etc/isp-manager/backup.env <<EOF
APP_DIR=$(systemd_quote "$APP_DIR")
BACKUP_ROOT=$(systemd_quote "$BACKUP_ROOT")
BACKUP_ENCRYPTION_KEY_FILE=$(systemd_quote "$BACKUP_ENCRYPTION_KEY_FILE")
BACKUP_DAILY_RETENTION_DAYS=$(systemd_quote "$BACKUP_DAILY_RETENTION_DAYS")
BACKUP_WEEKLY_RETENTION_DAYS=$(systemd_quote "$BACKUP_WEEKLY_RETENTION_DAYS")
BACKUP_MONTHLY_RETENTION_DAYS=$(systemd_quote "$BACKUP_MONTHLY_RETENTION_DAYS")
RADIUS_DB_HOST=$(systemd_quote "$RADIUS_DB_HOST")
RADIUS_DB_PORT=$(systemd_quote "$RADIUS_DB_PORT")
RADIUS_DB_NAME=$(systemd_quote "$RADIUS_DB_NAME")
RADIUS_DB_USER=$(systemd_quote "$RADIUS_DB_USER")
RADIUS_DB_PASSWORD=$(systemd_quote "$RADIUS_DB_PASSWORD")
MONGO_URI="mongodb://127.0.0.1:27017/genieacs"
EOF
  if [[ -n "${TELEGRAM_BOT_TOKEN:-}" && -n "${TELEGRAM_CHAT_ID:-}" ]]; then
    printf 'TELEGRAM_BOT_TOKEN=%s\nTELEGRAM_CHAT_ID=%s\n' \
      "$(systemd_quote "$TELEGRAM_BOT_TOKEN")" "$(systemd_quote "$TELEGRAM_CHAT_ID")" >>/etc/isp-manager/backup.env
  fi
  chmod 0600 /etc/isp-manager/backup.env
  cat >/etc/isp-manager/health.env <<EOF
APP_DIR=$(systemd_quote "$APP_DIR")
HEALTH_URL="http://127.0.0.1/up"
HEALTH_HOST=$(systemd_quote "$SERVER_NAME")
EOF
  chmod 0644 /etc/isp-manager/health.env

  nginx -t
  freeradius -XC
  systemctl daemon-reload
  systemctl start mongod
  systemctl enable freeradius
  systemctl restart freeradius
  for service in cwmp nbi fs ui; do
    systemctl enable "genieacs@${service}.service"
    systemctl restart "genieacs@${service}.service"
  done
  systemctl enable --now isp-manager-queue.service isp-manager-scheduler.service isp-manager-frontend.service
  systemctl enable --now isp-manager-backup.timer isp-manager-healthcheck.timer
  systemctl reload nginx
fi

if [[ "$MODE" == "plan" ]]; then
  RADIUS_ALLOWED_IPS="${RADIUS_ALLOWED_IPS:-}" CWMP_ALLOWED_IPS="${CWMP_ALLOWED_IPS:-}" SSH_ALLOW_FROM="${SSH_ALLOW_FROM:-}" \
    ENABLE_WIREGUARD="${ENABLE_WIREGUARD:-0}" ENABLE_IKEV2="${ENABLE_IKEV2:-0}" \
    "$SCRIPT_DIR/firewall/ufw-radius.sh" --plan
else
  RADIUS_ALLOWED_IPS="${RADIUS_ALLOWED_IPS:-}" CWMP_ALLOWED_IPS="${CWMP_ALLOWED_IPS:-}" SSH_ALLOW_FROM="${SSH_ALLOW_FROM:-}" \
    ENABLE_WIREGUARD="${ENABLE_WIREGUARD:-0}" ENABLE_IKEV2="${ENABLE_IKEV2:-0}" WIREGUARD_PORT="${WIREGUARD_PORT:-51820}" \
    "$SCRIPT_DIR/firewall/ufw-radius.sh" --apply
fi

if [[ "$ENABLE_HTTPS" == "1" ]]; then
  if [[ "$MODE" == "plan" ]]; then
    echo "PLAN  request/renew a Let's Encrypt certificate for $SERVER_NAME and enable redirect"
  else
    certbot_args=(--nginx --non-interactive --agree-tos --redirect --domain "$SERVER_NAME")
    if [[ -n "$CERTBOT_EMAIL" ]]; then
      certbot_args+=(--no-eff-email --email "$CERTBOT_EMAIL")
    else
      certbot_args+=(--register-unsafely-without-email)
    fi
    certbot "${certbot_args[@]}"
  fi
fi

if [[ "$MODE" == "apply" ]]; then
  HEALTH_URL=http://127.0.0.1/up HEALTH_HOST="$SERVER_NAME" "$SOURCE_DIR/scripts/healthcheck.sh"
  echo "Deployment applied successfully. Offline backup key: $BACKUP_ENCRYPTION_KEY_FILE"
else
  echo "Plan complete. Review it, protect the env file (chmod 600), then rerun with --apply or APPLY=1."
fi
