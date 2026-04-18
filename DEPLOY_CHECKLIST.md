# Deploy Readiness Checklist

Complete this checklist before running your first deployment.

## Pre-Deployment Verification

### Local Machine / Development

- [ ] Laravel app exists: `C:\wamp64\www\ct\`
- [ ] `.env` file exists in app root
- [ ] `composer.json` exists in app root
- [ ] PHP is installed and in PATH: `php -v`
- [ ] Composer is installed: `composer -V`
- [ ] Git is installed: `git --version`
- [ ] App is initialized as git repo: `git remote -v`
- [ ] App can start: `php artisan serve` works

### Production Server (if deploying)

- [ ] SSH access works: `ssh user@example.com`
- [ ] Laravel app directory exists: `/var/www/capetennis/`
- [ ] `.env` file exists on server
- [ ] PHP CLI is installed: `php -v`
- [ ] Composer is installed: `composer -V`
- [ ] Git is installed: `git --version`
- [ ] Can connect to database from server
- [ ] `storage/` and `bootstrap/cache/` are writable

## Script Configuration

### Windows (deploy.ps1)

- [ ] Opened `deploy.ps1` in editor
- [ ] Found and updated these lines:
  ```powershell
  $APP_PATH = "C:\wamp64\www\ct"                    # Line 11
  $PUBLIC_HTML = "C:\wamp64\www\public_html"        # Line 12
  $GIT_BRANCH = "main"                              # Line 13
  ```
- [ ] Saved the file
- [ ] PowerShell execution policy allows running scripts:
  ```powershell
  Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
  ```

### Linux (deploy.sh)

- [ ] Opened `deploy.sh` in editor
- [ ] Found and updated these lines:
  ```bash
  APP_PATH="/var/www/capetennis"                    # Line 5
  PUBLIC_HTML="/home/user/public_html"              # Line 6
  GIT_BRANCH="main"                                 # Line 7
  ```
- [ ] Saved the file
- [ ] Made executable: `chmod +x deploy.sh`
- [ ] Tested script loads: `bash -n deploy.sh` (no errors)

## Directory Structure Verification

### Windows
```
C:\wamp64\www\ct\
├── app/                          ← Exists?
├── public/                        ← Exists?
│   ├── css/                       ← Exists?
│   ├── js/                        ← Exists?
│   └── images/                    ← Exists?
├── .env                           ← Exists? Contains DB info?
├── composer.json                  ← Exists?
└── deploy.ps1                     ← Exists and updated?

C:\wamp64\www\public_html\        ← Exists? Empty or populated?
```

### Linux
```
/var/www/capetennis/
├── app/                          ← Exists?
├── public/                        ← Exists?
│   ├── css/                       ← Exists?
│   ├── js/                        ← Exists?
│   └── images/                    ← Exists?
├── .env                           ← Exists? Contains DB info?
├── composer.json                  ← Exists?
└── deploy.sh                      ← Exists and updated?

/home/user/public_html/           ← Exists? Empty or populated?
```

## Database Verification

- [ ] Database exists and is accessible
- [ ] `.env` contains correct DB credentials:
  ```bash
  DB_HOST=localhost
  DB_PORT=3306
  DB_DATABASE=capetennis
  DB_USERNAME=xxxx
  DB_PASSWORD=xxxx
  ```
- [ ] Test connection from command line:
  ```bash
  php artisan tinker
  >>> DB::connection()->getPdo()
  # Should return PDO object, not error
  ```

## Git Repository Verification

- [ ] Git repository initialized: `git status` works
- [ ] Remote configured: `git remote -v` shows origin
- [ ] Current branch correct: `git branch`
  ```bash
  git branch
  # Should show * main (or your branch)
  ```
- [ ] Can pull from remote: `git fetch origin` works
- [ ] No uncommitted changes: `git status` shows clean

## Dependencies Verification

- [ ] Composer can run: `composer -V` works
- [ ] No missing packages: `composer validate` passes
- [ ] vendor/ folder exists: `composer install` has been run

## Permissions Verification (Linux Only)

```bash
# Check directory permissions
ls -la /var/www/capetennis/
# Should show drwxr-xr-x or similar

# Check storage permissions
ls -la /var/www/capetennis/storage/
# Should be writable by web server user

# Check bootstrap permissions
ls -la /var/www/capetennis/bootstrap/cache/
# Should be writable by web server user
```

- [ ] `/var/www/capetennis/` owned by correct user
- [ ] `storage/` folder is writable
- [ ] `bootstrap/cache/` folder is writable

## First Deploy Test (Dry Run)

### Windows
```powershell
# Test without actually making changes (review what would happen)
Write-Host "Script configuration:"
Write-Host "App Path: C:\wamp64\www\ct"
Write-Host "Public HTML: C:\wamp64\www\public_html"
Write-Host "Branch: main"

# Then run actual deploy
.\deploy.ps1

# Check the log
Get-Content logs/deploy_*.log | Select-Object -Last 20
```

### Linux
```bash
# Test script syntax
bash -n deploy.sh

# Check configuration
echo "App Path: /var/www/capetennis"
echo "Public HTML: /home/user/public_html"
echo "Branch: main"

# Run deploy
./deploy.sh production

# Check log
tail -f logs/deploy_*.log
```

- [ ] Deploy runs without fatal errors
- [ ] Log file is created
- [ ] No "not found" errors for key directories

## Post-Deploy Verification

After first successful deploy:

- [ ] Log file created: `logs/deploy_*.log`
- [ ] Assets synced to public_html:
  ```bash
  ls -la /home/user/public_html/css/
  ls -la /home/user/public_html/js/
  ```
- [ ] CSS files in web root have recent timestamp
- [ ] JS files in web root have recent timestamp
- [ ] Database migrations completed: `php artisan migrate:status`
- [ ] No errors in `storage/logs/laravel.log`

## Browser Testing

- [ ] Website loads without 404 errors
- [ ] CSS is applied (page looks formatted)
- [ ] JavaScript functions work (interact with page)
- [ ] Browser developer tools show no 404s for assets
- [ ] Clear cache and hard refresh still works

## Automated Deployments Setup

Only if doing scheduled deploys:

### Windows Task Scheduler
- [ ] Task created: `CapeTennisDeploy`
- [ ] Runs with elevated privileges
- [ ] Scheduled for off-peak hours (2 AM)
- [ ] Test manual run successful

### Linux Cron
- [ ] Added to crontab: `crontab -e`
- [ ] Correct syntax (0 2 * * *)
- [ ] Log file location writable
- [ ] Permissions allow execution

## Troubleshooting Prep

Before you need help:

- [ ] Know your web root path
- [ ] Know your database name and user
- [ ] Know which branch you deploy from
- [ ] Have hosting provider contact info
- [ ] Can SSH to server (production)
- [ ] Can access deployment logs

## Sign-Off

- [ ] All checklist items completed
- [ ] First manual deployment successful
- [ ] Team member briefed on process
- [ ] Rollback plan documented (if needed)
- [ ] Support email bookmarked: support@capetennis.co.za

---

**Next Step:** Run `.\deploy.ps1` (Windows) or `./deploy.sh production` (Linux)

**Issues?** Check deployment log first, then consult DEPLOY_SETUP.md or DEPLOYMENT.md
