#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRODUCTION_URL="${PRODUCTION_URL:-https://getqualitystuff.com/}"
MODE="${1:-}"
REMOTE_RETRY_DELAY="${REMOTE_RETRY_DELAY:-30}"
REMOTE_USER="ikinone"
REMOTE_HOST="iad1-shared-e1-28.dreamhost.com"
REMOTE_PORT="22"
REMOTE_PATH="/home/ikinone/getqualitystuff.com"
SSH_CONTROL_PATH="${TMPDIR:-/tmp}/getqualitystuff-deploy-$$.sock"

if [[ "$MODE" != "" && "$MODE" != "--check-only" ]]; then
    echo "Usage: scripts/deploy-production.sh [--check-only]"
    exit 1
fi

cd "$ROOT_DIR"

for command in curl git php rsync ssh; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Required command is not available: ${command}" >&2
        exit 1
    fi
done

if [[ -n "$(git status --short)" ]]; then
    echo "Deployment refused: the working tree has uncommitted changes." >&2
    git status --short
    echo "Review and commit the intended production changes before deploying." >&2
    exit 1
fi

echo "Checking PHP syntax..."
while IFS= read -r file; do
    php -l "$file" >/dev/null
done < <(git ls-files '*.php')

php -r '
    if (!extension_loaded("curl") || !extension_loaded("gd") || !function_exists("imagewebp")) {
        fwrite(STDERR, "PHP cURL and GD with WebP support are required.\n");
        exit(1);
    }
'

git diff --check
git diff --cached --check
php scripts/audit_css.php

COMMIT="$(git rev-parse --short HEAD)"
BRANCH="$(git branch --show-current)"
echo "Local checks passed for ${BRANCH:-detached HEAD} at ${COMMIT}."

if [[ "$MODE" == "--check-only" ]]; then
    exit 0
fi

run_remote_step() {
    local description="$1"
    shift

    if "$@"; then
        return 0
    fi

    echo
    echo "${description} failed. Retrying in ${REMOTE_RETRY_DELAY} seconds..." >&2
    sleep "$REMOTE_RETRY_DELAY"

    if ! check_ssh_connection; then
        echo "The shared DreamHost SSH connection closed. Deployment stopped before retrying." >&2
        echo "Wait a few minutes, confirm SSH access, then run the deployment again." >&2
        return 1
    fi

    "$@"
}

check_ssh_connection() {
    ssh -p "$REMOTE_PORT" -S "$SSH_CONTROL_PATH" -O check \
        "${REMOTE_USER}@${REMOTE_HOST}" >/dev/null 2>&1
}

close_ssh_connection() {
    if [[ -S "$SSH_CONTROL_PATH" ]]; then
        ssh -p "$REMOTE_PORT" -S "$SSH_CONTROL_PATH" -O exit \
            "${REMOTE_USER}@${REMOTE_HOST}" >/dev/null 2>&1 || true
    fi
    rm -f "$SSH_CONTROL_PATH"
}

trap close_ssh_connection EXIT

echo
echo "Opening one shared DreamHost SSH connection for this deployment..."
echo "You should only be prompted for the DreamHost password once."
rm -f "$SSH_CONTROL_PATH"
if ! ssh \
    -p "$REMOTE_PORT" \
    -o PreferredAuthentications=password \
    -o PubkeyAuthentication=no \
    -o NumberOfPasswordPrompts=1 \
    -o ConnectionAttempts=1 \
    -o ControlMaster=yes \
    -o ControlPersist=600 \
    -o ControlPath="$SSH_CONTROL_PATH" \
    -o ConnectTimeout=20 \
    -o ServerAliveInterval=30 \
    -o ServerAliveCountMax=3 \
    -fN \
    "${REMOTE_USER}@${REMOTE_HOST}"; then
    echo "DreamHost closed or rejected the initial SSH connection." >&2
    echo "Wait a few minutes before retrying. If it continues, test direct SSH access:" >&2
    echo "  ssh -p ${REMOTE_PORT} ${REMOTE_USER}@${REMOTE_HOST}" >&2
    exit 1
fi

if ! check_ssh_connection; then
    echo "Unable to establish a reusable DreamHost SSH connection." >&2
    echo "Confirm the password and DreamHost SSH availability, then try again." >&2
    exit 1
fi

export DREAMHOST_SSH_COMMAND="ssh -p ${REMOTE_PORT} -S ${SSH_CONTROL_PATH} -o ControlMaster=no -o BatchMode=yes"

echo
echo "Checking production configuration..."
if ! ssh \
    -p "$REMOTE_PORT" \
    -S "$SSH_CONTROL_PATH" \
    -o ControlMaster=no \
    -o BatchMode=yes \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "REMOTE_PATH='$REMOTE_PATH' bash -s" <<'REMOTE_CONFIG_CHECK'
set -euo pipefail

ENV_FILE="${REMOTE_PATH}/.env.local"
if [[ ! -r "$ENV_FILE" ]]; then
    echo "Production configuration missing: ${ENV_FILE}" >&2
    exit 1
fi

for name in \
    GET_QUALITY_STUFF_APP_URL \
    GET_QUALITY_STUFF_GOOGLE_CLIENT_ID \
    GET_QUALITY_STUFF_GOOGLE_CLIENT_SECRET
do
    if ! grep -Eq "^${name}=.+" "$ENV_FILE"; then
        echo "Production configuration is missing ${name}." >&2
        exit 1
    fi
done

echo "Production app URL and Google sign-in credentials are configured."
php -r '
    if (!extension_loaded("curl") || !extension_loaded("gd") || !function_exists("imagewebp")) {
        fwrite(STDERR, "Production PHP requires cURL and GD with WebP support.\n");
        exit(1);
    }
'
REMOTE_CONFIG_CHECK
then
    echo "Deployment stopped before uploading code." >&2
    echo "Create ${REMOTE_PATH}/.env.local on DreamHost with production values, then retry." >&2
    exit 1
fi

echo
echo "Backing up the primary production database..."
run_remote_step "Database backup" "${ROOT_DIR}/scripts/backup-dreamhost-db.sh"

echo
echo "Previewing the production code deployment..."
run_remote_step "Deployment preview" "${ROOT_DIR}/scripts/deploy-dreamhost.sh" --dry-run

echo
echo "This will deploy commit ${COMMIT} to ${PRODUCTION_URL}."
echo "The primary production database and uploaded files will be preserved."
read -r -p "Type DEPLOY to continue: " CONFIRMATION

if [[ "$CONFIRMATION" != "DEPLOY" ]]; then
    echo "Deployment cancelled."
    exit 1
fi

echo
echo "Deploying code to production..."
run_remote_step "Production deployment" "${ROOT_DIR}/scripts/deploy-dreamhost.sh" --live

echo
echo "Verifying production..."
HTTP_CODE="$(
    curl \
        --location \
        --silent \
        --show-error \
        --output /dev/null \
        --write-out '%{http_code}' \
        --max-time 20 \
        "$PRODUCTION_URL"
)"

if [[ ! "$HTTP_CODE" =~ ^2[0-9][0-9]$ ]]; then
    echo "Deployment completed, but production verification returned HTTP ${HTTP_CODE}." >&2
    exit 1
fi

echo "Production deployment verified with HTTP ${HTTP_CODE}."
echo "Deployed commit ${COMMIT}."
