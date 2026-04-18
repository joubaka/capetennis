#!/bin/bash
# Laravel Deploy Script (Linux/Production)
# Usage: ./deploy.sh [environment] [--skip-migrations] [--skip-deps]

set -e  # Exit on error

# Configuration
# Defaults can be overridden by a deploy.config file located next to this script
APP_PATH="/var/www/capetennis"
PUBLIC_HTML="/home/user/public_html"  # Shared hosting web root - CUSTOMIZE FOR YOUR SETUP or set in deploy.config
ENV_FILE="$APP_PATH/.env"
ENVIRONMENT="${1:-production}"
SKIP_MIGRATIONS=false
SKIP_DEPS=false
GIT_BRANCH="main"  # Change to "player-update", "version-2", etc. as needed or set in deploy.config
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="$APP_PATH/logs/deploy_$TIMESTAMP.log"
BACKUP_PATH="$APP_PATH/backups/backup_$TIMESTAMP"

# Parse additional arguments
for arg in "$@"; do
    case $arg in
        --skip-migrations) SKIP_MIGRATIONS=true ;;
        --skip-deps) SKIP_DEPS=true ;;
    esac
done

# Ensure logs directory exists
mkdir -p "$APP_PATH/logs"
mkdir -p "$APP_PATH/backups"

# If a deploy.config file exists next to this script, source it to override defaults
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/deploy.config" ]; then
    # shellcheck disable=SC1090
    source "$SCRIPT_DIR/deploy.config"
    log "INFO" "Loaded configuration from $SCRIPT_DIR/deploy.config"
fi

# Expand leading tilde in PUBLIC_HTML if present
if [[ "$PUBLIC_HTML" == ~* ]]; then
    PUBLIC_HTML="${PUBLIC_HTML/#\~/$HOME}"
    log "INFO" "Expanded PUBLIC_HTML to $PUBLIC_HTML"
fi

# Logging function
log() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date '+%H:%M:%S')
    local log_entry="[$timestamp] [$level] $message"
    echo "$log_entry"
    echo "$log_entry" >> "$LOG_FILE"
}

# Error handling
error_exit() {
    log "ERROR" "$1"
    exit 1
}

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
    git fetch origin || log "WARNING" "git fetch failed"

    # Ensure we are on the configured branch
    if git rev-parse --verify "$GIT_BRANCH" >/dev/null 2>&1; then
        git checkout "$GIT_BRANCH" || git checkout -b "$GIT_BRANCH" origin/"$GIT_BRANCH"
    else
        git checkout -b "$GIT_BRANCH" origin/"$GIT_BRANCH" || log "WARNING" "Could not create/check out branch $GIT_BRANCH"
    fi

    git pull origin "$GIT_BRANCH" || log "WARNING" "Failed to pull from origin/$GIT_BRANCH"

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
    
    log "INFO" "Running database migrations..."
    cd "$APP_PATH"
    
    php artisan migrate --force || error_exit "Migrations failed"
    
    log "INFO" "Migrations completed"
}

# Clear cache
clear_cache() {
    log "INFO" "Clearing application cache..."
    cd "$APP_PATH"
    
    php artisan cache:clear || true
    php artisan route:clear || true
    php artisan config:clear || true
    php artisan view:clear || true
    
    log "INFO" "Cache cleared"
}

# Optimize application
optimize_application() {
    log "INFO" "Optimizing application..."
    cd "$APP_PATH"
    
    php artisan config:cache || true
    php artisan route:cache || true
    
    if [ "$ENVIRONMENT" = "production" ]; then
        php artisan event:cache || true
    fi
    
    log "INFO" "Application optimized"
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

    # Sync each asset folder individually for better control
    for folder in css js images vendors assets; do
        if [ -d "$APP_PATH/public/$folder" ]; then
            log "INFO" "   Syncing $folder/"
            mkdir -p "$PUBLIC_HTML/$folder"
            rsync -av --delete "$APP_PATH/public/$folder/" "$PUBLIC_HTML/$folder/" 2>/dev/null || {
                cp -r "$APP_PATH/public/$folder"/* "$PUBLIC_HTML/$folder/" 2>/dev/null || true
            }
        fi
    done

    # Sync root-level files (service workers, manifest, favicon, etc.)
    for file in firebase-messaging-sw.js manifest.json mix-manifest.json favicon.ico robots.txt; do
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
    restart_services
    
    log "INFO" "========== DEPLOYMENT COMPLETED SUCCESSFULLY =========="
    log "INFO" "Log saved to: $LOG_FILE"
}

# Run main function
main
