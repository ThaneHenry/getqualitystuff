#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PUBLIC_DIR="$ROOT_DIR/public"
ROUTER_FILE="$PUBLIC_DIR/router.php"
STORAGE_DIR="$ROOT_DIR/storage"
PID_FILE="$STORAGE_DIR/.local-server.pid"

HOST=${HOST:-127.0.0.1}
PORT=${PORT:-8000}

export GET_QUALITY_STUFF_ADMIN_EMAIL=${GET_QUALITY_STUFF_ADMIN_EMAIL:-local-admin@getqualitystuff.test}
export GET_QUALITY_STUFF_ADMIN_PASSWORD=${GET_QUALITY_STUFF_ADMIN_PASSWORD:-local-admin-password}

if ! command -v php >/dev/null 2>&1; then
    printf '%s\n' 'Error: PHP is not installed or is not available on PATH.' >&2
    printf '%s\n' 'Install PHP 8.2+ with PDO SQLite enabled, then try again.' >&2
    exit 1
fi

PHP_VERSION_ID=$(php -r 'echo PHP_VERSION_ID;')
if [ "$PHP_VERSION_ID" -lt 80200 ]; then
    printf 'Error: PHP 8.2+ is required. Found PHP %s.\n' "$(php -r 'echo PHP_VERSION;')" >&2
    exit 1
fi

if ! php -m | grep -Eq '^pdo_sqlite$'; then
    printf '%s\n' 'Error: PHP extension pdo_sqlite is required.' >&2
    exit 1
fi

mkdir -p "$STORAGE_DIR"

if [ -f "$PID_FILE" ]; then
    EXISTING_PID=$(cat "$PID_FILE" 2>/dev/null || true)
    if [ -n "$EXISTING_PID" ] && kill -0 "$EXISTING_PID" 2>/dev/null; then
        printf 'Error: the local server supervisor is already running with PID %s.\n' "$EXISTING_PID" >&2
        exit 1
    fi
    rm -f "$PID_FILE"
fi

printf 'Starting Get Quality Stuff at http://%s:%s\n' "$HOST" "$PORT"
printf 'Local admin: %s / %s\n' "$GET_QUALITY_STUFF_ADMIN_EMAIL" "$GET_QUALITY_STUFF_ADMIN_PASSWORD"
printf '%s\n' 'Press Ctrl+C to stop the server.'

SERVER_PID=

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill -TERM "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    rm -f "$PID_FILE"
}

trap cleanup EXIT INT TERM HUP

printf '%s\n' "$$" > "$PID_FILE"
php -S "$HOST:$PORT" -t "$PUBLIC_DIR" "$ROUTER_FILE" &
SERVER_PID=$!
wait "$SERVER_PID"
