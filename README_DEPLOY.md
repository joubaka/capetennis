# Cape Tennis Deploy Scripts - Complete Setup

Updated deployment scripts for Cape Tennis, configured like jouberttennis.co.za.

## Files Included

1. **deploy.ps1** - Windows PowerShell deploy script
2. **deploy.sh** - Linux Bash deploy script
3. **deploy.config** - Configuration template (variables)
4. **DEPLOY_SETUP.md** - Detailed setup guide
5. **QUICKSTART.md** - Quick reference
6. **DEPLOYMENT.md** - Full documentation

## Key Feature: Asset Syncing

Unlike standard Laravel, your setup uses **separate directories**:

```
App Root (Code)          Web Root (Served by Server)
/var/www/capetennis/     /home/user/public_html/
├── app/                 ├── css/
├── public/              ├── js/
│  ├── css/              ├── images/
│  ├── js/               ├── vendors/
│  ├── images/           └── assets/
│  └── ...
└── ...
```

The deploy scripts **sync assets** from `public/` → `public_html/`.

This is critical for shared hosting where:
- App code lives in private directory
- Web server serves from different location
- CSS/JS must be copied to serve properly

## Quick Setup

### Windows

```powershell
# 1. Edit deploy.ps1, set these:
$APP_PATH = "C:\wamp64\www\ct"
$PUBLIC_HTML = "C:\wamp64\www\public_html"  # Your web root
$GIT_BRANCH = "main"

# 2. Run
.\deploy.ps1
```

### Linux/Production

```bash
# 1. Edit deploy.sh, set these:
APP_PATH="/var/www/capetennis"
PUBLIC_HTML="/home/user/public_html"        # Your web root
GIT_BRANCH="main"

# 2. Make executable
chmod +x deploy.sh

# 3. Run
./deploy.sh production
```

## Deployment Steps

1. ✅ Check Prerequisites (PHP, Composer, Git)
2. ✅ Backup `.env` file
3. ✅ Pull Latest Code (git pull)
4. ✅ Install Dependencies (composer install)
5. ✅ Clear Caches (config, route, view)
6. ✅ Run Migrations (database updates)
7. ✅ Publish Assets (storage link)
8. ✅ Update Permissions (755)
9. ✅ Optimize Application (cache routes, config)
10. ✅ **Sync Public Assets** (CSS, JS, images → web root)
11. ✅ Restart Services (optional)

## Branch Support

Deploy from any git branch:

```powershell
# Windows - edit deploy.ps1
$GIT_BRANCH = "player-update"
.\deploy.ps1 -environment production
```

```bash
# Linux - edit deploy.sh
GIT_BRANCH="version-2"
./deploy.sh production
```

## Options

### Windows
```powershell
.\deploy.ps1                              # Default (local env)
.\deploy.ps1 -environment production      # Production
.\deploy.ps1 -skipMigrations              # Skip DB migrations
.\deploy.ps1 -skipDeps                    # Skip composer install
.\deploy.ps1 -environment production -skipMigrations
```

### Linux
```bash
./deploy.sh                                # Default (production env)
./deploy.sh development                   # Dev environment
./deploy.sh production --skip-migrations   # Skip migrations
./deploy.sh production --skip-deps         # Skip composer
./deploy.sh production --skip-migrations --skip-deps
```

## Logging

All deployments logged:
- **Windows**: `C:\wamp64\www\ct\logs\deploy_YYYY-MM-DD_HH-MM-SS.log`
- **Linux**: `/var/www/capetennis/logs/deploy_YYYY-MM-DD_HH-MM-SS.log`

```bash
tail -f logs/deploy_*.log
```

## Troubleshooting

### CSS/JS Not Updating
1. Clear browser cache (Ctrl+Shift+Delete)
2. Hard refresh (Ctrl+Shift+R)
3. Check files in public_html: `ls -la /home/user/public_html/css/`

### Composer Not Found
```bash
# Download composer.phar to app root
curl -sS https://getcomposer.org/composer.phar -o composer.phar
```

### public_html Not Found
1. Update `PUBLIC_HTML` path in script
2. Create directory: `mkdir -p /home/user/public_html`
3. Set permissions: `chmod 755 /home/user/public_html`

### Migrations Fail
1. Check `.env` database credentials
2. Test connection: `php artisan tinker` → `DB::connection()->getPdo()`
3. Check database server is running

## Automated Deployments

### Windows Task Scheduler
```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-NoProfile -File C:\wamp64\www\ct\deploy.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -Action $action -Trigger $trigger `
  -TaskName "CapeTennisDeploy"
```

### Linux Cron
```bash
# Daily at 2 AM
0 2 * * * /var/www/capetennis/deploy.sh production >> /var/www/capetennis/logs/cron.log 2>&1

# Edit: crontab -e
```

## Configuration Variables

Edit `deploy.config` for quick reference of all available variables:

```bash
# Key variables
APP_PATH=/var/www/capetennis              # Laravel app root
PUBLIC_HTML=/home/user/public_html        # Web server root
GIT_BRANCH=main                           # Branch to deploy
BACKUP_ENV_FILE=true                      # Backup .env
RUN_MIGRATIONS=true                       # Run migrations
SYNC_FOLDERS="css js images vendors"      # What to sync
```

## GitHub Integration

Both scripts support git branches:

```bash
# Check current branch
cd /var/www/capetennis && git branch -a

# Deploy specific branch (edit script first)
GIT_BRANCH="player-update"
./deploy.sh production
```

## Support & Help

**For Cape Tennis support:**
- Email: support@capetennis.co.za

**Before asking for help:**
1. Check deployment logs
2. Verify all paths are correct
3. Test prerequisites: `php -v`, `composer -V`, `git --version`
4. Check file permissions: `ls -la`

## File Structure After Deploy

```
App Directory (Git)
C:\wamp64\www\ct\                or  /var/www/capetennis/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/                       # Source
│   ├── css/
│   ├── js/
│   ├── images/
│   └── ...
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env
├── logs/
│   └── deploy_*.log
└── deploy.sh

Web Root (Served)
C:\wamp64\www\public_html\      or  /home/user/public_html/
├── css/                         # Synced from public/
├── js/                          # Synced from public/
├── images/                      # Synced from public/
├── vendors/                     # Synced from public/
└── ...
```

## Next Steps

1. **Configure paths** in `deploy.ps1` or `deploy.sh`
2. **Test locally** before production use
3. **Set up automated deployments** (cron/Task Scheduler)
4. **Review logs** after first deployment
5. **Document any custom changes** for your team

---

Based on jouberttennis.co.za deployment architecture with asset syncing support.
