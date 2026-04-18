# Laravel Deploy Scripts

Automated deployment scripts for your Cape Tennis Laravel application.

## Files Included

1. **deploy.ps1** - Windows/PowerShell deployment script
2. **deploy.sh** - Linux/Bash deployment script  
3. **deploy.config** - Configuration file (optional)

## Prerequisites

### Windows (PowerShell)
- PHP installed and in PATH
- Composer installed or `composer.phar` in project root
- PowerShell 5.0 or higher
- Git (optional, for version info)

### Linux/Production
- PHP CLI installed
- Composer installed or `composer.phar` in project root
- Git installed
- Proper permissions for file operations
- Web server running (Apache/Nginx)

## Quick Start

### Windows Deployment

```powershell
# Basic deployment (local environment)
.\deploy.ps1

# Production deployment
.\deploy.ps1 -environment production

# Skip migrations
.\deploy.ps1 -skipMigrations

# Skip dependency installation
.\deploy.ps1 -skipDeps

# Combine options
.\deploy.ps1 -environment production -skipMigrations
```

### Linux Deployment

```bash
# Make script executable (first time only)
chmod +x deploy.sh

# Basic deployment (production environment)
./deploy.sh

# Development deployment
./deploy.sh development

# Skip migrations
./deploy.sh production --skip-migrations

# Skip dependencies
./deploy.sh production --skip-deps

# Combine options
./deploy.sh production --skip-migrations --skip-deps
```

## What Each Script Does

### Deployment Steps

1. **Check Prerequisites** - Verifies PHP, Composer, Git availability
2. **Backup** - Creates backup of `.env` file and application state
3. **Pull Code** (Linux only) - Fetches latest code from Git repository
4. **Install Dependencies** - Runs `composer install`
5. **Clear Cache** - Clears all Laravel caches
6. **Run Migrations** - Executes pending database migrations
7. **Publish Assets** - Creates storage symlink
8. **Update Permissions** - Sets proper file permissions
9. **Optimize** - Caches routes, config, and events
10. **Restart Services** - Restarts web services (production only)

## Configuration

### Manual Configuration

Edit the script files directly:

- **Windows**: Update variables at top of `deploy.ps1`:
  ```powershell
  $APP_PATH = "C:\wamp64\www\ct"
  $PUBLIC_HTML = "C:\wamp64\www\ct\public"
  ```

- **Linux**: Update variables at top of `deploy.sh`:
  ```bash
  APP_PATH="/var/www/capetennis"
  PUBLIC_HTML="/var/www/html/public_html"
  ```

### Using Configuration File (Linux)

1. Copy `deploy.config` to `.env.deploy`
2. Modify values for your environment
3. Update `deploy.sh` to source the config file

## Logging

All deployments are logged to:
- **Windows**: `C:\wamp64\www\ct\logs\deploy_YYYY-MM-DD_HH-MM-SS.log`
- **Linux**: `/var/www/capetennis/logs/deploy_YYYY-MM-DD_HH-MM-SS.log`

## Customization Examples

### Add Asset Compilation (Windows)

```powershell
function Compile-Assets {
    Write-Log "Compiling assets..."
    Push-Location $APP_PATH
    
    try {
        npm install
        npm run prod
        Write-Log "Assets compiled"
    }
    catch {
        Write-Log "Asset compilation failed: $_" "WARNING"
    }
    
    Pop-Location
}

# Add to main flow before Optimize-Application
Compile-Assets
```

### Add Email Notification (Linux)

```bash
send_notification() {
    local email="$1"
    local status="$2"
    
    if [ -z "$email" ]; then
        return
    fi
    
    echo "Deployment $status on $(date)" | \
        mail -s "Cape Tennis Deploy: $status" "$email"
    
    log "INFO" "Notification sent to $email"
}

# Add to end of main function
send_notification "$DEPLOY_NOTIFY_EMAIL" "SUCCESS"
```

### Database Backup Before Migration (Linux)

```bash
backup_database() {
    log "INFO" "Backing up database..."
    local backup_file="$BACKUP_PATH/database_$TIMESTAMP.sql"
    
    mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$backup_file"
    
    log "INFO" "Database backed up to $backup_file"
}

# Add to main flow before run_migrations
backup_database
```

## Troubleshooting

### Script fails with "composer not found"

**Windows:**
```powershell
# Download composer.phar to project root
Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile "composer.phar"
```

**Linux:**
```bash
# Download composer.phar to project root
curl -sS https://getcomposer.org/composer.phar -o composer.phar
```

### Permission Denied (Linux)

```bash
chmod +x deploy.sh
sudo ./deploy.sh  # If needed
```

### Migrations fail

- Check database credentials in `.env`
- Verify database is running
- Run migrations manually: `php artisan migrate --force`
- Check logs: `storage/logs/laravel.log`

### Cache issues

Clear cache manually:
```bash
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

## Scheduling Deployments

### Windows Task Scheduler

```powershell
# Create scheduled task
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-File C:\path\to\deploy.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "LaravelDeploy"
```

### Linux Cron

```bash
# Add to crontab (crontab -e)
0 2 * * * /var/www/capetennis/deploy.sh production >> /var/www/capetennis/logs/cron_deploy.log 2>&1
```

## Additional Resources

- [Laravel Deployment Guide](https://laravel.com/docs/deployment)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Git Documentation](https://git-scm.com/doc)

## Support

For issues or questions about deployment:
- Email: support@capetennis.co.za
- Check deployment logs for detailed error messages
- Review Laravel error logs: `storage/logs/laravel.log`
