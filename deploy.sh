#!/usr/bin/env bash
set -euo pipefail

APP_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[ -f "$APP_PATH/deploy.config" ] && source "$APP_PATH/deploy.config"
PUBLIC_HTML="${PUBLIC_HTML:-$APP_PATH/public}"
DEPLOY_BRANCHES="${DEPLOY_BRANCHES:-main}"
GIT_BRANCH="${GIT_BRANCH:-main}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-false}"
SYNC_FOLDERS="${SYNC_FOLDERS:-css js images vendors assets}"
SYNC_ROOT_FILES="${SYNC_ROOT_FILES:-firebase-messaging-sw.js manifest.json manifest.webmanifest mix-manifest.json favicon.ico offline.html service-worker.js robots.txt}"
SKIP_MIGRATIONS=false; SKIP_DEPS=false; INSTALL_COMMAND=false; SHOW_HELP=false; REQUESTED_BRANCH=""; APP_IS_DOWN=0
log() { echo "==> [$1] $2"; }
fail() { log ERROR "$1" >&2; exit 1; }
run_php() { php -d display_errors=Off "$@"; }
usage() { echo 'Usage: deploy main [--skip-migrations] [--skip-deps]'; echo '       ./deploy.sh --install-command'; }
install_command() {
    local dir="${DEPLOY_COMMAND_DIR:-$HOME/bin}"
    local path="$dir/deploy-ct"
    local profile_path="${DEPLOY_PROFILE_PATH:-$HOME/.bash_profile}"
    mkdir -p "$dir"
    ln -sfn "$APP_PATH/bin/deploy" "$path"
    case ":$PATH:" in
        *":$dir:"*) ;;
        *)
            touch "$profile_path"
            if ! grep -Fq 'export PATH="$HOME/bin:$PATH"' "$profile_path"; then
                printf '\nexport PATH="$HOME/bin:$PATH"\n' >> "$profile_path"
            fi
            log INFO "Added $dir to PATH; reconnect once before using: deploy-ct main"
            ;;
    esac
    log INFO "Deployment shortcut ready: deploy-ct main"
}
while [ "$#" -gt 0 ]; do case "$1" in
    --install-command) INSTALL_COMMAND=true ;; --skip-migrations) SKIP_MIGRATIONS=true ;; --skip-deps) SKIP_DEPS=true ;; -h|--help) SHOW_HELP=true ;;
    --branch) [ "$#" -ge 2 ] || fail 'Missing value for --branch'; REQUESTED_BRANCH="$2"; shift ;; --branch=*) REQUESTED_BRANCH="${1#*=}" ;;
    -*) fail "Unknown option: $1" ;; *) [ -z "$REQUESTED_BRANCH" ] || fail 'Only one deployment branch may be supplied'; REQUESTED_BRANCH="$1" ;;
esac; shift; done
[ "$SHOW_HELP" = true ] && { usage; exit 0; }; [ "$INSTALL_COMMAND" = true ] && { install_command; exit 0; }
REQUESTED_BRANCH="${REQUESTED_BRANCH:-$GIT_BRANCH}"
case "$REQUESTED_BRANCH" in *[!A-Za-z0-9._/-]*|/*|*..*) fail "Invalid deployment branch: $REQUESTED_BRANCH" ;; esac
case " $DEPLOY_BRANCHES " in *" $REQUESTED_BRANCH "*) ;; *) fail "Branch '$REQUESTED_BRANCH' is not approved" ;; esac
[ "$REQUESTED_BRANCH" = main ] || fail 'Cape Tennis production deploys only main'
[ -d "$APP_PATH/.git" ] || fail "$APP_PATH is not a Git checkout"
[ -z "$(git -C "$APP_PATH" status --porcelain)" ] || fail 'Production working tree is not clean'
[ "$(git -C "$APP_PATH" branch --show-current)" = main ] || fail 'Production must be on main'
restore_online() { local status=$?; [ "$APP_IS_DOWN" = 1 ] && run_php "$APP_PATH/artisan" up || true; exit "$status"; }
sync_public_html() {
    [ -z "$PUBLIC_HTML" ] || [ "$PUBLIC_HTML" = "$APP_PATH/public" ] && { log INFO 'Skipping separate public asset sync'; return; }
    mkdir -p "$PUBLIC_HTML"
    for folder in $SYNC_FOLDERS; do [ -d "$APP_PATH/public/$folder" ] || continue; mkdir -p "$PUBLIC_HTML/$folder"; if command -v rsync >/dev/null 2>&1; then rsync -a --delete "$APP_PATH/public/$folder/" "$PUBLIC_HTML/$folder/"; else cp -rf "$APP_PATH/public/$folder/." "$PUBLIC_HTML/$folder/"; fi; done
    for file in $SYNC_ROOT_FILES; do [ -f "$APP_PATH/public/$file" ] && cp "$APP_PATH/public/$file" "$PUBLIC_HTML/$file"; done
}
git -C "$APP_PATH" fetch origin main
run_php "$APP_PATH/artisan" down --retry=60; APP_IS_DOWN=1; trap restore_online EXIT
git -C "$APP_PATH" merge --ff-only origin/main
# Read the migration list shipped with the release we just pulled.
[ -f "$APP_PATH/deploy.config" ] && source "$APP_PATH/deploy.config"
REMOTE_HEAD="$(git -C "$APP_PATH" rev-parse HEAD)"
[ "$SKIP_DEPS" = true ] || composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --working-dir="$APP_PATH"
run_php "$APP_PATH/artisan" optimize:clear
if [ "$SKIP_MIGRATIONS" = false ] && [ "$RUN_MIGRATIONS" = true ]; then
    [ -n "${MIGRATION_PATHS:-}" ] || fail 'RUN_MIGRATIONS=true requires MIGRATION_PATHS'
    for migration in $MIGRATION_PATHS; do case "$migration" in database/migrations/*.php) ;; *) fail "Invalid migration path: $migration" ;; esac; [ -f "$APP_PATH/$migration" ] || fail "Migration not found: $migration"; run_php "$APP_PATH/artisan" migrate --force --no-interaction --path="$migration"; done
    PENDING_MIGRATIONS="$(run_php "$APP_PATH/artisan" migrate:status --pending --no-interaction --no-ansi)"
    if ! printf '%s\n' "$PENDING_MIGRATIONS" | grep -Fq 'No pending migrations.'; then
        printf '%s\n' "$PENDING_MIGRATIONS"
        fail 'Pending migrations remain after the approved migration list ran; add each reviewed path to MIGRATION_PATHS'
    fi
fi
run_php "$APP_PATH/artisan" storage:link 2>&1 | grep -v 'already exists' || true
run_php "$APP_PATH/artisan" config:cache; run_php "$APP_PATH/artisan" route:cache; run_php "$APP_PATH/artisan" view:cache
sync_public_html; run_php "$APP_PATH/artisan" queue:restart; run_php "$APP_PATH/artisan" up; APP_IS_DOWN=0; trap - EXIT
echo "Deployment complete: $REMOTE_HEAD"
