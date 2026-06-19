# Production Deployment: Bulk Email System

## ⚠️ CRITICAL: Database Migration Required

The production server is missing the `bulk_email_logs` table, which is causing a 500 error:

```
SQLSTATE[42S02]: Base table or view not found: 1146 
Table 'batawrry_capetennis.bulk_email_logs' doesn't exist
```

## Prerequisites

Before deploying, ensure you have:
1. ✅ SSH access to your production server
2. ✅ Database backup (always backup before running migrations!)
3. ✅ Access to run artisan commands on production

## Deployment Steps

### Step 1: Upload/Pull Latest Code

**Option A - Git Pull** (Recommended):
```bash
cd /home/batawrry/domains/capetennis.co.za/laraval_app
git pull origin main
```

**Option B - Manual Upload**:
Upload these files to production:
- `database/migrations/2026_06_18_112113_create_bulk_email_logs_table.php`
- `app/Services/BulkMailDispatcher.php`
- `app/Jobs/SendBulkEmailJob.php`
- `app/Models/BulkEmailLog.php`
- `app/Http/Controllers/Backend/EmailController.php`
- `app/Mail/SendEmailTest.php`
- `app/Mail/BulkEventMail.php`
- `app/Mail/AnnouncementMail.php`
- `app/Providers/AppServiceProvider.php`
- `config/mail.php`

### Step 2: Run Database Migration

**⚠️ BACKUP YOUR DATABASE FIRST!**

```bash
cd /home/batawrry/domains/capetennis.co.za/laraval_app

# Run the migration
php artisan migrate

# You should see:
# Migrating: 2026_06_18_112113_create_bulk_email_logs_table
# Migrated:  2026_06_18_112113_create_bulk_email_logs_table (XX.XXms)
```

### Step 3: Clear All Caches

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Step 4: Verify Queue Worker is Running

The bulk email system requires a queue worker to process jobs.

**Check if queue worker is running**:
```bash
ps aux | grep "queue:work"
```

**If NOT running, start it**:
```bash
# Option A: Run in background with supervisor (recommended)
# See docs/QUEUE_WORKER_SETUP.md

# Option B: Quick test (not for production use)
php artisan queue:work --tries=3 --timeout=90 &
```

### Step 5: Test Email Sending

1. Log into admin panel
2. Go to a team or region
3. Send an email to ≥10 players
4. Check logs:

```bash
# Check Laravel logs
tail -f /home/batawrry/domains/capetennis.co.za/laraval_app/storage/logs/laravel.log

# Check for bulk email log entries
php artisan tinker
>>> \App\Models\BulkEmailLog::latest()->take(5)->get()
```

## What Changed

### New Database Table
- `bulk_email_logs` - Tracks all bulk email sends with status, deduplication, and retry support

### New Files
- `app/Services/BulkMailDispatcher.php` - Centralized bulk email dispatcher
- `app/Jobs/SendBulkEmailJob.php` - Queue job for individual email sends
- `app/Models/BulkEmailLog.php` - Eloquent model for logs
- Migration file (see above)

### Modified Files
- `app/Http/Controllers/Backend/EmailController.php`
  - Added `MailAccountManager` import
  - Added `BulkMailDispatcher` import
  - Changed team/region sends to use bulk dispatcher for ≥10 recipients

- `app/Mail/SendEmailTest.php`
  - Fixed FROM address spoofing (now uses SMTP account email)

- `app/Mail/BulkEventMail.php`
  - Removed `ShouldQueue` interface (prevents nested queue jobs)

- `app/Mail/AnnouncementMail.php`
  - Removed `ShouldQueue` interface

- `app/Providers/AppServiceProvider.php`
  - Added bulk-email rate limiter (10 emails/minute by default)

- `config/mail.php`
  - Added bulk_mail configuration section

## Configuration

After deployment, you can tune bulk email settings in `.env`:

```env
# Bulk Email Configuration
BULK_MAIL_DELAY_SECONDS=6        # Delay between emails (default: 6s)
BULK_MAIL_MAX_PER_MINUTE=10      # Max emails per minute (default: 10)
BULK_MAIL_BATCH_THRESHOLD=10     # Min recipients to use bulk system (default: 10)
BULK_MAIL_MAX_TRIES=3            # Max retry attempts (default: 3)
```

Then clear config cache:
```bash
php artisan config:clear
```

## Rollback Plan

If something goes wrong:

### Rollback Migration
```bash
php artisan migrate:rollback --step=1
```

### Revert Code
```bash
git revert HEAD
# or
git reset --hard <previous-commit-hash>
```

## Troubleshooting

### Error: "Table bulk_email_logs doesn't exist"
**Solution**: Run the migration (Step 2)

### Error: "Target class [MailAccountManager] does not exist"
**Solution**: Clear caches (Step 3) and ensure `EmailController.php` has the correct import

### Emails not sending
**Solution**: 
1. Check queue worker is running (Step 4)
2. Check failed jobs: `php artisan queue:failed`
3. Check logs: `tail -f storage/logs/laravel.log`

### Duplicate emails being sent
**Solution**: Check `bulk_email_logs` table for duplicate entries - the system should auto-dedupe

## Support

For issues, check:
- `docs/EMAIL_CONTROLLER_BULK_FIX.md` - Detailed explanation
- `docs/BULK_DISPATCHER_PARAMETER_FIX.md` - Parameter fix details
- `docs/QUEUE_WORKER_SETUP.md` - Queue setup guide

---

**Deployment Date**: _________________  
**Deployed By**: _________________  
**Migration Status**: ⬜ Success ⬜ Failed  
**Queue Worker Status**: ⬜ Running ⬜ Not Running  
**Test Email Status**: ⬜ Success ⬜ Failed  

---

## Quick Reference

```bash
# Complete deployment in one go:
cd /home/batawrry/domains/capetennis.co.za/laraval_app
git pull origin main
php artisan migrate
php artisan optimize:clear
php artisan queue:restart  # If queue worker is already running
```
