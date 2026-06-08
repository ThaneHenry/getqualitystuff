#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REMOTE_USER="${REMOTE_USER:-ikinone}"
REMOTE_HOST="${REMOTE_HOST:-iad1-shared-e1-28.dreamhost.com}"
REMOTE_PORT="${REMOTE_PORT:-22}"
REMOTE_PATH="${REMOTE_PATH:-/home/ikinone/getqualitystuff.com}"
EXPECTED_ITEMS=449

cd "$ROOT_DIR"

for command in git php rsync ssh; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Required command is not available: ${command}" >&2
        exit 1
    fi
done

if [[ -n "$(git status --short)" ]]; then
    echo "Production import refused: the working tree has uncommitted changes." >&2
    echo "Commit and deploy the reviewed importer, dataset, and schema changes first." >&2
    exit 1
fi

php -r '
    $catalog = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($catalog) || count($catalog) !== 449) {
        fwrite(STDERR, "The committed Buy It For Life dataset must contain exactly 449 items.\n");
        exit(1);
    }
' "$ROOT_DIR/data/buyitforlife.json"

echo "Backing up the current production database..."
"$ROOT_DIR/scripts/backup-dreamhost-db.sh"

echo
echo "This will import ${EXPECTED_ITEMS} items into the existing production database."
echo "It will not upload or replace the local database."
read -r -p "Type IMPORT to continue: " CONFIRMATION
if [[ "$CONFIRMATION" != "IMPORT" ]]; then
    echo "Production import cancelled."
    exit 1
fi

ssh \
    -p "$REMOTE_PORT" \
    -o PreferredAuthentications=password \
    -o PubkeyAuthentication=no \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "cd '$REMOTE_PATH' && php scripts/import_buyitforlife.php && php -r '
        require \"app/repository.php\";
        \$db = db();
        \$items = (int) \$db->query(\"SELECT COUNT(*) FROM items\")->fetchColumn();
        \$links = (int) \$db->query(\"SELECT COUNT(*) FROM item_purchase_links\")->fetchColumn();
        \$integrity = \$db->query(\"PRAGMA integrity_check\")->fetchColumn();
        echo \"Production totals: {\$items} items, {\$links} purchase links.\\n\";
        if (\$items < 449 || \$links < 449 || \$integrity !== \"ok\") {
            fwrite(STDERR, \"Production import verification failed.\\n\");
            exit(1);
        }
    '"

echo "Production Buy It For Life import completed and verified."
