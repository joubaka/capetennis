# 🚀 Cape Tennis Complete Deployment Solution

## 📦 What You Have

Fully automated deployment system for Cape Tennis with:
- ✅ Windows PowerShell script
- ✅ Linux Bash script  
- ✅ Automatic asset syncing (critical for shared hosting!)
- ✅ Database migrations
- ✅ Cache management
- ✅ Comprehensive documentation

## 📋 Files Created (11 Total)

### 🔧 Scripts (EDIT THESE FIRST)

| File | Purpose | Edit? |
|------|---------|-------|
| **deploy.ps1** (6.7 KB) | Windows deployment | **YES** - lines 11-13 |
| **deploy.sh** (7.0 KB) | Linux deployment | **YES** - lines 5-7 |
| **deploy.config** (2.5 KB) | Configuration template | Optional reference |

### 📖 Documentation (READ IN ORDER)

| # | File | Time | Content |
|---|------|------|---------|
| 1 | **START_HERE.md** (6.3 KB) | 5 min | Quick start & overview |
| 2 | **VISUAL_GUIDE.md** (8.7 KB) | 5 min | Diagrams & flowcharts |
| 3 | **QUICKSTART.md** (3.6 KB) | 5 min | Quick reference card |
| 4 | **README_DEPLOY.md** (6.6 KB) | 10 min | Complete overview |
| 5 | **DEPLOY_SETUP.md** (5.4 KB) | 15 min | Detailed setup guide |
| 6 | **DEPLOY_CHECKLIST.md** (7.0 KB) | 20 min | Pre-deploy verification |
| 7 | **DEPLOYMENT.md** (5.8 KB) | 30 min | Full reference |
| 8 | **FILES_SUMMARY.md** (6.5 KB) | 5 min | File inventory |

## ⚡ Quick Start (3 Steps)

### Step 1: Configure Script
Edit `deploy.ps1` or `deploy.sh` (change 2-3 lines):

**Windows:**
```powershell
$APP_PATH = "C:\wamp64\www\ct"           # Line 11
$PUBLIC_HTML = "C:\wamp64\www\public_html"  # Line 12
```

**Linux:**
```bash
APP_PATH="/var/www/capetennis"           # Line 5
PUBLIC_HTML="/home/user/public_html"     # Line 6
```

### Step 2: Run Deploy
```powershell
# Windows
.\deploy.ps1

# Linux  
chmod +x deploy.sh
./deploy.sh production
```

### Step 3: Check Results
```bash
tail -f logs/deploy_*.log
# Website should load with CSS/JS working
```

## 🎯 Which File to Read?

**Just want to deploy?**
→ Read `START_HERE.md` then run the script

**Setting up for first time?**
→ Read `START_HERE.md` then `DEPLOY_CHECKLIST.md`

**Need quick reference?**
→ Check `QUICKSTART.md` (bookmark this!)

**Understanding the system?**
→ Read `VISUAL_GUIDE.md` then `README_DEPLOY.md`

**Detailed setup help?**
→ Follow `DEPLOY_SETUP.md` step-by-step

**Troubleshooting?**
→ Check `QUICKSTART.md` then `DEPLOY_SETUP.md`

**Everything?**
→ Read in this order:
1. START_HERE.md
2. VISUAL_GUIDE.md
3. README_DEPLOY.md
4. DEPLOY_SETUP.md
5. DEPLOY_CHECKLIST.md (before deploying)
6. DEPLOYMENT.md (reference)

## 🔑 Key Concept: Your Setup

Your Cape Tennis has the same architecture as jouberttennis.co.za:

```
App Folder              Web Root
/var/www/capetennis/    /home/user/public_html/
├── app/                ├── css/
├── public/             ├── js/
│  ├── css/      ────→  ├── images/
│  ├── js/       ────→  └── ...
│  └── images/   ────→
└── ...
```

**Critical:** Deploy script automatically syncs `public/` → `public_html/`

This is why CSS, JS, and images update when you deploy!

## ✅ Pre-Deploy Checklist

Before running first deployment:

- [ ] Edit script (change app path & web root)
- [ ] PHP installed: `php -v`
- [ ] Composer installed: `composer -V`
- [ ] Git installed: `git --version`
- [ ] `.env` file exists with DB credentials
- [ ] `composer.json` exists
- [ ] `public/` folder has CSS, JS, images
- [ ] Web root folder exists/is writable

**See `DEPLOY_CHECKLIST.md` for full list**

## 🚀 Deployment Process

Each deploy does this (in order):

1. ✅ Check prerequisites
2. ✅ Backup .env
3. ✅ Pull latest code
4. ✅ Install dependencies
5. ✅ Clear caches
6. ✅ Run migrations
7. ✅ Publish assets
8. ✅ Set permissions
9. ✅ Optimize
10. ✅ **SYNC ASSETS** (CSS, JS to web root)
11. ✅ Restart services

All logged to: `logs/deploy_YYYY-MM-DD_HH-MM-SS.log`

## 📚 Documentation Structure

```
Quick/Easy Reads:
├─ START_HERE.md          The entry point
├─ VISUAL_GUIDE.md        Diagrams & flows
├─ QUICKSTART.md          Commands & fixes
└─ FILES_SUMMARY.md       File inventory

Setup Guides:
├─ README_DEPLOY.md       Overview & how it works
├─ DEPLOY_SETUP.md        Step-by-step configuration
└─ DEPLOY_CHECKLIST.md    Verification checklist

Reference:
├─ DEPLOYMENT.md          Full documentation
└─ deploy.config          Config template
```

## 🎛️ Run Options

### Windows
```powershell
.\deploy.ps1                          # Basic (local env)
.\deploy.ps1 -environment production  # Production
.\deploy.ps1 -skipMigrations          # Skip DB changes
.\deploy.ps1 -skipDeps                # Skip composer
```

### Linux
```bash
./deploy.sh production                         # Standard
./deploy.sh development                       # Dev env
./deploy.sh production --skip-migrations      # Skip DB
./deploy.sh production --skip-deps            # Skip composer
```

## 📍 Your Paths

**Windows Development:**
- App: `C:\wamp64\www\ct\`
- Web: `C:\wamp64\www\public_html\`

**Linux Production (typical):**
- App: `/var/www/capetennis/`
- Web: `/home/user/public_html/` (ask hosting provider!)

**Or cPanel-style:**
- App: `/var/www/capetennis/`
- Web: `/home/user/domains/capetennis.co.za/public_html/`

## 🔄 Git Branches

Deploy from any branch:

```powershell
# Windows - edit deploy.ps1
$GIT_BRANCH = "player-update"    # Change this
.\deploy.ps1
```

```bash
# Linux - edit deploy.sh
GIT_BRANCH="version-2"            # Change this
./deploy.sh production
```

Available branches:
- `main` - Production
- `player-update` - Feature branch
- `version-2` - Alternative
- `develop` - Development

## 🤖 Automated Deployments

**Daily at 2 AM (optional setup):**

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

See `DEPLOY_SETUP.md` → Scheduling section for more info.

## 🐛 Common Issues

| Problem | Solution |
|---------|----------|
| CSS/JS not updating | Browser cache clear + hard refresh (Ctrl+Shift+R) |
| Assets not syncing | Verify web root path in script, check logs |
| Composer not found | Download `composer.phar` to app root |
| Migrations fail | Check `.env` database credentials |
| "Not found" errors | Update `$APP_PATH` and `$PUBLIC_HTML` in script |

See `QUICKSTART.md` for more troubleshooting.

## 🆘 Need Help?

1. **Quick help:** Check `QUICKSTART.md` (bookmark it!)
2. **Step-by-step:** Follow `DEPLOY_SETUP.md`
3. **Verification:** Use `DEPLOY_CHECKLIST.md`
4. **Full reference:** See `DEPLOYMENT.md`
5. **Email support:** support@capetennis.co.za

## 📊 File Sizes

```
deploy.ps1              6.7 KB    Windows script
deploy.sh               7.0 KB    Linux script
deploy.config           2.5 KB    Config template

START_HERE.md           6.3 KB    Entry point
VISUAL_GUIDE.md         8.7 KB    Diagrams
QUICKSTART.md           3.6 KB    Quick ref
README_DEPLOY.md        6.6 KB    Overview
DEPLOY_SETUP.md         5.4 KB    Setup guide
DEPLOY_CHECKLIST.md     7.0 KB    Checklist
DEPLOYMENT.md           5.8 KB    Full docs
FILES_SUMMARY.md        6.5 KB    Inventory

Total: ~66 KB of documentation
```

## 🎓 Learning Path

**New to deployment?**
```
START_HERE.md
  ↓
VISUAL_GUIDE.md
  ↓
README_DEPLOY.md
  ↓
DEPLOY_CHECKLIST.md
  ↓
Run deployment!
```

**Experienced, just need reference?**
```
QUICKSTART.md (bookmark this!)
  +
DEPLOY_SETUP.md (if issues)
```

**Want to automate?**
```
DEPLOY_SETUP.md → Scheduling section
```

## ✨ What Makes This Special

✅ **Asset Syncing** - Automatically copies CSS/JS to web root (critical for your setup!)
✅ **Backup** - Saves `.env` before changes
✅ **Verification** - Checks PHP/Composer/Git before starting
✅ **Detailed Logging** - Everything recorded for troubleshooting
✅ **Flexible** - Can skip migrations, deps, run different branches
✅ **Documentation** - 8 docs covering every scenario
✅ **Windows & Linux** - Works on both platforms
✅ **No Dependencies** - Uses only standard tools

## 🎯 Next Steps

1. Open **START_HERE.md**
2. Edit your script (2-3 lines)
3. Run deployment
4. Check logs
5. Test website

**Done! Your deploy is configured.**

---

## 📞 Support

**Cape Tennis Support:** support@capetennis.co.za

**Quick Questions:** Check `QUICKSTART.md`
**Setup Help:** Follow `DEPLOY_SETUP.md`
**Before Deploying:** Complete `DEPLOY_CHECKLIST.md`

---

**Based on jouberttennis.co.za deployment architecture**

**Last Updated:** 2024
**Version:** 1.0
**Status:** ✅ Ready to use
