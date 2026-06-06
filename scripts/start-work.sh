#!/usr/bin/env bash
set -euo pipefail
umask 077

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCAL_DB="${ROOT_DIR}/storage/getqualitystuff.sqlite"
LOCAL_BACKUP_DIR="${ROOT_DIR}/backups/local"
REMOTE_USER="${REMOTE_USER:-ikinone}"
REMOTE_HOST="${REMOTE_HOST:-iad1-shared-e1-28.dreamhost.com}"
REMOTE_PORT="${REMOTE_PORT:-22}"
REMOTE_DB="${REMOTE_DB:-/home/ikinone/getqualitystuff.com/storage/getqualitystuff.sqlite}"
MODE="${1:-}"

if [[ "$MODE" != "" && "$MODE" != "--offline" && "$MODE" != "--sync-only" ]]; then
    echo "Usage: scripts/start-work.sh [--offline|--sync-only]"
    exit 1
fi

cd "$ROOT_DIR"
mkdir -p "$(dirname "$LOCAL_DB")" "$LOCAL_BACKUP_DIR"

for command in php rsync ssh git; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Required command is not available: ${command}" >&2
        exit 1
    fi
done

if [[ -n "$(git status --short 2>/dev/null || true)" ]]; then
    echo "Note: the working tree has uncommitted changes."
fi

verify_database() {
    local database_path="$1"

    php -r '
        $path = $argv[1];
        $pdo = new PDO("sqlite:" . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($pdo->query("PRAGMA integrity_check")->fetchColumn() !== "ok") {
            fwrite(STDERR, "SQLite integrity check failed.\n");
            exit(1);
        }

        $required = ["users", "brands", "items", "categories"];
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = :type AND name = :name");
        foreach ($required as $table) {
            $stmt->execute(["type" => "table", "name" => $table]);
            if (!$stmt->fetchColumn()) {
                fwrite(STDERR, "Required table missing: {$table}\n");
                exit(1);
            }
        }
    ' "$database_path"
}

sync_production_database() {
    local timestamp
    local download_path

    timestamp="$(date +%Y-%m-%d-%H%M%S)"
    download_path="${ROOT_DIR}/storage/.getqualitystuff-production-${timestamp}.sqlite"

    if [[ -f "$LOCAL_DB" ]]; then
        cp -p "$LOCAL_DB" "${LOCAL_BACKUP_DIR}/getqualitystuff-${timestamp}.sqlite"
        echo "Backed up the current local database."
    fi
    find "$LOCAL_BACKUP_DIR" -type f -name 'getqualitystuff-*.sqlite' -mtime +30 -delete

    echo "Downloading the production database from DreamHost..."
    if ! rsync -avz \
        -e "ssh -p ${REMOTE_PORT} -o PreferredAuthentications=password -o PubkeyAuthentication=no" \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DB}" \
        "$download_path"; then
        rm -f "$download_path"
        echo "Production sync failed. The existing local database was left unchanged." >&2
        echo "Use scripts/start-work.sh --offline to start with the existing local database." >&2
        exit 1
    fi

    verify_database "$download_path"
    mv "$download_path" "$LOCAL_DB"
    chmod 600 "$LOCAL_DB"

    local brand_count
    local item_count
    local user_count
    brand_count="$(php -r '$db = new PDO("sqlite:" . $argv[1]); echo $db->query("SELECT COUNT(*) FROM brands")->fetchColumn();' "$LOCAL_DB")"
    item_count="$(php -r '$db = new PDO("sqlite:" . $argv[1]); echo $db->query("SELECT COUNT(*) FROM items")->fetchColumn();' "$LOCAL_DB")"
    user_count="$(php -r '$db = new PDO("sqlite:" . $argv[1]); echo $db->query("SELECT COUNT(*) FROM users")->fetchColumn();' "$LOCAL_DB")"

    echo "Production database synced: ${brand_count} brands, ${item_count} items, ${user_count} users."
}

if [[ "$MODE" != "--offline" ]]; then
    sync_production_database
else
    if [[ ! -f "$LOCAL_DB" ]]; then
        echo "No local database exists. Run without --offline to download production first." >&2
        exit 1
    fi
    verify_database "$LOCAL_DB"
    echo "Using the existing local database without syncing production."
fi

if [[ "$MODE" == "--sync-only" ]]; then
    exit 0
fi

exec "${ROOT_DIR}/scripts/serve-local.sh"
