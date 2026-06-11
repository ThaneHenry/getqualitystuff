#!/usr/bin/env bash
set -euo pipefail

REMOTE_USER="ikinone"
REMOTE_HOST="iad1-shared-e1-28.dreamhost.com"
REMOTE_PORT="22"
REMOTE_PATH="/home/ikinone/getqualitystuff.com/"
SSH_COMMAND="${DREAMHOST_SSH_COMMAND:-ssh -p ${REMOTE_PORT} -o PreferredAuthentications=password -o PubkeyAuthentication=no}"

DRY_RUN_FLAG="${1:---dry-run}"

if [[ "$DRY_RUN_FLAG" != "--dry-run" && "$DRY_RUN_FLAG" != "--live" ]]; then
  echo "Usage: scripts/deploy-dreamhost.sh [--dry-run|--live]"
  exit 1
fi

RSYNC_FLAGS=(-avz --delete)

if [[ "$DRY_RUN_FLAG" == "--dry-run" ]]; then
  RSYNC_FLAGS+=(--dry-run)
fi

rsync "${RSYNC_FLAGS[@]}" \
  -e "$SSH_COMMAND" \
  --exclude='.git/' \
  --exclude='.gitignore' \
  --exclude='.kilo/' \
  --exclude='.vscode/' \
  --exclude='.DS_Store' \
  --exclude='.dh-diag' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='README.md' \
  --exclude='backups/' \
  --include='data/' \
  --include='data/buyitforlife.json' \
  --exclude='data/*' \
  --include='scripts/' \
  --include='scripts/import_buyitforlife.php' \
  --include='scripts/backfill_item_images.php' \
  --exclude='scripts/*' \
  --exclude='storage/*.sqlite' \
  --exclude='storage/*.sqlite-*' \
  --exclude='storage/.local-server.pid' \
  --exclude='storage/mail.log' \
  --exclude='storage/item-images/' \
  --exclude='public/uploads/*' \
  ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"
