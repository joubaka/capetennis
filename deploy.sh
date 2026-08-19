#!/bin/bash
# Laravel Deploy Script (Linux/Production)
# Usage: deploy <branch> [environment] [--skip-migrations] [--skip-deps]
#        ./deploy.sh <branch> [environment] [--skip-migrations] [--skip-deps]

set -e  # Exit on error

# ------------------
# Default configuration (can be overridden by deploy.config)
# ------------------
APP_PATH="/var/www/capetennis"
PUBLIC_HTML="/home/user/public_html"  # Shared hosting web root - CUSTOMIZE FOR YOUR SETUP or set in deploy.config
ENVIRONMENT="production"
SKIP_MIGRATIONS=false
SKIP_DEPS=false
GIT_BRANCH="main"  # Change to "player-update", "version-2", etc. as needed or set in deploy.config
DEPLOY_BRANCHES="main"
COMPARE_ONLY=false
ONLY_JS=false
INSTALL_COMMAND=false
SHOW_HELP=false
REQUESTED_BRANCH=""

# Determine script directory (used to locate deploy.config)
SCRIPT_SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SCRIPT_SOURCE" ]; do
    SOURCE_DIR="$(cd -P "$(dirname "$SCRIPT_SOURCE")" && pwd)"
    SCRIPT_SOURCE="$(readlink "$SCRIPT_SOURCE")"
    [[ "$SCRIPT_SOURCE" != /* ]] && SCRIPT_SOURCE="$SOURCE_DIR/$SCRIPT_SOURCE"
done
SCRIPT_DIR="$(cd -P "$(dirname "$SCRIPT_SOURCE")" && pwd)"
CONFIG_LOADED=false

# If a deploy.config file exists next to this script, source it to override defaults
if [ -f "$SCRIPT_DIR/deploy.config" ]; then
    # shellcheck disable=SC1090
    source "$SCRIPT_DIR/deploy.config"
    CONFIG_LOADED=true
fi

# deploy.config may define the default deployment environment.
ENVIRONMENT="${DEPLOY_ENV:-$ENVIRONMENT}"

# Expand leading tilde in APP_PATH and PUBLIC_HTML if present
if [[ "$APP_PATH" == ~* ]]; then
    APP_PATH="${APP_PATH/#\~/$HOME}"
fi
if [[ "$PUBLIC_HTML" == ~* ]]; then
    PUBLIC_HTML="${PUBLIC_HTML/#\~/$HOME}"
fi

# Fallback: if APP_PATH is empty (not set in config), use current working directory
if [ -z "$APP_PATH" ]; then
    APP_PATH="$(pwd)"
fi

# Parse arguments. A positional environment remains supported for backwards
# compatibility, while `deploy main` treats `main` as the requested branch.
while [ "$#" -gt 0 ]; do
    case "$1" in
        production|staging|development|local)
            ENVIRONMENT="$1"
            ;;
        --branch)
            [ "$#" -ge 2 ] || { echo "Missing value for --branch" >&2; exit 2; }
            REQUESTED_BRANCH="$2"
            shift
            ;;
        --branch=*) REQUESTED_BRANCH="${1#*=}" ;;
        --skip-migrations) SKIP_MIGRATIONS=true ;;
        --skip-deps) SKIP_DEPS=true ;;
        --compare) COMPARE_ONLY=true ;;
        --only-js) ONLY_JS=true ;;
        --install-command) INSTALL_COMMAND=true ;;
        -h|--help) SHOW_HELP=true ;;
        -*) echo "Unknown deploy option: $1" >&2; exit 2 ;;
        *)
            if [ -n "$REQUESTED_BRANCH" ]; then
                echo "Only one deployment branch may be supplied" >&2
                exit 2
            fi
            REQUESTED_BRANCH="$1"
            ;;
    esac
    shift
done

if [ -n "$REQUESTED_BRANCH" ]; then
    case "$REQUESTED_BRANCH" in
        *[!A-Za-z0-9._/-]*|""|/*|*..*)
            echo "Invalid deployment branch: $REQUESTED_BRANCH" >&2
            exit 2
            ;;
    esac

    BRANCH_ALLOWED=false
    for allowed_branch in ${DEPLOY_BRANCHES:-$GIT_BRANCH}; do
        if [ "$REQUESTED_BRANCH" = "$allowed_branch" ]; then
            BRANCH_ALLOWED=true
            break
        fi
    done
    if [ "$BRANCH_ALLOWED" != true ]; then
        echo "Branch '$REQUESTED_BRANCH' is not approved by DEPLOY_BRANCHES" >&2
        exit 2
    fi
    GIT_BRANCH="$REQUESTED_BRANCH"
fi

show_usage() {
    cat <<'EOF'
Cape Tennis production deployment

Usage:
  deploy main [--skip-migrations] [--skip-deps]
  ./deploy.sh main [production] [options]
  ./deploy.sh --install-command

The deployment pulls the approved branch with --ff-only, installs locked
Composer dependencies, runs only migration paths allowlisted in deploy.config,
refreshes Laravel caches, publishes/syncs assets, and restarts queue workers.
EOF
}

install_deploy_command() {
    local install_dir="${DEPLOY_COMMAND_DIR:-$HOME/.local/bin}"
    local command_path="$install_dir/deploy"

    mkdir -p "$install_dir"

    if [ -e "$command_path" ] || [ -L "$command_path" ]; then
        if [ -L "$command_path" ] && [ "$(readlink -f "$command_path")" = "$SCRIPT_DIR/deploy.sh" ]; then
            echo "deploy command is already installed at $command_path"
        else
            echo "Refusing to replace existing command: $command_path" >&2
            exit 1
        fi
    else
        ln -s "$SCRIPT_DIR/deploy.sh" "$command_path"
        echo "Installed deploy command at $command_path"
    fi

    case ":$PATH:" in
        *":$install_dir:"*) ;;
        *)
            echo "Add this directory to PATH before using the command:"
            echo "  export PATH=\"$install_dir:\$PATH\""
            ;;
    esac
    echo "You can now run: deploy main"
}

if [ "$SHOW_HELP" = true ]; then
    show_usage
    exit 0
fi

if [ "$INSTALL_COMMAND" = true ]; then
    install_deploy_command
    exit 0
fi

# Derived paths (must be set after APP_PATH is finalized)
ENV_FILE="$APP_PATH/.env"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="$APP_PATH/logs/deploy_$TIMESTAMP.log"
BACKUP_PATH="$APP_PATH/backups/backup_$TIMESTAMP"

# Ensure logs and backups directories exist (use finalized APP_PATH)
mkdir -p "$APP_PATH/logs"
mkdir -p "$APP_PATH/backups"

# Logging function (defined after LOG_FILE and log dir exist)
log() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date '+%H:%M:%S')
    local log_entry="[$timestamp] [$level] $message"
    echo "$log_entry"
    echo "$log_entry" >> "$LOG_FILE"
}

# Compare public folders (APP_PATH/public vs PUBLIC_HTML) using rsync dry-run or diff fallback
compare_public_html() {
    log "INFO" "Comparing $APP_PATH/public -> $PUBLIC_HTML"

    if [ ! -d "$APP_PATH/public" ]; then
        log "ERROR" "$APP_PATH/public does not exist"
        return 2
    fi

    if [ ! -d "$PUBLIC_HTML" ]; then
        log "WARNING" "$PUBLIC_HTML does not exist on remote; nothing to compare"
        return 1
    fi

    if command -v rsync &> /dev/null; then
        log "INFO" "Using rsync --dry-run to show differences"
        rsync -av --delete --dry-run --itemize-changes "$APP_PATH/public/" "$PUBLIC_HTML/"
        return 0
    fi

    log "INFO" "rsync not available, falling back to diff -qr"
    diff -qr "$APP_PATH/public" "$PUBLIC_HTML" || true
}

# Error handling
error_exit() {
    log "ERROR" "$1"
    exit 1
}

# If config was loaded earlier, log that fact now
if [ "$CONFIG_LOADED" = true ]; then
    log "INFO" "Loaded configuration from $SCRIPT_DIR/deploy.config"
    log "INFO" "Expanded APP_PATH to $APP_PATH"
    log "INFO" "Expanded PUBLIC_HTML to $PUBLIC_HTML"
fi

# If user requested only comparison, run it and exit
if [ "$COMPARE_ONLY" = true ]; then
    compare_public_html
    exit $?
fi

# Check prerequisites
check_prerequisites() {
    log "INFO" "Checking prerequisites..."
    
    command -v php &> /dev/null || error_exit "PHP not found"
    log "INFO" "PHP version: $(php --version | head -n1)"
    
    if ! command -v composer &> /dev/null; then
        log "WARNING" "Composer not found - will attempt to use local composer.phar"
    fi
    
    command -v git &> /dev/null || log "WARNING" "Git not found"
}

# Backup environment
backup_environment() {
    log "INFO" "Creating backup..."
    mkdir -p "$BACKUP_PATH"
    
    if [ -f "$ENV_FILE" ]; then
        cp "$ENV_FILE" "$BACKUP_PATH/.env.backup"
        log "INFO" "Environment file backed up to $BACKUP_PATH"
    fi
}

# Pull latest code
pull_code() {
    log "INFO" "Pulling latest code from repository..."
    cd "$APP_PATH"

    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || error_exit "$APP_PATH is not a Git working tree"
    if [ -n "$(git status --porcelain)" ]; then
        error_exit "Deployment stopped: the production working tree is not clean"
    fi

    git fetch origin || error_exit "git fetch failed"

    # Ensure we are on the configured branch. Never continue on another branch
    # when checkout fails.
    if git show-ref --verify --quiet "refs/heads/$GIT_BRANCH"; then
        git checkout "$GIT_BRANCH" || error_exit "Could not check out branch $GIT_BRANCH"
    elif git show-ref --verify --quiet "refs/remotes/origin/$GIT_BRANCH"; then
        git checkout -b "$GIT_BRANCH" --track "origin/$GIT_BRANCH" || error_exit "Could not create local branch $GIT_BRANCH"
    else
        error_exit "Approved branch origin/$GIT_BRANCH does not exist"
    fi

    [ "$(git branch --show-current)" = "$GIT_BRANCH" ] || error_exit "Deployment is not on branch $GIT_BRANCH"

    git pull --ff-only origin "$GIT_BRANCH" || error_exit "Failed to fast-forward from origin/$GIT_BRANCH"

    [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$GIT_BRANCH")" ] || error_exit "Local branch does not match origin/$GIT_BRANCH"

    log "INFO" "Code pulled successfully (branch: $GIT_BRANCH)"
}

# Install dependencies
install_dependencies() {
    if [ "$SKIP_DEPS" = true ]; then
        log "INFO" "Skipping dependency installation"
        return
    fi
    
    log "INFO" "Installing/updating dependencies..."
    cd "$APP_PATH"
    
    if command -v composer &> /dev/null; then
        if [ "$ENVIRONMENT" = "production" ]; then
            composer install --no-interaction --optimize-autoloader --no-dev
        else
            composer install --no-interaction --optimize-autoloader
        fi
    elif [ -f "composer.phar" ]; then
        if [ "$ENVIRONMENT" = "production" ]; then
            php composer.phar install --no-interaction --optimize-autoloader --no-dev
        else
            php composer.phar install --no-interaction --optimize-autoloader
        fi
    else
        error_exit "Composer not found and composer.phar not available"
    fi
    
    log "INFO" "Dependencies installed"
}

# Run migrations
run_migrations() {
    if [ "$SKIP_MIGRATIONS" = true ]; then
        log "INFO" "Skipping migrations"
        return
    fi
    
    if [ "${RUN_MIGRATIONS:-false}" != true ]; then
        log "INFO" "Migrations disabled by deploy.config"
        return
    fi

    if [ -z "${MIGRATION_PATHS:-}" ]; then
        error_exit "RUN_MIGRATIONS=true requires an explicit MIGRATION_PATHS allowlist"
    fi

    log "INFO" "Running approved database migrations..."
    cd "$APP_PATH"

    for migration_path in $MIGRATION_PATHS; do
        case "$migration_path" in
            database/migrations/*.php) ;;
            *) error_exit "Invalid migration path: $migration_path" ;;
        esac

        if [ ! -f "$APP_PATH/$migration_path" ]; then
            error_exit "Approved migration not found: $migration_path"
        fi

        php artisan migrate --force --path="$migration_path" || error_exit "Migration failed: $migration_path"
    done
    
    log "INFO" "Migrations completed"
}

# Clear cache
clear_cache() {
    log "INFO" "Clearing application cache..."
    cd "$APP_PATH"
    
    php artisan cache:clear || true
    php artisan route:clear || error_exit "Route cache clear failed"
    php artisan config:clear || error_exit "Configuration cache clear failed"
    php artisan view:clear || error_exit "Compiled view clear failed"
    
    log "INFO" "Cache cleared"
}

# Optimize application
optimize_application() {
    log "INFO" "Optimizing application..."
    cd "$APP_PATH"
    
    php artisan config:cache || error_exit "Configuration cache rebuild failed"
    php artisan route:cache || error_exit "Route cache rebuild failed"
    
    if [ "$ENVIRONMENT" = "production" ]; then
        php artisan event:cache || true
    fi
    
    log "INFO" "Application optimized"
}

# Tell long-running queue workers to reload the newly deployed application.
restart_queue_workers() {
    log "INFO" "Restarting queue workers..."
    cd "$APP_PATH"
    php artisan queue:restart || error_exit "Queue worker restart signal failed"
    log "INFO" "Queue worker restart signal sent"
}

# Publish assets
publish_assets() {
    log "INFO" "Publishing assets..."
    cd "$APP_PATH"
    
    php artisan storage:link --force 2>/dev/null || true
    
    log "INFO" "Assets published"
}

# Update permissions
update_permissions() {
    log "INFO" "Updating permissions..."
    
    # Set proper permissions for Laravel directories
    chmod -R 755 "$APP_PATH/storage"
    chmod -R 755 "$APP_PATH/bootstrap/cache"
    
    # Set ownership (adjust user:group based on your setup)
    # chown -R www-data:www-data "$APP_PATH"
    
    log "INFO" "Permissions updated"
}

# Sync public_html with detailed asset handling (like jta setup)
sync_public_html() {
    if [ -z "$PUBLIC_HTML" ] || [ "$PUBLIC_HTML" = "$APP_PATH/public" ]; then
        log "INFO" "Skipping asset sync (public_html is same as app public folder)"
        return
    fi

    log "INFO" "Syncing public assets to $PUBLIC_HTML..."

    if [ ! -d "$PUBLIC_HTML" ]; then
        mkdir -p "$PUBLIC_HTML"
        log "INFO" "Created $PUBLIC_HTML"
    fi

    # Determine which folders to sync. Prefer SYNC_FOLDERS from deploy.config.
    if [ "$ONLY_JS" = true ]; then
        folders_to_sync="js"
        log "INFO" "Only syncing JS assets (--only-js)"
    elif [ -n "$SYNC_FOLDERS" ]; then
        folders_to_sync=$SYNC_FOLDERS
    else
        folders_to_sync="css js images vendors assets"
    fi

    # Sync each asset folder individually for better control
    for folder in $folders_to_sync; do
        if [ -d "$APP_PATH/public/$folder" ]; then
            log "INFO" "   Syncing $folder/"
            mkdir -p "$PUBLIC_HTML/$folder"
            rsync -av --delete "$APP_PATH/public/$folder/" "$PUBLIC_HTML/$folder/" 2>/dev/null || {
                cp -r "$APP_PATH/public/$folder"/* "$PUBLIC_HTML/$folder/" 2>/dev/null || true
            }
        fi
    done

    # Sync root-level files (service workers, manifest, favicon, etc.)
    root_files="${SYNC_ROOT_FILES:-firebase-messaging-sw.js manifest.json mix-manifest.json favicon.ico robots.txt}"
    for file in $root_files; do
        if [ -f "$APP_PATH/public/$file" ]; then
            cp "$APP_PATH/public/$file" "$PUBLIC_HTML/$file"
            log "INFO" "   Copied $file"
        fi
    done

    log "INFO" "✅ Public assets synced to $PUBLIC_HTML"
}

# Restart services
restart_services() {
    if [ "$ENVIRONMENT" = "production" ]; then
        log "INFO" "Restarting web services..."
        # Adjust these commands based on your server setup
        # sudo systemctl restart apache2
        # sudo systemctl restart nginx
        # sudo systemctl restart php-fpm
        log "INFO" "Please manually restart web services if needed"
    fi
}

# Main deployment flow
main() {
    log "INFO" "========== LARAVEL DEPLOYMENT STARTED =========="
    log "INFO" "Environment: $ENVIRONMENT"
    log "INFO" "App Path: $APP_PATH"
    
    check_prerequisites
    backup_environment
    pull_code
    install_dependencies
    clear_cache
    run_migrations
    publish_assets
    update_permissions
    sync_public_html
    optimize_application
    restart_queue_workers
    restart_services
    
    log "INFO" "========== DEPLOYMENT COMPLETED SUCCESSFULLY =========="
    log "INFO" "Log saved to: $LOG_FILE"
}

# Run main function
main
