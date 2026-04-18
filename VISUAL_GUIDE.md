# 🎯 Cape Tennis Deploy - Visual Guide

## Your Architecture

```
┌─────────────────────────────────┐
│   Git Repository (GitHub)       │
│   (joubaka/capetennis)          │
│   Branches: main, player-update │
└──────────────┬──────────────────┘
               │ git pull
               ▼
┌─────────────────────────────────┐
│   Laravel App Directory         │
│   C:\wamp64\www\ct\ (Windows)  │ ┌─ Deploy runs here
│   /var/www/capetennis/ (Linux)  │
│                                 │
│  ├── app/                       │
│  ├── public/                    │──┐
│  │  ├── css/                    │  │
│  │  ├── js/                     │  │  Synced to web root
│  │  ├── images/                 │  │  (Critical!)
│  │  └── vendors/                │  │
│  ├── storage/                   │  │
│  ├── .env (Database config)     │  │
│  ├── composer.json              │  │
│  └── deploy.ps1 / deploy.sh     │  │
└──────────────┬──────────────────┘  │
               │                     │
               │ Asset Sync          │
               │ (CSS,JS,images)     │
               ▼ ◄──────────────────┘
┌─────────────────────────────────┐
│   Web Root (Browser sees)       │
│   C:\wamp64\www\public_html\   │
│   /home/user/public_html/       │
│                                 │
│  ├── css/    ◄─ Synced from app│
│  ├── js/     ◄─ Synced from app│
│  ├── images/ ◄─ Synced from app│
│  └── vendors/ ◄─ Synced from app│
└─────────────────────────────────┘
               ▲
               │ HTTP Requests
               │ (Browser)
┌─────────────────────────────────┐
│        Your Browser             │
│   User sees: CSS, JS, Images    │
└─────────────────────────────────┘
```

## Deployment Flow

```
START
  │
  ├─► 🔍 Check Prerequisites (PHP, Composer, Git)
  │
  ├─► 💾 Backup .env file
  │
  ├─► 📥 Pull Latest Code (git pull)
  │
  ├─► 📦 Install Dependencies (composer install)
  │
  ├─► 🧹 Clear Caches (config, route, view)
  │
  ├─► 🗄️  Run Migrations (database updates)
  │
  ├─► 🔗 Publish Assets (storage link)
  │
  ├─► 🔐 Update Permissions (chmod 755)
  │
  ├─► ⚡ Optimize Application (cache routes, config)
  │
  ├─► 📂 SYNC ASSETS (CSS, JS, images to web root) ◄─ KEY STEP!
  │
  ├─► 🔄 Restart Services (optional)
  │
  └─► ✅ SUCCESS! Check logs
```

## File Organization

```
Your Workspace
│
├─ 📜 START_HERE.md          ◄─ READ THIS FIRST!
├─ 📊 FILES_SUMMARY.md       ◄─ File overview
├─ ⚡ QUICKSTART.md          ◄─ Quick commands
│
├─ 🔧 SCRIPTS (Edit these)
│  ├─ deploy.ps1             ◄─ Windows (EDIT lines 11-13)
│  └─ deploy.sh              ◄─ Linux   (EDIT lines 5-7)
│
├─ 📖 DOCUMENTATION
│  ├─ README_DEPLOY.md       ◄─ Overview & how it works
│  ├─ DEPLOY_SETUP.md        ◄─ Step-by-step setup
│  ├─ DEPLOY_CHECKLIST.md    ◄─ Pre-deploy checklist
│  ├─ DEPLOYMENT.md          ◄─ Full reference
│  └─ deploy.config          ◄─ Config template
│
└─ 📝 app/
   ├─ composer.json
   ├─ .env                    ◄─ Database credentials
   ├─ public/
   │  ├─ css/                 ◄─ Gets synced
   │  ├─ js/                  ◄─ Gets synced
   │  └─ images/              ◄─ Gets synced
   └─ logs/
      └─ deploy_*.log         ◄─ Created after each deploy
```

## Configuration Checklist

```
┌─────────────────────────────────────┐
│ BEFORE FIRST DEPLOYMENT             │
└─────────────────────────────────────┘

Windows (deploy.ps1):
  □ $APP_PATH = "C:\wamp64\www\ct"
  □ $PUBLIC_HTML = "C:\wamp64\www\public_html"
  □ $GIT_BRANCH = "main"

Linux (deploy.sh):
  □ APP_PATH="/var/www/capetennis"
  □ PUBLIC_HTML="/home/user/public_html"
  □ GIT_BRANCH="main"

Verify:
  □ PHP installed: php -v
  □ Composer installed: composer -V
  □ Git installed: git --version
  □ Paths exist and readable
```

## One-Command Deploy

```
Windows:
$ .\deploy.ps1

Linux:
$ ./deploy.sh production

That's it! Script handles everything.
```

## After Deployment

```
✅ CHECK THESE:

Log File:
  Windows: C:\wamp64\www\ct\logs\deploy_*.log
  Linux:   /var/www/capetennis/logs/deploy_*.log

Website:
  □ Loads without errors
  □ CSS appears (styled correctly)
  □ JavaScript works
  □ No 404 errors in browser console

Assets Synced:
  □ CSS files exist in public_html/css/
  □ JS files exist in public_html/js/
  □ Images exist in public_html/images/

Database:
  □ php artisan migrate:status ✓
  □ No errors in laravel.log
```

## Troubleshooting Tree

```
ISSUE: Website not loading
├─ Check if PHP/Laravel running
├─ Check .env database credentials
└─ Review logs/deploy_*.log

ISSUE: CSS/JS not displaying
├─ Clear browser cache (Ctrl+Shift+Delete)
├─ Hard refresh (Ctrl+Shift+R)
├─ Check assets exist in public_html/
└─ Verify public_html path is correct

ISSUE: Assets not syncing
├─ Check deploy log for errors
├─ Verify public_html folder exists
├─ Verify public_html is writable
└─ Check paths in script

ISSUE: Migrations failed
├─ Check .env database credentials
├─ Verify database is running
├─ Run: php artisan migrate:status
└─ Check laravel.log

ISSUE: "Composer not found"
├─ Download composer.phar to app root
├─ Or install Composer globally
└─ Retry deployment
```

## Commands You'll Use

```
Windows:
  .\deploy.ps1                              # Basic deploy
  .\deploy.ps1 -environment production      # Production
  .\deploy.ps1 -skipMigrations              # No DB changes

Linux:
  ./deploy.sh production                    # Production
  ./deploy.sh production --skip-migrations  # No DB changes
  ./deploy.sh development                   # Development

Checking status:
  git status                                # Git changes
  git branch                                # Current branch
  composer validate                         # Composer check
  php artisan migrate:status                # Migrations
  tail -f logs/deploy_*.log                 # Live logs
```

## Support Contacts

```
🔧 Technical Issues:
   • Check logs/deploy_*.log
   • Review troubleshooting in QUICKSTART.md
   • See detailed guide in DEPLOY_SETUP.md

💬 Cape Tennis Support:
   📧 support@capetennis.co.za
   
🌐 Hosting Provider:
   • Ask about web root path
   • Confirm SSH access
   • Verify PHP/Composer available
```

## Key Differences from Standard Laravel

```
Standard Laravel:
  public/ IS the web root
  Browser sees everything in public/

Your Cape Tennis Setup:
  public/ is in APP FOLDER
  Web root is SEPARATE (public_html/)
  Deploy SYNCS public/ → public_html/
  
WHY?
  Shared hosting keeps app private
  Only web root served to internet
  Better security & separation
```

## Branch Names

```
main (or master)          ← Production
  └─ Deploy from this when stable

player-update             ← Feature branch
  └─ Deploy from this for testing

version-2                 ← Alternative
  └─ Deploy from this if needed

develop                   ← Development
  └─ Deploy from this for staging
```

## Next Steps

1. **Read:** START_HERE.md (this file)
2. **Configure:** Edit deploy.ps1 or deploy.sh
3. **Check:** DEPLOY_CHECKLIST.md
4. **Run:** `.\deploy.ps1` or `./deploy.sh production`
5. **Monitor:** tail -f logs/deploy_*.log

---

**Questions?** See QUICKSTART.md or README_DEPLOY.md
