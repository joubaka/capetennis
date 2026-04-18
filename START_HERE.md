# 📦 Cape Tennis Deploy Scripts - Start Here

## What You Have

Complete deployment automation for Cape Tennis, configured for your shared hosting setup (app + separate web root).

## 🚀 3-Step Quick Start

### 1. Configure
Edit the script for your paths:

**Windows:** `deploy.ps1` lines 11-13
```powershell
$APP_PATH = "C:\wamp64\www\ct"
$PUBLIC_HTML = "C:\wamp64\www\public_html"
```

**Linux:** `deploy.sh` lines 5-7
```bash
APP_PATH="/var/www/capetennis"
PUBLIC_HTML="/home/user/public_html"
```

### 2. Run
```powershell
.\deploy.ps1                    # Windows
```

```bash
chmod +x deploy.sh
./deploy.sh production          # Linux
```

### 3. Check Logs
```bash
tail -f logs/deploy_*.log
```

## 📖 Documentation (Pick One)

| File | When | Time |
|------|------|------|
| **FILES_SUMMARY.md** | Overview of all files | 2 min |
| **QUICKSTART.md** | Quick commands & fixes | 5 min |
| **README_DEPLOY.md** | How it works, setup | 10 min |
| **DEPLOY_SETUP.md** | Detailed configuration | 15 min |
| **DEPLOY_CHECKLIST.md** | Before first deploy | 20 min |
| **DEPLOYMENT.md** | Full reference | 30 min |

## 🎯 By Your Need

**I just want to deploy**
→ Read `QUICKSTART.md` then run the script

**I'm setting up for first time**
→ Read `README_DEPLOY.md` then follow `DEPLOY_CHECKLIST.md`

**I need to troubleshoot**
→ Check `QUICKSTART.md` then `DEPLOY_SETUP.md`

**I need to customize**
→ Read `DEPLOYMENT.md`

**I want automated deployments (cron)**
→ See `DEPLOY_SETUP.md` → Scheduling section

## 📋 Files Created

```
deploy.ps1               Windows deployment script
deploy.sh                Linux deployment script  
deploy.config            Configuration template

FILES_SUMMARY.md         Overview of all files
QUICKSTART.md            Quick reference card
README_DEPLOY.md         Complete overview
DEPLOY_SETUP.md          Detailed setup guide
DEPLOY_CHECKLIST.md      Pre-deploy verification
DEPLOYMENT.md            Full documentation
```

## ⚡ Key Feature: Asset Syncing

Your setup syncs CSS, JS, images from app's `public/` to your web root (`public_html/`):

```
App Folder                Web Root
public/css/  ────────→  public_html/css/
public/js/   ────────→  public_html/js/
public/images/ ──────→  public_html/images/
```

This is critical for shared hosting where app and web root are separate!

## ✅ Pre-Deploy Checklist

- [ ] Script configuration updated (paths)
- [ ] `.env` file exists with DB credentials
- [ ] `composer.json` exists in app folder
- [ ] PHP installed: `php -v`
- [ ] Composer installed: `composer -V`
- [ ] Git installed: `git --version`
- [ ] Git repo initialized: `git status`
- [ ] Web root folder exists/is writable

**See `DEPLOY_CHECKLIST.md` for full checklist.**

## 🔄 Deployment Happens In This Order

1. Check prerequisites (PHP, Composer, Git)
2. Backup `.env` file
3. Pull latest code (git pull)
4. Install dependencies (composer install)
5. Clear caches
6. Run migrations (database updates)
7. Publish assets (storage link)
8. Set permissions
9. Optimize application
10. **Sync assets to web root** ← Critical for your setup!
11. Restart services (optional)

## 🎛️ Run Options

```powershell
# Windows
.\deploy.ps1                              # Basic
.\deploy.ps1 -environment production      # Production
.\deploy.ps1 -skipMigrations              # No DB changes
.\deploy.ps1 -skipDeps                    # No composer
```

```bash
# Linux
./deploy.sh production                         # Default
./deploy.sh development                       # Dev env
./deploy.sh production --skip-migrations      # No DB changes
./deploy.sh production --skip-deps            # No composer
```

## 📍 Your Paths

**Windows:**
- App: `C:\wamp64\www\ct\`
- Web: `C:\wamp64\www\public_html\`

**Linux (typical):**
- App: `/var/www/capetennis/`
- Web: `/home/user/public_html/` (ask hosting)

**Or domain-specific (cPanel):**
- App: `/var/www/capetennis/`
- Web: `/home/user/domains/capetennis.co.za/public_html/`

*Not sure? Check with your hosting provider!*

## 🐛 Common Issues

| Issue | Fix |
|-------|-----|
| CSS/JS not updating | Browser cache clear + hard refresh (Ctrl+Shift+R) |
| Assets not syncing | Check web root path in script, verify it exists |
| Migrations fail | Check `.env` database credentials |
| Composer not found | Download `composer.phar` to app root |

**More solutions:** See `QUICKSTART.md` or `DEPLOY_SETUP.md`

## 📊 Deployment Logs

Created after each deployment:
```
logs/deploy_YYYY-MM-DD_HH-MM-SS.log
```

View:
```bash
tail -f logs/deploy_*.log
```

## 🔐 Supported Git Branches

Deploy from any branch by editing the script:

```powershell
# Windows
$GIT_BRANCH = "main"               # Change this
.\deploy.ps1 -environment production
```

```bash
# Linux
GIT_BRANCH="version-2"             # Change this
./deploy.sh production
```

Common branches:
- `main` - Primary branch
- `player-update` - Feature branch  
- `version-2` - Alternative version
- `develop` - Development branch

## 🤖 Automated Deployments

**Daily at 2 AM (optional):**

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
0 2 * * * /var/www/capetennis/deploy.sh production >> /var/www/capetennis/logs/cron.log 2>&1
```

## 📞 Support

**Cape Tennis Support:** support@capetennis.co.za

**Before asking for help:**
1. Check deployment log
2. Verify paths are correct
3. Confirm PHP/Composer installed
4. Check database credentials

## 🎓 Learn More

- **Quick commands:** `QUICKSTART.md`
- **How it works:** `README_DEPLOY.md`
- **Step-by-step setup:** `DEPLOY_SETUP.md`
- **Before first deploy:** `DEPLOY_CHECKLIST.md`
- **Everything:** `DEPLOYMENT.md`

---

**Ready?** Start with your configuration, then run the script!

```powershell
# Windows
.\deploy.ps1
```

```bash
# Linux
./deploy.sh production
```

**Questions?** See `QUICKSTART.md` or `README_DEPLOY.md`
