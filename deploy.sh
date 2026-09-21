#!/usr/bin/env bash
set -euo pipefail

SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_APP_PATH_OVERRIDE="${DEPLOY_APP_PATH:-}"
readonly SCRIPT_PATH DEPLOY_APP_PATH_OVERRIDE
APP_PATH="${DEPLOY_APP_PATH_OVERRIDE:-$SCRIPT_PATH}"
case "$APP_PATH" in /*) ;; *) echo '==> [ERROR] DEPLOY_APP_PATH must be an absolute path' >&2; exit 1 ;; esac
[ -d "$APP_PATH" ] || { echo "==> [ERROR] Application path does not exist: $APP_PATH" >&2; exit 1; }
APP_PATH="$(cd "$APP_PATH" && pwd)"
CANONICAL_APP_PATH="$APP_PATH"
readonly CANONICAL_APP_PATH
log() { echo "==> [$1] $2"; }
fail() { log ERROR "$1" >&2; exit 1; }
run_php() { php -d display_errors=Off "$@"; }
[ -d "$APP_PATH/.git" ] || fail "$APP_PATH is not a Git checkout"
[ -z "$(git -C "$APP_PATH" status --porcelain)" ] || fail 'Production working tree is not clean'
[ "$(git -C "$APP_PATH" branch --show-current)" = main ] || fail 'Production must be on main'
[ -f "$APP_PATH/deploy.config" ] && source "$APP_PATH/deploy.config"
APP_PATH="$CANONICAL_APP_PATH"
PUBLIC_HTML="${PUBLIC_HTML:-$APP_PATH/public}"
DEPLOY_BRANCHES="${DEPLOY_BRANCHES:-main}"
GIT_BRANCH="${GIT_BRANCH:-main}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-false}"
SYNC_FOLDERS="${SYNC_FOLDERS:-css js images vendors assets}"
SYNC_ROOT_FILES="${SYNC_ROOT_FILES:-firebase-messaging-sw.js manifest.json manifest.webmanifest mix-manifest.json favicon.ico offline.html service-worker.js robots.txt}"
SKIP_MIGRATIONS=false; SKIP_DEPS=false; LIVE_DEPLOY=false; RECONCILE_MASTERS_PAYMENTS=false; INSTALL_COMMAND=false; SHOW_HELP=false; REQUESTED_BRANCH=""; EXPECTED_SHA=""; APPROVED_MIGRATIONS_B64=""; APPROVED_MIGRATIONS_SET=false; APP_IS_DOWN=0; PREFLIGHT_DIR=""
usage() { echo 'Usage: deploy main [--expected-sha SHA] [--approved-migrations-b64 BASE64] [--skip-migrations] [--skip-deps] [--live] [--reconcile-masters-payments]'; echo '       ./deploy.sh --install-command'; echo; echo '  --expected-sha SHA  Deploy exactly this 40-character origin/main commit and reject branch movement.'; echo '  --approved-migrations-b64 BASE64  Base64 of exact comma-separated pending paths, or "none".'; echo '  --live              Keep the site online and run the exact approved migrations; rejects Composer dependency changes.'; echo '                      In an interactive terminal, omitted migration approval is reviewed and confirmed before deployment.'; echo '  --reconcile-masters-payments  Explicitly apply the Masters payment reconciliation during maintenance deploys.'; }
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
    --install-command) INSTALL_COMMAND=true ;; --skip-migrations) SKIP_MIGRATIONS=true ;; --skip-deps) SKIP_DEPS=true ;; --live) LIVE_DEPLOY=true ;; --reconcile-masters-payments) RECONCILE_MASTERS_PAYMENTS=true ;; -h|--help) SHOW_HELP=true ;;
    --expected-sha) [ "$#" -ge 2 ] || fail 'Missing value for --expected-sha'; EXPECTED_SHA="$2"; shift ;; --expected-sha=*) EXPECTED_SHA="${1#*=}" ;;
    --approved-migrations-b64) [ "$#" -ge 2 ] || fail 'Missing value for --approved-migrations-b64'; APPROVED_MIGRATIONS_B64="$2"; APPROVED_MIGRATIONS_SET=true; shift ;; --approved-migrations-b64=*) APPROVED_MIGRATIONS_B64="${1#*=}"; APPROVED_MIGRATIONS_SET=true ;;
    --branch) [ "$#" -ge 2 ] || fail 'Missing value for --branch'; REQUESTED_BRANCH="$2"; shift ;; --branch=*) REQUESTED_BRANCH="${1#*=}" ;;
    -*) fail "Unknown option: $1" ;; *) [ -z "$REQUESTED_BRANCH" ] || fail 'Only one deployment branch may be supplied'; REQUESTED_BRANCH="$1" ;;
esac; shift; done
[ "$SHOW_HELP" = true ] && { usage; exit 0; }; [ "$INSTALL_COMMAND" = true ] && { install_command; exit 0; }
if [ "$APPROVED_MIGRATIONS_SET" = false ]; then
    [ "$LIVE_DEPLOY" = true ] || fail 'An explicit per-run approved migration list is required for maintenance deployments'
    [ -t 0 ] && [ -t 1 ] || fail 'Non-interactive deployments require --approved-migrations-b64'
fi
REQUESTED_BRANCH="${REQUESTED_BRANCH:-$GIT_BRANCH}"
case "$REQUESTED_BRANCH" in *[!A-Za-z0-9._/-]*|/*|*..*) fail "Invalid deployment branch: $REQUESTED_BRANCH" ;; esac
case " $DEPLOY_BRANCHES " in *" $REQUESTED_BRANCH "*) ;; *) fail "Branch '$REQUESTED_BRANCH' is not approved" ;; esac
[ "$REQUESTED_BRANCH" = main ] || fail 'Cape Tennis production deploys only main'
if [ -n "$EXPECTED_SHA" ]; then
    case "$EXPECTED_SHA" in *[!0-9a-fA-F]*|'') fail 'Expected SHA must contain exactly 40 hexadecimal characters' ;; esac
    [ "${#EXPECTED_SHA}" -eq 40 ] || fail 'Expected SHA must contain exactly 40 hexadecimal characters'
    EXPECTED_SHA="$(printf '%s' "$EXPECTED_SHA" | tr 'A-F' 'a-f')"
fi
[ "$RECONCILE_MASTERS_PAYMENTS" = false ] || [ "$LIVE_DEPLOY" = false ] || fail 'Masters payment reconciliation requires a maintenance deployment'
cleanup_preflight() { [ -z "$PREFLIGHT_DIR" ] || { rm -f -- "$PREFLIGHT_DIR/deploy.config" "$PREFLIGHT_DIR/target-migrations" "$PREFLIGHT_DIR/approved-migrations" "$PREFLIGHT_DIR/pending-migrations" "$PREFLIGHT_DIR/post-pending-migrations" "$PREFLIGHT_DIR/empty-approval" "$PREFLIGHT_DIR/preflight-error" "$PREFLIGHT_DIR/preflight.php"; rmdir "$PREFLIGHT_DIR" 2>/dev/null || true; }; }
restore_online() { local status=$?; [ "$APP_IS_DOWN" = 1 ] && run_php "$APP_PATH/artisan" up || true; cleanup_preflight; exit "$status"; }
sync_public_html() {
    [ -z "$PUBLIC_HTML" ] || [ "$PUBLIC_HTML" = "$APP_PATH/public" ] && { log INFO 'Skipping separate public asset sync'; return; }
    mkdir -p "$PUBLIC_HTML"
    for folder in $SYNC_FOLDERS; do [ -d "$APP_PATH/public/$folder" ] || continue; mkdir -p "$PUBLIC_HTML/$folder"; if command -v rsync >/dev/null 2>&1; then rsync -a --delete "$APP_PATH/public/$folder/" "$PUBLIC_HTML/$folder/"; else cp -rf "$APP_PATH/public/$folder/." "$PUBLIC_HTML/$folder/"; fi; done
    for file in $SYNC_ROOT_FILES; do [ -f "$APP_PATH/public/$file" ] && cp "$APP_PATH/public/$file" "$PUBLIC_HTML/$file"; done
}
git -C "$APP_PATH" fetch origin main
FETCHED_MAIN="$(git -C "$APP_PATH" rev-parse origin/main)"
if [ -n "$EXPECTED_SHA" ] && [ "$FETCHED_MAIN" != "$EXPECTED_SHA" ]; then
    fail "origin/main is $FETCHED_MAIN, not expected commit $EXPECTED_SHA"
fi
PREFLIGHT_DIR="$(mktemp -d)"
trap cleanup_preflight EXIT
git -C "$APP_PATH" show "$FETCHED_MAIN:deploy.config" > "$PREFLIGHT_DIR/deploy.config"
git -C "$APP_PATH" ls-tree -r --name-only "$FETCHED_MAIN" -- database/migrations | grep -E '^database/migrations/[^/]+\.php$' > "$PREFLIGHT_DIR/target-migrations"
git -C "$APP_PATH" show "$FETCHED_MAIN:scripts/deployment-migration-preflight.php" > "$PREFLIGHT_DIR/preflight.php"
if [ "$APPROVED_MIGRATIONS_SET" = true ]; then
    if ! APPROVED_MIGRATIONS="$(printf '%s' "$APPROVED_MIGRATIONS_B64" | base64 --decode 2>/dev/null)"; then
        fail 'Approved migration input is not valid base64'
    fi
    [ -n "$APPROVED_MIGRATIONS" ] || fail 'Approved migration input must be "none" or an exact comma-separated list'
    if [ "$APPROVED_MIGRATIONS" = none ]; then
        : > "$PREFLIGHT_DIR/approved-migrations"
    else
        printf '%s' "$APPROVED_MIGRATIONS" | tr ',' '\n' > "$PREFLIGHT_DIR/approved-migrations"
    fi
else
    : > "$PREFLIGHT_DIR/approved-migrations"
    PREFLIGHT_ERROR="$PREFLIGHT_DIR/preflight-error"
    if run_php "$PREFLIGHT_DIR/preflight.php" \
        --deploy-config="$PREFLIGHT_DIR/deploy.config" \
        --target-migrations="$PREFLIGHT_DIR/target-migrations" \
        --approved-migrations="$PREFLIGHT_DIR/approved-migrations" \
        --pending-output="$PREFLIGHT_DIR/pending-migrations" \
        --app-path="$APP_PATH" 2> "$PREFLIGHT_ERROR"; then
        log INFO 'Exact target preflight found no pending migrations'
    else
        PREFLIGHT_MESSAGE="$(cat "$PREFLIGHT_ERROR")"
        case "$PREFLIGHT_MESSAGE" in
            'Migration preflight failed: Pending migrations lack explicit per-run approval: '*)
                printf '%s\n' "${PREFLIGHT_MESSAGE#Migration preflight failed: Pending migrations lack explicit per-run approval: }" | tr ',' '\n' | sed 's/^ //' > "$PREFLIGHT_DIR/approved-migrations"
                ;;
            *) printf '%s\n' "$PREFLIGHT_MESSAGE" >&2; fail 'Unable to determine an exact safe migration set' ;;
        esac
    fi
    echo 'Exact pending migrations for the target commit:'
    if [ -s "$PREFLIGHT_DIR/approved-migrations" ]; then sed 's/^/  - /' "$PREFLIGHT_DIR/approved-migrations"; else echo '  (none)'; fi
    printf 'Type DEPLOY to approve this exact migration set and continue: '
    IFS= read -r INTERACTIVE_APPROVAL
    [ "$INTERACTIVE_APPROVAL" = DEPLOY ] || fail 'Deployment cancelled; exact migration set was not approved'
fi
run_php "$PREFLIGHT_DIR/preflight.php" \
    --deploy-config="$PREFLIGHT_DIR/deploy.config" \
    --target-migrations="$PREFLIGHT_DIR/target-migrations" \
    --approved-migrations="$PREFLIGHT_DIR/approved-migrations" \
    --pending-output="$PREFLIGHT_DIR/pending-migrations" \
    --app-path="$APP_PATH"
if [ "$SKIP_MIGRATIONS" = true ] && [ -s "$PREFLIGHT_DIR/pending-migrations" ]; then
    fail 'Cannot skip migrations because the exact target commit has approved pending migrations'
fi
if [ "$LIVE_DEPLOY" = true ]; then
    LIVE_CHANGED_FILES="$(git -C "$APP_PATH" diff --name-only HEAD.."$FETCHED_MAIN")"
    if printf '%s\n' "$LIVE_CHANGED_FILES" | grep -Eq '^composer\.(json|lock)$'; then
        printf '%s\n' "$LIVE_CHANGED_FILES" | grep -E '^composer\.(json|lock)$' || true
        fail 'Live deploy rejected: Composer dependency changes require the normal maintenance deployment'
    fi
    SKIP_DEPS=true
    log INFO 'Live deployment selected; the site will remain online and approved migrations will run'
else
    run_php "$APP_PATH/artisan" down --retry=60; APP_IS_DOWN=1; trap restore_online EXIT
fi
git -C "$APP_PATH" merge --ff-only "$FETCHED_MAIN"
# Read the migration list shipped with the release we just pulled.
LOCKED_FETCHED_MAIN="$FETCHED_MAIN"
LOCKED_EXPECTED_SHA="$EXPECTED_SHA"
LOCKED_RECONCILE_MASTERS_PAYMENTS="$RECONCILE_MASTERS_PAYMENTS"
LOCKED_REQUESTED_BRANCH="$REQUESTED_BRANCH"
LOCKED_SKIP_MIGRATIONS="$SKIP_MIGRATIONS"
LOCKED_SKIP_DEPS="$SKIP_DEPS"
LOCKED_LIVE_DEPLOY="$LIVE_DEPLOY"
LOCKED_APP_IS_DOWN="$APP_IS_DOWN"
LOCKED_APPROVED_MIGRATIONS_B64="$APPROVED_MIGRATIONS_B64"
LOCKED_PREFLIGHT_DIR="$PREFLIGHT_DIR"
readonly LOCKED_FETCHED_MAIN LOCKED_EXPECTED_SHA LOCKED_RECONCILE_MASTERS_PAYMENTS LOCKED_REQUESTED_BRANCH
readonly LOCKED_SKIP_MIGRATIONS LOCKED_SKIP_DEPS LOCKED_LIVE_DEPLOY LOCKED_APP_IS_DOWN LOCKED_APPROVED_MIGRATIONS_B64 LOCKED_PREFLIGHT_DIR
[ -f "$APP_PATH/deploy.config" ] && source "$APP_PATH/deploy.config"
APP_PATH="$CANONICAL_APP_PATH"
FETCHED_MAIN="$LOCKED_FETCHED_MAIN"
EXPECTED_SHA="$LOCKED_EXPECTED_SHA"
RECONCILE_MASTERS_PAYMENTS="$LOCKED_RECONCILE_MASTERS_PAYMENTS"
REQUESTED_BRANCH="$LOCKED_REQUESTED_BRANCH"
SKIP_MIGRATIONS="$LOCKED_SKIP_MIGRATIONS"
SKIP_DEPS="$LOCKED_SKIP_DEPS"
LIVE_DEPLOY="$LOCKED_LIVE_DEPLOY"
APP_IS_DOWN="$LOCKED_APP_IS_DOWN"
APPROVED_MIGRATIONS_B64="$LOCKED_APPROVED_MIGRATIONS_B64"
PREFLIGHT_DIR="$LOCKED_PREFLIGHT_DIR"
REMOTE_HEAD="$(git -C "$APP_PATH" rev-parse HEAD)"
[ "$REMOTE_HEAD" = "$FETCHED_MAIN" ] || fail "Deployed commit $REMOTE_HEAD does not match preflighted commit $FETCHED_MAIN"
[ "$SKIP_DEPS" = true ] || composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --working-dir="$APP_PATH"
run_php "$APP_PATH/artisan" optimize:clear
if [ "$SKIP_MIGRATIONS" = false ] && [ "$RUN_MIGRATIONS" = true ]; then
    while IFS= read -r migration; do
        [ -n "$migration" ] || continue
        [ -f "$APP_PATH/$migration" ] || fail "Migration not found after merge: $migration"
        run_php "$APP_PATH/artisan" migrate --force --no-interaction --path="$migration"
    done < "$PREFLIGHT_DIR/pending-migrations"
    : > "$PREFLIGHT_DIR/empty-approval"
    run_php "$PREFLIGHT_DIR/preflight.php" \
        --deploy-config="$PREFLIGHT_DIR/deploy.config" \
        --target-migrations="$PREFLIGHT_DIR/target-migrations" \
        --approved-migrations="$PREFLIGHT_DIR/empty-approval" \
        --pending-output="$PREFLIGHT_DIR/post-pending-migrations" \
        --app-path="$APP_PATH"
fi
if [ "$RECONCILE_MASTERS_PAYMENTS" = true ]; then
    log INFO 'Reconciling completed Masters payments with their invitations'
    run_php "$APP_PATH/artisan" masters:reconcile-payments --apply
fi
run_php "$APP_PATH/artisan" storage:link 2>&1 | grep -v 'already exists' || true
run_php "$APP_PATH/artisan" config:cache; run_php "$APP_PATH/artisan" route:cache; run_php "$APP_PATH/artisan" view:cache
sync_public_html; run_php "$APP_PATH/artisan" queue:restart
if [ "$LIVE_DEPLOY" = false ]; then run_php "$APP_PATH/artisan" up; APP_IS_DOWN=0; fi
cleanup_preflight; PREFLIGHT_DIR=""; trap - EXIT
echo "Deployment complete: $REMOTE_HEAD"
