#!/usr/bin/env bash
set -euo pipefail

REMOTE_USER="ikinone"
REMOTE_HOST="iad1-shared-e1-28.dreamhost.com"
REMOTE_PORT="22"
REMOTE_DB="/home/ikinone/getqualitystuff.com/storage/getqualitystuff.sqlite"
SSH_COMMAND="${DREAMHOST_SSH_COMMAND:-ssh -p ${REMOTE_PORT} -o PreferredAuthentications=password -o PubkeyAuthentication=no}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/backups/dreamhost"
TIMESTAMP="$(date +%Y-%m-%d-%H%M%S)"
BACKUP_FILE="${BACKUP_DIR}/getqualitystuff-${TIMESTAMP}.sqlite"

mkdir -p "$BACKUP_DIR"

rsync -avz \
  -e "$SSH_COMMAND" \
  "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DB}" \
  "$BACKUP_FILE"

echo "Database backup saved to:"
echo "$BACKUP_FILE"
