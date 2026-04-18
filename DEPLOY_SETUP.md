# Cape Tennis Deployment Setup Guide

This guide explains how to configure the deploy scripts for Cape Tennis (similar to jouberttennis.co.za setup).

## Your Current Setup

```
C:\wamp64\www\ct\                 ← Laravel app root
├── app/
├── resources/
├── public/                        ← Contains CSS, JS, images
├── storage/
├── vendor/
└── .env
```

## Deployment Architecture

Your setup uses **separated directories**:
- **App Code**: `C:\wamp64\www\ct\` (or `/var/www/capetennis` on production)
- **Web Root**: `C:\wamp64\www\public_html\` (or `/home/user/public_html` on production)

The deploy script syncs assets from `public/` → `public_html/`.

## Configuration

### Windows (Local Development)

**Edit `deploy.ps1` and set these variables:**

```powershell
$APP_PATH = "C:\wamp64\www\ct"
$PUBLIC_HTML = "C:\wamp64\www\public_html"  # Where your web server serves files
$GIT_BRANCH = "main"                        # or "player-update", "develop", etc.
```

**Then run:**

```powershell
.\deploy.ps1
.\deploy.ps1 -environment production
.\deploy.ps1 -skipMigrations
```

### Linux/Production Server

**Edit `deploy.sh` and set these variables:**

```bash
APP_PATH="/var/www/capetennis"               # Laravel app location
PUBLIC_HTML="/home/user/public_html"         # Web root (ask your hosting provider)
GIT_BRANCH="version-2"                       # or "main", "player-update", etc.
```

**Then run:**

```bash
chmod +x deploy.sh
./deploy.sh production
./deploy.sh production --skip-migrations
```

## Finding Your Web Root on Shared Hosting

Contact your hosting provider for these details, OR check:

```bash
# SSH into your server
ls -la ~/
# Look for: public_html, www, htdocs, or a domain-specific folder

# For cPanel:
ls -la ~/domains/
# Shows: capetennis.co.za/public_html, jouberttennis.co.za/public_html, etc.

# For Plesk:
ls -la /var/www/vhosts/
```

## What Gets Synced

The deploy script copies these from `public/`:

- ✅ `css/` - All stylesheets
- ✅ `js/` - All JavaScript files
- ✅ `images/` - Images
- ✅ `vendors/` - Third-party libraries (jQuery, Bootstrap, etc.)
- ✅ `assets/` - Other assets
- ✅ Root files: `favicon.ico`, `manifest.json`, `robots.txt`, etc.

## Deployment Flow

```
1. ✅ Check Prerequisites (PHP, Composer, Git)
2. ✅ Backup .env file
3. ✅ Pull Latest Code (git pull)
4. ✅ Install Dependencies (composer install)
5. ✅ Clear Caches (config, route, view)
6. ✅ Run Migrations (database updates)
7. ✅ Publish Assets (storage link)
8. ✅ Update Permissions (755 on storage/bootstrap)
9. ✅ Optimize (cache config & routes)
10. ✅ **Sync Public Assets** → public_html
11. ✅ Restart Services (optional)
```

## Troubleshooting

### CSS/JS not updating in browser

**Solution:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+Shift+R)
3. Check if files exist in `public_html`:
   ```bash
   ls -la /home/user/public_html/css/
   ls -la /home/user/public_html/js/
   ```

### Deploy script fails to find `public_html`

**Solution:**
1. Verify path exists:
   ```bash
   ls -la /home/user/public_html
   ```
2. Check permissions:
   ```bash
   chmod 755 /home/user/public_html
   ```
3. Update `PUBLIC_HTML` variable in script

### Assets synced but not appearing

**Solution:**
1. Check web server document root:
   ```bash
   # Apache config
   grep DocumentRoot /etc/apache2/sites-enabled/*.conf
   # Nginx config
   grep root /etc/nginx/sites-enabled/*;
   ```
2. Verify symlinks aren't breaking:
   ```bash
   ls -la /home/user/public_html/
   # Check for `→` arrows indicating broken symlinks
   ```

### Migrations fail

**Solution:**
```bash
# Check database connection in .env
cat .env | grep DB_

# Test database manually
php artisan tinker
>>> DB::connection()->getPdo()
# Should return PDO object, not error

# Run migrations with verbose
php artisan migrate --force -v
```

## GitHub Branch Management

The deploy scripts can pull from different branches:

**Windows:**
```powershell
# Modify $GIT_BRANCH in deploy.ps1
$GIT_BRANCH = "player-update"
.\deploy.ps1
```

**Linux:**
```bash
# Modify GIT_BRANCH in deploy.sh
GIT_BRANCH="version-2"
./deploy.sh production
```

## Scheduling Automated Deployments

### Windows Task Scheduler

```powershell
# Create scheduled task (runs daily at 2 AM)
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-NoProfile -File C:\wamp64\www\ct\deploy.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -Action $action -Trigger $trigger `
  -TaskName "CapeTennisDeploy" -RunLevel Highest
```

### Linux Cron

```bash
# Add to crontab (crontab -e)
0 2 * * * /var/www/capetennis/deploy.sh production >> /var/www/capetennis/logs/cron.log 2>&1
```

## Logs

All deployments are logged:

- **Windows**: `C:\wamp64\www\ct\logs\deploy_YYYY-MM-DD_HH-MM-SS.log`
- **Linux**: `/var/www/capetennis/logs/deploy_YYYY-MM-DD_HH-MM-SS.log`

View the log:
```bash
tail -f /var/www/capetennis/logs/deploy_*.log
```

## Need Help?

For withdrawal/support issues:
- Email: support@capetennis.co.za

For deployment issues:
1. Check the logs (see above)
2. Verify paths are correct
3. Test prerequisites: `php -v`, `composer -V`, `git --version`
4. Check file permissions: `ls -la`
