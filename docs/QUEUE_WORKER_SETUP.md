# Bulk Email System - Queue Worker Setup

## Overview

The Cape Tennis application uses a throttled bulk email system to prevent overwhelming the mail server (Exim). Emails are queued and sent with delays to ensure no more than 10 messages are sent in one SMTP connection.

## Queue Configuration

The application is configured to use the **database** queue driver. Check `.env`:

```env
QUEUE_CONNECTION=database
```

### Bulk Mail Settings

Configure throttling via `.env`:

```env
BULK_MAIL_DELAY_SECONDS=10
BULK_MAIL_MAX_PER_MINUTE=10
BULK_MAIL_BATCH_THRESHOLD=10
BULK_MAIL_MAX_TRIES=3
```

- `BULK_MAIL_DELAY_SECONDS`: Delay between each email job dispatch (default: 10 seconds)
- `BULK_MAIL_MAX_PER_MINUTE`: Maximum emails sent per minute via runtime rate limiting (default: 10)
- `BULK_MAIL_BATCH_THRESHOLD`: Number of emails before throttling kicks in (default: 10)
- `BULK_MAIL_MAX_TRIES`: Maximum retry attempts for failed emails (default: 3)

## Production Queue Worker

### Required Setup

Production servers **MUST** run a queue worker to process bulk emails. Without a worker, emails will remain queued in the database and never be sent.

### Start Queue Worker

```bash
php artisan queue:work --tries=3 --timeout=120
```

### Recommended: Use Supervisor

For production, use Supervisor to keep the queue worker running automatically:

1. Install Supervisor:
   ```bash
   sudo apt-get install supervisor
   ```

2. Create config file `/etc/supervisor/conf.d/capetennis-worker.conf`:
   ```ini
   [program:capetennis-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/capetennis/artisan queue:work --tries=3 --timeout=120 --sleep=3 --max-jobs=1000
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/path/to/capetennis/storage/logs/worker.log
   stopwaitsecs=3600
   ```

3. Start Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start capetennis-worker:*
   ```

4. Check status:
   ```bash
   sudo supervisorctl status capetennis-worker:*
   ```

## How Throttling Works

The bulk email system uses **two layers of throttling** to prevent SMTP rate limit errors:

### Layer 1: Dispatch-Time Delays

When emails are queued, they are dispatched with progressive delays to spread them out over time.

### Layer 2: Runtime Rate Limiting

Queue jobs are rate-limited at execution time using Laravel's `RateLimited` middleware. This prevents multiple queue workers from sending emails too quickly, even if jobs become ready simultaneously.

### Event Announcement Example

When sending an announcement to 50 players:

1. **Collect recipients**: Gather all player emails (deduplicated)
2. **Create log records**: One `bulk_email_logs` entry per recipient with `status=queued`
3. **Dispatch jobs**: One `SendBulkEmailJob` per recipient with progressive delays:
   - Email 1: 0 seconds delay
   - Email 2: 10 seconds delay
   - Email 3: 20 seconds delay
   - Email 50: 490 seconds delay (~8 minutes)
4. **Process queue**: Worker picks up jobs and sends emails
5. **Runtime throttling**: Jobs are rate-limited to max 10 emails/minute (configurable via `BULK_MAIL_MAX_PER_MINUTE`)
6. **Update logs**: Each email's status is updated to `sent` or `failed` **only after successful SMTP delivery**

### Why Two Layers?

- **Dispatch delays** spread jobs over time to avoid queue bursts
- **Runtime rate limiting** protects against multiple workers or delayed jobs executing simultaneously
- Together, they ensure compliance with SMTP server rate limits

### Duplicate Prevention

The system prevents sending the same email twice:

- **Key**: `mail_type` + `related_type` + `related_id` + `recipient_email`
- **Result**: If announcement #123 was already sent to user@example.com, a second attempt will be skipped
- **Override**: Some features (like admin bulk email) allow duplicates via `allowDuplicates=true`

## Features Using Bulk Email System

### 1. Event Announcements
- Controller: `EventAnnouncementController`
- Mail type: `event_announcement`
- Sends to: All registered players in the event
- Duplicate prevention: Yes

### 2. Admin Bulk Email
- Controller: `EventEntryController::sendEmail()`
- Mail type: `bulk_event_mail`
- Sends to: Selected players/categories/events
- Duplicate prevention: No (allows resending)

### 3. Bank Refund Reminders
- Command: `php artisan refunds:send-bank-reminders`
- Mail type: `bank_refund_reminder`
- Sends to: Player + super-users
- Duplicate prevention: Yes

### 4. Disciplinary Alerts
- Command: `php artisan disciplinary:check-thresholds`
- Mail type: `suspension_alert` / `violation_notification`
- Sends to: Player + guardians + event admins + super-users
- Duplicate prevention: Yes

## Monitoring Bulk Emails

### Check Queue Status

```bash
# Count jobs in queue
php artisan queue:monitor

# Inspect jobs table
mysql -u root -p capetennis -e "SELECT queue, COUNT(*) as count FROM jobs GROUP BY queue;"
```

### Check Email Logs

```bash
# Recent queued emails
mysql -u root -p capetennis -e "SELECT mail_type, status, COUNT(*) as count FROM bulk_email_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY mail_type, status;"

# Failed emails
mysql -u root -p capetennis -e "SELECT * FROM bulk_email_logs WHERE status='failed' ORDER BY failed_at DESC LIMIT 10;"
```

### Resend Failed Emails

Use the `BulkMailDispatcher::resendFailed()` method:

```php
$dispatcher = app(\App\Services\BulkMailDispatcher::class);
$stats = $dispatcher->resendFailed('event_announcement', $announcement);
```

## Troubleshooting

### Emails Not Sending

1. **Check queue worker is running**:
   ```bash
   ps aux | grep "queue:work"
   ```

2. **Check jobs table**:
   ```bash
   SELECT COUNT(*) FROM jobs;
   ```

3. **Check failed_jobs table**:
   ```bash
   SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
   ```

4. **Check logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep BulkMail
   ```

### Queue Worker Died

Restart supervisor:
```bash
sudo supervisorctl restart capetennis-worker:*
```

### High Job Count

If jobs are building up:
```bash
# Clear all jobs (BE CAREFUL - this deletes pending emails!)
php artisan queue:clear

# Or restart worker to pick up jobs faster
sudo supervisorctl restart capetennis-worker:*
```

### Emails Stuck in "Queued" Status

Check if jobs are actually being processed:
```bash
# Watch the queue in real-time
watch -n 2 'mysql -u root -p capetennis -e "SELECT COUNT(*) as jobs FROM jobs; SELECT status, COUNT(*) as count FROM bulk_email_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) GROUP BY status;"'
```

## Testing Queue Locally

For local development:

```bash
# Run worker manually
php artisan queue:work --tries=3 --timeout=120

# Or use sync driver (no queue - immediate sending)
# In .env:
QUEUE_CONNECTION=sync
```

**WARNING**: Do NOT use `sync` driver in production for bulk emails! This defeats the throttling system.

## Performance Notes

- **Delay impact**: 50 emails with 10-second delay = ~8 minutes total time
- **Rate limit protection**: Runtime rate limiter ensures max 10 emails/minute regardless of queue worker count
- **Adjust delay**: For larger batches, you can reduce `BULK_MAIL_DELAY_SECONDS` if needed - the runtime rate limiter provides the final safety net
- **Multiple workers**: Safe to run 2-3 supervisor processes - runtime rate limiting prevents SMTP overload
- **Monitor Exim**: Check Exim logs for `no immediate delivery` or `Too many emails per second` warnings

## Critical: Mailables Must NOT Be Queueable

**IMPORTANT**: Mailable classes used within `SendBulkEmailJob` (like `AnnouncementMail`, `BulkEventMail`) must **NOT** implement `ShouldQueue`.

### Why?

When a mailable implements `ShouldQueue` and is passed to `Mail::send()`, Laravel creates a **nested queued job** (`SendQueuedMailable`). This:
- Bypasses the bulk email throttling system
- Creates double-queuing (job within a job)
- Causes burst sending and SMTP rate limit errors
- Prevents proper logging and retry handling

### Correct Pattern

```php
// ✅ CORRECT - Mailable does NOT implement ShouldQueue
class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;
    // NO implements ShouldQueue
}

// ✅ CORRECT - Job calls Mail::send() directly
class SendBulkEmailJob implements ShouldQueue
{
    public function handle()
    {
        Mail::send(new AnnouncementMail(...));
    }
}
```

### Wrong Pattern

```php
// ❌ WRONG - Mailable implements ShouldQueue
class AnnouncementMail extends Mailable implements ShouldQueue
{
    // This causes nested queued jobs!
}
```

Tests verify this constraint: see `tests/Unit/BulkMailableTest.php`

## Support

For issues with the bulk email system, check:
1. `storage/logs/laravel.log` - Application logs
2. `/var/log/exim4/mainlog` - Exim mail server logs
3. `bulk_email_logs` table - Email dispatch status
4. `failed_jobs` table - Failed queue jobs
