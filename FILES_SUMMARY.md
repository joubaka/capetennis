# Deploy Scripts - File Summary

All deployment files created for Cape Tennis (similar to jouberttennis.co.za setup).

## 📋 Main Scripts

### `deploy.ps1` - Windows PowerShell Script
**Use for:** Windows development machines, local deployments
**Features:**
- Pulls latest code (if git available)
- Installs Composer dependencies
- Runs database migrations
- Clears caches and optimizes
- **Syncs public assets to separate web root**
- Logs all operations

**Run:**
```powershell
.\deploy.ps1                          # Local environment
.\deploy.ps1 -environment production  # Production
.\deploy.ps1 -skipMigrations          # Skip DB migrations
```

**Configure:**
- Edit lines 11-13 in `deploy.ps1`
- Set `$APP_PATH`, `$PUBLIC_HTML`, `$GIT_BRANCH`

---

### `deploy.sh` - Linux Bash Script
**Use for:** Linux production servers, shared hosting
**Features:**
- Same as PowerShell version, bash compatible
- Git integration (pulls before deploy)
- Compatible with cron for automated deployments
- Handles rsync and fallback to cp
- **Syncs public assets to separate web root**

**Run:**
```bash
./deploy.sh production                            # Production
./deploy.sh development                           # Development
./deploy.sh production --skip-migrations          # Skip migrations
```

**Configure:**
- Edit lines 5-7 in `deploy.sh`
- Set `APP_PATH`, `PUBLIC_HTML`, `GIT_BRANCH`

---

### `deploy.config` - Configuration Template
**Use for:** Reference of all available variables
**Contains:**
- App paths
- Database settings
- Asset sync folders
- Backup settings
- Notifications
- Service restart flags

---

## 📖 Documentation Files

### `README_DEPLOY.md` - Complete Overview
**Read this first** for:
- High-level architecture explanation
- All deployment steps
- Branch support
- Logging info
- Troubleshooting overview

### `DEPLOY_SETUP.md` - Detailed Setup Guide
**Read for:**
- Step-by-step configuration
- Finding your web root on shared hosting
- What gets synced and why
- Detailed troubleshooting with solutions
- GitHub branch management
- Scheduling automated deployments

### `QUICKSTART.md` - Quick Reference Card
**Quick lookup for:**
- Common commands
- Paths on your system
- Common issues & fixes
- Logs location
- Which branch to deploy

### `DEPLOY_CHECKLIST.md` - Pre-Deploy Verification
**Complete before first deploy:**
- Verify all prerequisites installed
- Check directory structure
- Confirm script configuration
- Test database connection
- Verify git setup
- Post-deploy verification steps

### `DEPLOYMENT.md` - Full Documentation (Original)
**Original comprehensive guide** with:
- Detailed prerequisites
- All script features
- Customization examples
- Advanced configuration
- Scheduling details

---

## 🚀 Quick Start Path

1. **Read:** `README_DEPLOY.md` (5 min)
2. **Check:** `DEPLOY_CHECKLIST.md` (verify your setup)
3. **Configure:** Edit `deploy.ps1` or `deploy.sh` (3-5 min)
4. **Run:** `.\deploy.ps1` or `./deploy.sh production`
5. **Troubleshoot:** Check `QUICKSTART.md` or `DEPLOY_SETUP.md`

---

## 🎯 Key Features

### Asset Syncing (Critical for Shared Hosting)
Syncs from app's public folder to web root:
- ✅ `public/css/` → `public_html/css/`
- ✅ `public/js/` → `public_html/js/`
- ✅ `public/images/` → `public_html/images/`
- ✅ `public/vendors/` → `public_html/vendors/`
- ✅ Root files like `favicon.ico`, `manifest.json`

### Safety Features
- ✅ Prerequisite checking (PHP, Composer, Git)
- ✅ Environment file backups
- ✅ Error logging and reporting
- ✅ Validation on critical operations
- ✅ Graceful error handling

### Flexibility
- ✅ Skip migrations if needed
- ✅ Skip dependency installation
- ✅ Choose environment (local/production)
- ✅ Select git branch
- ✅ Multiple logging options

---

## 📁 File Locations

```
C:\wamp64\www\ct\                    (Windows)
or
/var/www/capetennis/                 (Linux)

├── deploy.ps1                        # Windows script
├── deploy.sh                         # Linux script
├── deploy.config                     # Config template
├── README_DEPLOY.md                  # Overview
├── DEPLOY_SETUP.md                   # Setup guide
├── QUICKSTART.md                     # Quick reference
├── DEPLOY_CHECKLIST.md               # Pre-deploy check
├── DEPLOYMENT.md                     # Full docs
├── app/
├── public/
│   ├── css/                          ← Source
│   ├── js/                           ← Source
│   └── images/                       ← Source
├── storage/
├── .env
└── logs/                             # Created by deploy
    └── deploy_YYYY-MM-DD_HH-MM-SS.log
```

---

## ⚙️ Configuration Steps

### Step 1: Edit Script

**Windows (`deploy.ps1`):**
```powershell
# Line 11-13
$APP_PATH = "C:\wamp64\www\ct"
$PUBLIC_HTML = "C:\wamp64\www\public_html"
$GIT_BRANCH = "main"
```

**Linux (`deploy.sh`):**
```bash
# Line 5-7
APP_PATH="/var/www/capetennis"
PUBLIC_HTML="/home/user/public_html"
GIT_BRANCH="main"
```

### Step 2: Verify Paths
```bash
# Windows
Test-Path "C:\wamp64\www\ct"
Test-Path "C:\wamp64\www\public_html"

# Linux
ls -la /var/www/capetennis
ls -la /home/user/public_html
```

### Step 3: Run Deployment
```powershell
# Windows
.\deploy.ps1
```

```bash
# Linux
chmod +x deploy.sh
./deploy.sh production
```

---

## 🔍 Troubleshooting

| Problem | Solution |
|---------|----------|
| CSS/JS not updating | Clear browser cache + hard refresh, check logs |
| "Not found" errors | Verify paths in script, update `$APP_PATH` and `$PUBLIC_HTML` |
| Composer fails | Download `composer.phar` to app root |
| Migrations error | Check `.env` database credentials |
| Permission denied | Use `sudo` or check file ownership |

**See `DEPLOY_SETUP.md` for detailed troubleshooting.**

---

## 📚 Read Next

- **For setup:** `DEPLOY_SETUP.md`
- **For quick help:** `QUICKSTART.md`
- **For checklist:** `DEPLOY_CHECKLIST.md`
- **For full docs:** `DEPLOYMENT.md`

---

## 📧 Support

Cape Tennis Support: **support@capetennis.co.za**

For deployment issues:
1. Check the log file
2. Run checklist from `DEPLOY_CHECKLIST.md`
3. Review `QUICKSTART.md` for common fixes
4. Consult `DEPLOY_SETUP.md` troubleshooting section

---

**Your setup uses the same architecture as jouberttennis.co.za with app code and web root separated.**
