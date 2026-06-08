#!/usr/bin/env bash
set -u
umask 077

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOCAL_DB="${ROOT_DIR}/storage/getqualitystuff.sqlite"
LOCAL_BACKUP_DIR="${ROOT_DIR}/backups/local"
SERVER_PID_FILE="${ROOT_DIR}/storage/.local-server.pid"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

cd "$ROOT_DIR"
mkdir -p "$LOCAL_BACKUP_DIR"

for command in git php lsof; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Required command is not available: ${command}" >&2
        exit 1
    fi
done

CHECKS_FAILED=0
DATABASE_SAFE_TO_COPY=1

echo "Checking changed PHP files..."
PHP_FILES="$(
    {
        git diff --name-only --diff-filter=ACMR
        git diff --cached --name-only --diff-filter=ACMR
        git ls-files --others --exclude-standard
    } | sort -u | grep '\.php$' || true
)"

if [[ -n "$PHP_FILES" ]]; then
    while IFS= read -r file; do
        if ! php -l "$file" >/dev/null; then
            CHECKS_FAILED=1
        fi
    done <<< "$PHP_FILES"
else
    echo "No changed PHP files to check."
fi

if ! git diff --check; then
    CHECKS_FAILED=1
fi
if ! git diff --cached --check; then
    CHECKS_FAILED=1
fi
if ! php scripts/audit_css.php; then
    CHECKS_FAILED=1
fi

stop_local_server() {
    local supervisor_pid
    local pids
    local stopped=0
    local process_name
    local process_cwd

    if [[ -f "$SERVER_PID_FILE" ]]; then
        supervisor_pid="$(cat "$SERVER_PID_FILE" 2>/dev/null || true)"
        if [[ "$supervisor_pid" =~ ^[0-9]+$ ]] && kill -0 "$supervisor_pid" 2>/dev/null; then
            process_name="$(lsof -a -p "$supervisor_pid" -F c 2>/dev/null | sed -n 's/^c//p' | head -1)"
            process_cwd="$(lsof -a -p "$supervisor_pid" -d cwd -F n 2>/dev/null | sed -n 's/^n//p' | head -1)"
            if [[ "$process_cwd" != "$ROOT_DIR" || ( "$process_name" != "serve-local.sh" && "$process_name" != "sh" && "$process_name" != "bash" ) ]]; then
                echo "Removed a stale local server PID file without stopping process ${supervisor_pid}."
                rm -f "$SERVER_PID_FILE"
            elif kill -TERM "$supervisor_pid" 2>/dev/null; then
                for _ in {1..30}; do
                    if ! kill -0 "$supervisor_pid" 2>/dev/null; then
                        rm -f "$SERVER_PID_FILE"
                        echo "Stopped the local Get Quality Stuff server."
                        return
                    fi
                    sleep 0.1
                done
                echo "The local server supervisor did not stop in time." >&2
            else
                echo "Could not stop the recorded local server supervisor." >&2
            fi

            if [[ -f "$SERVER_PID_FILE" ]]; then
                DATABASE_SAFE_TO_COPY=0
                CHECKS_FAILED=1
                return
            fi
        fi

        rm -f "$SERVER_PID_FILE"
    fi

    pids="$(lsof -tiTCP:"$PORT" -sTCP:LISTEN 2>/dev/null || true)"
    if [[ -z "$pids" ]]; then
        echo "No local server is listening on ${HOST}:${PORT}."
        return
    fi

    while IFS= read -r pid; do
        process_name="$(lsof -a -p "$pid" -F c 2>/dev/null | sed -n 's/^c//p' | head -1)"
        process_cwd="$(lsof -a -p "$pid" -d cwd -F n 2>/dev/null | sed -n 's/^n//p' | head -1)"
        if [[ "$process_name" == "php" && "$process_cwd" == "$ROOT_DIR" ]]; then
            if kill -TERM "$pid" 2>/dev/null; then
                stopped=1
                echo "Stopped the local Get Quality Stuff server."
            else
                DATABASE_SAFE_TO_COPY=0
                CHECKS_FAILED=1
                echo "Could not stop the local Get Quality Stuff server." >&2
            fi
        else
            echo "Left process ${pid} running because it does not appear to belong to this project."
        fi
    done <<< "$pids"

    if [[ "$stopped" -eq 1 ]]; then
        for _ in {1..20}; do
            if ! lsof -tiTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
                return
            fi
            sleep 0.1
        done
        echo "Warning: a process is still listening on port ${PORT}." >&2
        DATABASE_SAFE_TO_COPY=0
        CHECKS_FAILED=1
    fi
}

stop_local_server

if [[ "$DATABASE_SAFE_TO_COPY" -eq 0 ]]; then
    echo "Skipped the final database snapshot because the project server is still running." >&2
elif [[ -f "$LOCAL_DB" ]]; then
    if php -r '
        $db = new PDO("sqlite:" . $argv[1]);
        $result = $db->query("PRAGMA integrity_check")->fetchColumn();
        if ($result !== "ok") {
            fwrite(STDERR, "SQLite integrity check failed: {$result}\n");
            exit(1);
        }
    ' "$LOCAL_DB"; then
        TIMESTAMP="$(date +%Y-%m-%d-%H%M%S)"
        BACKUP_FILE="${LOCAL_BACKUP_DIR}/end-work-${TIMESTAMP}.sqlite"
        cp -p "$LOCAL_DB" "$BACKUP_FILE"
        chmod 600 "$BACKUP_FILE"
        find "$LOCAL_BACKUP_DIR" -type f -name '*.sqlite' -mtime +30 -delete
        echo "Saved final local database snapshot: ${BACKUP_FILE#$ROOT_DIR/}"
    else
        CHECKS_FAILED=1
        echo "The local database was not backed up because its integrity check failed." >&2
    fi
else
    echo "No local database exists to back up."
fi

echo
echo "Session summary"
STATUS="$(git status --short)"
if [[ -n "$STATUS" ]]; then
    echo "$STATUS"
else
    echo "Working tree is clean."
fi

echo
echo "Recommendations"
echo "- Review and commit intentional code changes."
echo "- Keep production as the primary database; do not upload this local database casually."
echo "- Deploy code separately after its changes are reviewed."

if [[ "$CHECKS_FAILED" -eq 1 ]]; then
    echo
    echo "One or more checks failed. Review the output before committing or deploying." >&2
    exit 1
fi

echo
echo "End-of-session checks passed."
