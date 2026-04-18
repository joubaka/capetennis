# 🎯 Cape Tennis Deploy - One-Page Reference

## What You Have

Automated deployment for Cape Tennis (12 files, ~69 KB):
- ✅ PowerShell script (Windows)
- ✅ Bash script (Linux)  
- ✅ 9 comprehensive documentation files

## Your Setup (Critical to Understand)

```
App Code                 → Web Root
/var/www/capetennis/    → /home/user/public_html/

Deploy syncs public/ → public_html/ (CSS, JS, images)
```

## Quick Setup

### 1. Edit Script
Edit these 2 variables:

**Windows `deploy.ps1` line 11-12:**
```powershell
$APP_PATH = "C:\wamp64\www\ct"
$PUBLIC_HTML = "C:\wamp64\www\public_html"
```

**Linux `deploy.sh` line 5-6:**
```bash
APP_PATH="/var/www/capetennis"
PUBLIC_HTML="/home/user/public_html"
```

### 2. Run Deploy
```powershell
.\deploy.ps1                              # Windows
```

```bash
chmod +x deploy.sh && ./deploy.sh prod    # Linux
```

### 3. Check Results
```bash
tail -f logs/deploy_*.log
```

## All Commands

```powershell
# WINDOWS
.\deploy.ps1                            # Basic
.\deploy.ps1 -environment production    # Production
.\deploy.ps1 -skipMigrations            # Skip DB
.\deploy.ps1 -skipDeps                  # Skip composer
```

```bash
# LINUX
./deploy.sh production                       # Standard
./deploy.sh development                     # Dev
./deploy.sh production --skip-migrations    # Skip DB
```

## What Deploys Do

1. ✅ Check PHP/Composer/Git installed
2. ✅ Backup .env
3. ✅ Pull latest code (git)
4. ✅ Install dependencies
5. ✅ Clear caches
6. ✅ Run migrations
7. ✅ Set permissions
8. ✅ Optimize app
9. ✅ **Sync assets to web root** ← Key!
10. ✅ Restart services

## Read Documentation

| Start Here | Time | Then | Then |
|-----------|------|------|------|
| **INDEX.md** | 2m | START_HERE.md | DEPLOY_CHECKLIST.md |
| Quick Lookup | 2m | **QUICKSTART.md** | (for common issues) |
| Setup Help | 5m | **DEPLOY_SETUP.md** | DEPLOY_CHECKLIST.md |
| Everything | 30m | **DEPLOYMENT.md** | — |

## Your Paths

| Platform | App Path | Web Root |
|----------|----------|----------|
| Windows | `C:\wamp64\www\ct\` | `C:\wamp64\www\public_html\` |
| Linux | `/var/www/capetennis/` | `/home/user/public_html/` |

**Not sure about web root?** Ask hosting provider or check `DEPLOY_SETUP.md`

## Branches to Deploy

```
main              (production stable)
player-update     (feature branch)
version-2         (alternative)
develop           (development)
```

**To change branch:** Edit script, set `$GIT_BRANCH` or `GIT_BRANCH`

## Pre-Deploy Checklist

- [ ] Script configured (paths updated)
- [ ] `.env` exists with DB credentials  
- [ ] `php -v` works
- [ ] `composer -V` works
- [ ] `git --version` works
- [ ] `public/` has CSS, JS, images
- [ ] Web root folder exists

**Full checklist:** See `DEPLOY_CHECKLIST.md`

## Common Issues

| Issue | Fix |
|-------|-----|
| CSS/JS not updating | Clear cache (Ctrl+Shift+Delete) + hard refresh (Ctrl+Shift+R) |
| "public_html not found" | Update path in script, create folder |
| "Composer not found" | Download `composer.phar` to app root |
| Migrations fail | Check `.env` database credentials |
| Assets not syncing | Check logs, verify web root permissions |

**More help:** See `QUICKSTART.md` troubleshooting

## Logs

Located at:
```
logs/deploy_YYYY-MM-DD_HH-MM-SS.log
```

View live:
```bash
tail -f logs/deploy_*.log
```

## Files Overview

### Scripts (EDIT THESE)
- `deploy.ps1` - Windows deployment
- `deploy.sh` - Linux deployment
- `deploy.config` - Configuration template

### Documentation
- `INDEX.md` - Master index (read first!)
- `START_HERE.md` - Quick start guide
- `QUICKSTART.md` - Commands & fixes
- `VISUAL_GUIDE.md` - Diagrams & flows
- `README_DEPLOY.md` - Complete overview
- `DEPLOY_SETUP.md` - Step-by-step setup
- `DEPLOY_CHECKLIST.md` - Pre-deploy check
- `DEPLOYMENT.md` - Full reference
- `FILES_SUMMARY.md` - File inventory

## Git Integration

```bash
# Check current branch
git branch

# Pull manually
git pull origin main

# Deploy from specific branch (edit script first)
./deploy.sh production
```

## Scheduled Deployments

### Windows (every day at 2 AM)
```powershell
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-NoProfile -File C:\wamp64\www\ct\deploy.ps1"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -Action $action -Trigger $trigger `
  -TaskName "CapeTennisDeploy"
```

### Linux (every day at 2 AM)
```bash
# Add to crontab -e
0 2 * * * /var/www/capetennis/deploy.sh production >> /var/www/capetennis/logs/cron.log 2>&1
```

See `DEPLOY_SETUP.md` → Scheduling for more.

## Support

**Cape Tennis:** support@capetennis.co.za

**Before asking:**
1. Check `logs/deploy_*.log`
2. Run DEPLOY_CHECKLIST.md
3. Review QUICKSTART.md

## Next Step

👉 **Open `INDEX.md`** for complete file guide
👉 **Or open `START_HERE.md`** for quick start
👉 **Or just edit script and run!**

---

**Bookmark:** `QUICKSTART.md` (for quick commands)

**Questions?** See `INDEX.md` → Which file to read?
