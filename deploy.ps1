# Laravel Deploy Script (Windows/PowerShell)
# Configure the following variables before running

param(
    [string]$environment = "local",
    [switch]$skipMigrations = $false,
    [switch]$skipDeps = $false
)

# Configuration
$APP_PATH = "C:\wamp64\www\ct"
$PUBLIC_HTML = "C:\wamp64\www\public_html"  # Shared hosting web root
$ENV_FILE = "$APP_PATH\.env"
$GIT_BRANCH = "main"  # Change to "player-update", "version-2", etc. as needed
$TIMESTAMP = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$LOG_FILE = "$APP_PATH\logs\deploy_$TIMESTAMP.log"

# Ensure logs directory exists
if (-not (Test-Path "$APP_PATH\logs")) {
    New-Item -ItemType Directory -Path "$APP_PATH\logs" | Out-Null
}

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $logMessage = "[$((Get-Date).ToString('HH:mm:ss'))] [$Level] $Message"
    Write-Host $logMessage
    Add-Content -Path $LOG_FILE -Value $logMessage
}

function Test-Prerequisites {
    Write-Log "Checking prerequisites..."
    
    # Check if PHP is installed
    $php = Get-Command php -ErrorAction SilentlyContinue
    if (-not $php) {
        Write-Log "PHP not found in PATH" "ERROR"
        exit 1
    }
    Write-Log "PHP found: $(php --version | Select-Object -First 1)"
    
    # Check if Composer is installed
    $composer = Get-Command composer -ErrorAction SilentlyContinue
    if (-not $composer) {
        Write-Log "Composer not found in PATH" "WARNING"
    } else {
        Write-Log "Composer found: $(composer --version)"
    }
}

function Backup-Environment {
    Write-Log "Creating backup..."
    $backupPath = "$APP_PATH\backups\backup_$TIMESTAMP"
    New-Item -ItemType Directory -Path $backupPath -Force | Out-Null
    
    if (Test-Path $ENV_FILE) {
        Copy-Item -Path $ENV_FILE -Destination "$backupPath\.env.backup" | Out-Null
        Write-Log "Environment file backed up"
    }
}

function Install-Dependencies {
    if ($skipDeps) {
        Write-Log "Skipping dependency installation"
        return
    }
    
    Write-Log "Installing/updating dependencies..."
    Push-Location $APP_PATH
    
    try {
        composer install --no-interaction --optimize-autoloader $(if ($environment -eq "production") { "--no-dev" })
        Write-Log "Dependencies installed successfully"
    }
    catch {
        Write-Log "Failed to install dependencies: $_" "ERROR"
        Pop-Location
        exit 1
    }
    
    Pop-Location
}

function Clear-Cache {
    Write-Log "Clearing application cache..."
    Push-Location $APP_PATH
    
    try {
        php artisan cache:clear
        php artisan route:clear
        php artisan config:clear
        php artisan view:clear
        Write-Log "Cache cleared"
    }
    catch {
        Write-Log "Failed to clear cache: $_" "WARNING"
    }
    
    Pop-Location
}

function Run-Migrations {
    if ($skipMigrations) {
        Write-Log "Skipping migrations"
        return
    }
    
    Write-Log "Running database migrations..."
    Push-Location $APP_PATH
    
    try {
        php artisan migrate --force
        Write-Log "Migrations completed"
    }
    catch {
        Write-Log "Failed to run migrations: $_" "ERROR"
        Pop-Location
        exit 1
    }
    
    Pop-Location
}

function Optimize-Application {
    Write-Log "Optimizing application..."
    Push-Location $APP_PATH

    try {
        php artisan config:cache
        php artisan route:cache
        if ($environment -eq "production") {
            php artisan event:cache
        }
        Write-Log "Application optimized"
    }
    catch {
        Write-Log "Optimization failed: $_" "WARNING"
    }

    Pop-Location
}

function Sync-PublicAssets {
    if ($PUBLIC_HTML -eq "$APP_PATH\public") {
        Write-Log "Skipping asset sync (public_html is same as app public folder)"
        return
    }

    Write-Log "Syncing public assets to $PUBLIC_HTML..."

    if (-not (Test-Path $PUBLIC_HTML)) {
        New-Item -ItemType Directory -Path $PUBLIC_HTML -Force | Out-Null
        Write-Log "Created $PUBLIC_HTML"
    }

    $assetFolders = @('css', 'js', 'images', 'vendors', 'assets')
    foreach ($folder in $assetFolders) {
        $source = "$APP_PATH\public\$folder"
        $dest = "$PUBLIC_HTML\$folder"

        if (Test-Path $source) {
            Write-Log "   Syncing $folder..."
            # Remove old folder and copy fresh
            if (Test-Path $dest) { Remove-Item -Path $dest -Recurse -Force }
            Copy-Item -Path $source -Destination $dest -Recurse -Force
        }
    }

    # Sync root-level files
    $rootFiles = @('firebase-messaging-sw.js', 'manifest.json', 'mix-manifest.json', 'favicon.ico', 'robots.txt')
    foreach ($file in $rootFiles) {
        $source = "$APP_PATH\public\$file"
        if (Test-Path $source) {
            Copy-Item -Path $source -Destination "$PUBLIC_HTML\$file" -Force
            Write-Log "   Copied $file"
        }
    }

    Write-Log "✅ Public assets synced to $PUBLIC_HTML"
}

function Publish-Assets {
    Write-Log "Publishing assets..."
    Push-Location $APP_PATH
    
    try {
        php artisan storage:link --force 2>$null
        Write-Log "Assets published"
    }
    catch {
        Write-Log "Failed to publish assets: $_" "WARNING"
    }
    
    Pop-Location
}

function Update-Permissions {
    Write-Log "Updating permissions..."
    
    $directories = @(
        "$APP_PATH\storage",
        "$APP_PATH\bootstrap\cache"
    )
    
    foreach ($dir in $directories) {
        if (Test-Path $dir) {
            # Grant full control to IIS or Apache user (adjust username as needed)
            # For WAMP/XAMP, typically this is not needed on Windows
            Write-Log "Permissions check for $dir"
        }
    }
}

function Restart-Services {
    if ($environment -eq "production") {
        Write-Log "Restarting web services..."
        # Adjust service names based on your setup
        # Restart-Service -Name "Apache2.4" -Force -ErrorAction SilentlyContinue
        # Restart-Service -Name "MySQL" -Force -ErrorAction SilentlyContinue
        Write-Log "Services restarted (manual check recommended)"
    }
}

# Main deployment flow
Write-Log "========== LARAVEL DEPLOYMENT STARTED =========="
Write-Log "Environment: $environment"
Write-Log "App Path: $APP_PATH"
Write-Log "Public HTML: $PUBLIC_HTML"

Test-Prerequisites
Backup-Environment
Install-Dependencies
Clear-Cache
Run-Migrations
Publish-Assets
Update-Permissions
Optimize-Application
Sync-PublicAssets
Restart-Services

Write-Log "========== DEPLOYMENT COMPLETED SUCCESSFULLY ==========" "SUCCESS"
Write-Log "Log saved to: $LOG_FILE"
