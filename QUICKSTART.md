# Quick Deploy Reference

## Your Cape Tennis Setup (Like JTA)

```
Cape Tennis App           →  Separate Web Root
/var/www/capetennis/      →  /home/user/public_html/
(or C:\wamp64\www\ct\)        (or C:\wamp64\www\public_html\)

Deploy pulls code from app directory and syncs public/ assets to web root.
```

## Quick Start

### Windows (Local)

```powershell
# Basic deploy
.\deploy.ps1

# Production deploy
.\deploy.ps1 -environment production

# Skip migrations
.\deploy.ps1 -skipMigrations

# Skip dependencies
.\deploy.ps1 -skipDeps
```

**First time setup:**
1. Edit `deploy.ps1` lines 11-14
2. Set `$APP_PATH` to your Laravel app
3. Set `$PUBLIC_HTML` to your web root
4. Save and run

### Linux (Production)

```bash
# Make executable
chmod +x deploy.sh

# Deploy to production
./deploy.sh production

# Skip migrations
./deploy.sh production --skip-migrations

# Combined
./deploy.sh production --skip-migrations --skip-deps
```

**First time setup:**
1. Edit `deploy.sh` lines 5-11
2. Set `APP_PATH` to your Laravel app
3. Set `PUBLIC_HTML` to your web root
4. `chmod +x deploy.sh`
5. Run it

## What Happens During Deploy

✅ Check PHP/Composer installed  
✅ Backup `.env` file  
✅ Pull latest code (Linux only, git)  
✅ Install Composer dependencies  
✅ Clear all caches  
✅ Run database migrations  
✅ Create storage symlink  
✅ Set file permissions  
✅ Cache config & routes  
✅ **Sync assets** (CSS, JS, images → `public_html`)  
✅ Restart services (optional)

## Finding Your Web Root

```bash
# SSH to server
ssh user@example.com

# Check common locations
ls -la ~/
ls -la ~/domains/
ls -la /home/user/public_html
ls -la /var/www/html

# Ask hosting provider if unsure!
```

## Common Issues

| Issue | Solution |
|-------|----------|
| CSS/JS not updating | Clear browser cache (Ctrl+Shift+Delete) + hard refresh (Ctrl+Shift+R) |
| "Composer not found" | Download `composer.phar` to app root |
| "public_html not found" | Update `PUBLIC_HTML` path in script |
| Migrations fail | Check `.env` database credentials |
| Permission denied | Run with `sudo` or check folder ownership |

## Important Paths

**Cape Tennis:**
```
App:       C:\wamp64\www\ct\                 (or /var/www/capetennis)
Web Root:  C:\wamp64\www\public_html\        (or /home/user/public_html)
Assets:    public_html/css/, js/, images/
Logs:      ct/logs/deploy_YYYY-MM-DD_*.log
```

## Branch Names

Common branches to deploy from:
- `main` - Primary production branch
- `player-update` - Feature branch
- `version-2` - Version 2 (if using)
- `develop` - Development branch

Edit script to change branch before deploying.

## Automated Deployments

### Windows Task Scheduler
```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-NoProfile -File C:\wamp64\www\ct\deploy.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -Action $action -Trigger $trigger `
  -TaskName "CapeTennisDeploy" -RunLevel Highest
```

### Linux Cron
```bash
crontab -e
# Add line:
0 2 * * * /var/www/capetennis/deploy.sh production >> /var/www/capetennis/logs/cron.log 2>&1
```

## Logs

All deploys logged to:
- Windows: `C:\wamp64\www\ct\logs\deploy_*.log`
- Linux: `/var/www/capetennis/logs/deploy_*.log`

View logs:
```bash
tail -f logs/deploy_*.log
```

## Support

Withdrawal/Support Email: **support@capetennis.co.za**

For deploy help:
1. Check logs
2. Verify paths correct
3. Test: `php -v`, `composer -V`, `git --version`
4. Check permissions: `ls -la`
