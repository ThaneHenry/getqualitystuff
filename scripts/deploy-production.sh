#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRODUCTION_URL="${PRODUCTION_URL:-https://getqualitystuff.com/}"
MODE="${1:-}"
REMOTE_CONNECTION_DELAY="${REMOTE_CONNECTION_DELAY:-15}"
REMOTE_RETRY_DELAY="${REMOTE_RETRY_DELAY:-30}"

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

git diff --check
git diff --cached --check

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
    "$@"
}

pause_between_remote_steps() {
    echo "Waiting ${REMOTE_CONNECTION_DELAY} seconds before the next DreamHost connection..."
    sleep "$REMOTE_CONNECTION_DELAY"
}

echo
echo "Backing up the primary production database..."
run_remote_step "Database backup" "${ROOT_DIR}/scripts/backup-dreamhost-db.sh"

echo
pause_between_remote_steps
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
pause_between_remote_steps
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
