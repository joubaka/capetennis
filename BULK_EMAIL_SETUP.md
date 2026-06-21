# Bulk Email System - Setup Guide

## Overview

The Cape Tennis application has been updated to prevent Exim rate limiting errors when sending bulk emails. The system now uses a throttled queue-based approach that ensures emails are sent at a safe rate.

## Key Features

- **Throttled Sending**: Progressive delays between emails prevent exceeding Exim's 10-message-per-connection limit
- **Batch Management**: Automatically reconnects SMTP after every 8 emails (configurable)
- **Deduplication**: Prevents sending duplicate emails to the same recipient for the same campaign
- **Retry Logic**: Failed emails are automatically retried with exponential backoff
- **Comprehensive Logging**: All bulk emails are logged in the `bulk_email_logs` table

## Configuration

All bulk email settings are in `config/mail.php` under the `bulk_mail` section:

```php
'bulk_mail' => [
    // Delay in seconds between each SMTP batch reconnection
    'delay_seconds' => env('BULK_MAIL_DELAY_SECONDS', 2),

    // Maximum emails per minute (runtime rate limit)
    'max_per_minute' => env('BULK_MAIL_MAX_PER_MINUTE', 10),

    // SMTP connection batch size: max emails per connection before reconnect
    // Set to 8 to stay under Exim's 10-message-per-connection limit
    'batch_threshold' => env('BULK_MAIL_BATCH_THRESHOLD', 8),

    // Maximum retries for failed bulk emails
    'max_tries' => env('BULK_MAIL_MAX_TRIES', 3),

    // Backoff intervals in seconds for retries
    'backoff' => [60, 300, 900], // 1 min, 5 min, 15 min
],
```

### Environment Variables

Add these to your `.env` file:

```env
# Queue Configuration
QUEUE_CONNECTION=database

# Bulk Email Settings (optional - defaults shown)
BULK_MAIL_DELAY_SECONDS=2
BULK_MAIL_MAX_PER_MINUTE=10
BULK_MAIL_BATCH_THRESHOLD=8
BULK_MAIL_MAX_TRIES=3
```

## Queue Setup

### 1. Database Tables

The following tables are required (already migrated):
- `jobs` - Queue jobs
- `failed_jobs` - Failed job tracking
- `bulk_email_logs` - Bulk email audit trail

### 2. Start Queue Worker

**Development:**
```bash
php artisan queue:work --queue=default
```

**Production (recommended):**

Use a process manager like Supervisor to keep the queue worker running:

```ini
[program:cape-tennis-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/ct/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/ct/storage/logs/worker.log
stopwaitsecs=3600
```

### 3. Monitor Queue

```bash
# View queue status
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Retry specific failed job
php artisan queue:retry <job-id>

# Clear all jobs
php artisan queue:flush
```

## How It Works

### Email Sending Flow

1. **Collection Phase**: Email methods collect all recipient emails into an array
2. **Threshold Check**: If recipients >= 10, use BulkMailDispatcher; otherwise use direct queueing
3. **Dispatch Phase**: BulkMailDispatcher creates a log entry for each email and queues jobs with progressive delays
4. **Sending Phase**: SendBulkEmailJob processes each email with:
   - SMTP batch tracking (reconnects after 8 emails)
   - Rate limiting (max 10/minute)
   - Retry logic (3 attempts with backoff)
   - Status logging (queued → sent/failed)

### Supported Email Types

The system handles these bulk email scenarios:

- **Event Announcements** - Email all players in an event
- **Team Emails** - Email all players in a team
- **Region Emails** - Email all players in a region
- **Category Emails** - Email all players in a category
- **Series Emails** - Email all players across series events
- **Nomination Emails** - Email all nominated players
- **Unregistered Player Reminders** - Email unpaid players
- **Bank Refund Reminders** - Email players with pending refunds
- **Disciplinary Notifications** - Violations and suspensions

### Transactional Emails (Not Throttled)

These single-recipient emails are sent immediately without throttling:
- Registration confirmations
- Withdrawal notifications
- Refund confirmations
- Bank details requests
- Category moved notifications

## Monitoring Bulk Emails

### Check Bulk Email Logs

```php
// View recent bulk emails
use App\Models\BulkEmailLog;

// Queued emails
BulkEmailLog::queued()->count();

// Sent emails
BulkEmailLog::sent()->latest()->take(10)->get();

// Failed emails
BulkEmailLog::failed()->get();

// Skipped (duplicate) emails
BulkEmailLog::skipped()->get();
```

### Resend Failed Emails

```php
use App\Services\BulkMailDispatcher;

$dispatcher = app(BulkMailDispatcher::class);

// Resend all failed emails for a specific mail type
$stats = $dispatcher->resendFailed('event_announcement');

// Resend for specific related model
$announcement = Announcement::find(123);
$stats = $dispatcher->resendFailed('event_announcement', $announcement);
```

## Troubleshooting

### Queue Worker Not Processing Jobs

```bash
# Check if worker is running
ps aux | grep "queue:work"

# Restart worker
php artisan queue:restart

# Check job table
php artisan tinker
>>> DB::table('jobs')->count()
```

### Emails Not Sending

1. **Check queue connection**: `echo env('QUEUE_CONNECTION')` should be `database`
2. **Check worker is running**: See above
3. **Check failed jobs**: `php artisan queue:failed`
4. **Check logs**: `tail -f storage/logs/laravel.log`
5. **Check SMTP settings**: Verify mail configuration in `.env`

### Rate Limiting Errors

If you still see "more than 10 messages in one connection" errors:

1. **Reduce batch_threshold**: Set `BULK_MAIL_BATCH_THRESHOLD=5` in `.env`
2. **Increase delay**: Set `BULK_MAIL_DELAY_SECONDS=5` in `.env`
3. **Check worker count**: Multiple workers may compound the issue

### High Memory Usage

```bash
# Limit worker memory
php artisan queue:work --memory=128

# Restart worker after N jobs
php artisan queue:work --max-jobs=1000
```

## Testing

Run the bulk email tests:

```bash
# Unit tests (core functionality)
php artisan test --filter BulkMailDispatcherTest

# All bulk email tests
php artisan test tests/Unit/BulkMailDispatcherTest.php
```

## Migration from Old System

The old system queued emails but didn't add delays, causing all jobs to execute in rapid succession on the same SMTP connection. The new system:

1. ✅ Adds progressive delays between jobs (default: 2 seconds)
2. ✅ Reconnects SMTP after every 8 emails (under Exim's 10-message limit)
3. ✅ Logs all bulk emails for audit trail and deduplication
4. ✅ Supports retry with exponential backoff
5. ✅ Rate limits at runtime (max 10 emails/minute)

**No breaking changes** - all existing email functionality remains the same. Small batches (< 10 recipients) continue to work as before.

## Support

For issues or questions:
- Email: support@capetennis.co.za
- Check logs: `storage/logs/laravel.log`
- Monitor bulk emails: Review `bulk_email_logs` table
