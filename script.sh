#!/usr/bin/env bash
set -euo pipefail

# script.sh - Tự động cấu hình cơ sở dữ liệu và hoàn tất cài đặt
# Sử dụng: ./script.sh --dbhost host --dbport port --dbuser user --dbpw pw --dbname name --dbtablepre pre

PROG="$(basename "$0")"

DBHOST="${DBHOST:-127.0.0.1}"
DBPORT="${DBPORT:-3306}"
DBUSER="${DBUSER:-root}"
DBPW="${DBPW:-}" 
DBNAME="${DBNAME:-test}"
DBTABLEPRE="${DBTABLEPRE:-pre_}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.3}"
SERVER_HOST="127.0.0.1"
SERVER_PORT="8001"
BASE_URL="http://$SERVER_HOST:$SERVER_PORT"
LOGFILE="./install_server.log"

usage(){
  cat <<EOF
Usage: $PROG [--dbhost host] [--dbport port] [--dbuser user] [--dbpw pw] [--dbname name] [--dbtablepre pre]
Environment variables fallback: DBHOST,DBPORT,DBUSER,DBPW,DBNAME,DBTABLEPRE
This script will:
  - start a local PHP dev server (php8.3) in background
  - POST DB params to the installer and run SQL creation step
  - create admin account interactively (or via env ADMIN_NAME/ADMIN_PW)
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dbhost) DBHOST="$2"; shift 2;;
    --dbport) DBPORT="$2"; shift 2;;
    --dbuser) DBUSER="$2"; shift 2;;
    --dbpw) DBPW="$2"; shift 2;;
    --dbname) DBNAME="$2"; shift 2;;
    --dbtablepre) DBTABLEPRE="$2"; shift 2;;
    --only-env) ONLY_ENV=1; shift 1;;
    -h|--help) usage; exit 0;;
    *) echo "Unknown arg: $1"; usage; exit 1;;
  esac
done

ONEW=${ONLY_ENV:-0}

# Auto-generate credentials when not provided
rand(){
  # generate a random lowercase alnum string of given length
  local len=${1:-8}
  # temporarily disable pipefail to avoid SIGPIPE causing script exit
  set +o pipefail || true
  tr -dc 'a-z0-9' </dev/urandom | head -c "$len"
  # restore pipefail
  set -o pipefail
}
if [ -z "$DBPW" ]; then
  # generate safe random user/pw if password not provided
  DBUSER="dbuser_$(rand 6)"
  DBPW="$(rand 16)"
fi
if [ -z "$DBNAME" ] || [ "$DBNAME" = "test" ]; then
  DBNAME="db_$(rand 6)"
fi
if [ -z "$DBTABLEPRE" ] || [ "$DBTABLEPRE" = "pre_" ]; then
  DBTABLEPRE="pre_$(rand 4)_"
fi

echo "[info] Using DB: $DBUSER@$DBHOST:$DBPORT, database=$DBNAME, prefix=$DBTABLEPRE"

# If only env requested, write .env and exit
ENVFILE="./.env"
write_env(){
  cat > "$ENVFILE" <<EOF
# Auto-generated .env
DB_HOST=$DBHOST
DB_PORT=$DBPORT
DB_USER=$DBUSER
DB_PW=$DBPW
DB_NAME=$DBNAME
DB_TABLE_PREFIX=$DBTABLEPRE
PHP_BIN=$PHP_BIN
BASE_URL=$BASE_URL
EOF
  chmod 600 "$ENVFILE" || true
  echo "[info] Wrote $ENVFILE"
  echo "--- .env contents ---"
  sed -n '1,200p' "$ENVFILE"
}

if [ "${ONLY_ENV:-0}" -eq 1 ] ; then
  write_env
  exit 0
fi

start_server(){
  if pgrep -f "${PHP_BIN} -S 0.0.0.0:${SERVER_PORT}" >/dev/null; then
    echo "[info] PHP dev server already running on port ${SERVER_PORT}"
    return
  fi
  echo "[info] Starting PHP dev server with ${PHP_BIN} on ${SERVER_PORT}..."
  nohup "$PHP_BIN" -S 0.0.0.0:${SERVER_PORT} -t . >"$LOGFILE" 2>&1 &
  sleep 1
  echo "[info] Server started (logs -> $LOGFILE)"
}

stop_server(){
  pkill -f "${PHP_BIN} -S 0.0.0.0:${SERVER_PORT}" || true
}

wait_for_http(){
  for i in {1..10}; do
    if curl -sSf "$BASE_URL/index.php/install/check" >/dev/null 2>&1; then
      echo "[info] Installer reachable"
      return 0
    fi
    sleep 1
  done
  echo "[error] Installer not reachable at $BASE_URL" >&2
  return 1
}

do_setup(){
  echo "[info] Submitting DB config to installer..."
  RESP=$(curl -sS -X POST \
    -d "dbhost=$DBHOST&dbport=$DBPORT&dbuser=$DBUSER&dbpw=$DBPW&dbname=$DBNAME&dbtablepre=$DBTABLEPRE" \
    "$BASE_URL/index.php/install/create/setup" )
  # Basic check: installer will return HTML page; look for success marker
  if echo "$RESP" | grep -q "数据库参数配置成功\|数据库参数配置成功请继续安装数据\|数据库参数配置成功"; then
    echo "[info] DB params accepted by installer"
  else
    echo "[warn] Installer response did not contain success marker - saving response to /tmp/install_setup.html"
    echo "$RESP" > /tmp/install_setup.html
    echo "[warn] Inspect /tmp/install_setup.html for details"
  fi
}

do_create_tables(){
  echo "[info] Triggering table creation (create/step)..."
  RESP=$(curl -sS "$BASE_URL/index.php/install/create/step")
  echo "[info] create step response: $RESP"
}

do_create_admin(){
  ADMIN_NAME="${ADMIN_NAME:-admin}"
  ADMIN_PW="${ADMIN_PW:-admin123}"
  echo "[info] Creating admin account: $ADMIN_NAME"
  RESP=$(curl -sS -X POST -d "account_name=$ADMIN_NAME&account_pw=$ADMIN_PW&account_rpw=$ADMIN_PW" "$BASE_URL/index.php/install/finish")
  echo "[info] finish response saved to /tmp/install_finish.html"
  echo "$RESP" > /tmp/install_finish.html
}

print_nginx_instructions(){
  cat <<'NG'
Nginx + PHP-FPM deployment notes (example):

server {
    listen 80;
    server_name jubilant-goldfish-gxqrrjr7rjgj29r7j-8001.app.github.dev;
    root /var/www/install; # point to your project root
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock; # or 127.0.0.1:9000
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}

Ensure php8.3-fpm is installed and running (sudo apt install php8.3-fpm).
NG
}

main(){
  start_server
  wait_for_http
  do_setup
  do_create_tables
  do_create_admin
  echo "[done] Installation attempts finished. Check /tmp/install_setup.html and /tmp/install_finish.html if needed."
  print_nginx_instructions
}

main "$@"
