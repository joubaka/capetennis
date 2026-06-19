# SMTP "More than 10 messages in one connection" - Still Happening

## Error Message
```
no immediate delivery: more than 10 messages received in one connection
```

## Root Cause

The bulk email system is working correctly (creating delayed jobs), but the **queue worker** is processing jobs **too fast** or running **multiple workers in parallel**.

### What's Happening

1. ✅ `BulkMailDispatcher` creates jobs with progressive delays (0s, 6s, 12s, 18s...)
2. ✅ `SendBulkEmailJob` has rate limiting middleware (`RateLimited('bulk-email')`)
3. ✅ `AppServiceProvider` configures rate limit (10 emails/minute)
4. ❌ **Queue worker is ignoring delays or running too many concurrent processes**

## The Problem: Queue Worker Configuration

### Issue 1: Queue Worker Not Respecting Delays

If you're running:
```bash
php artisan queue:work
```

The worker will **immediately process** all available jobs, ignoring the delay we set.

### Issue 2: Multiple Queue Workers Running

If multiple queue workers are running in parallel, they will each process jobs simultaneously, overwhelming SMTP.

## Solution: Proper Queue Worker Configuration

### Step 1: Stop All Existing Queue Workers

```bash
# Find all running queue workers
ps aux | grep "queue:work"

# Kill them (replace PID with actual process IDs)
kill -9 <PID>

# Or use artisan
php artisan queue:restart
```

### Step 2: Configure Queue Worker Correctly

You need to run the queue worker with **proper concurrency limits**:

#### Option A: Single Worker (Recommended for Testing)

```bash
php artisan queue:work \
  --sleep=3 \
  --tries=3 \
  --timeout=90 \
  --max-jobs=100 \
  --max-time=3600
```

**Explanation**:
- `--sleep=3` - Wait 3 seconds between jobs when queue is empty
- `--tries=3` - Retry failed jobs 3 times
- `--timeout=90` - Job timeout (prevent hanging)
- `--max-jobs=100` - Restart worker after 100 jobs (prevents memory leaks)
- `--max-time=3600` - Restart worker after 1 hour

#### Option B: Supervisor (Recommended for Production)

Create `/etc/supervisor/conf.d/capetennis-worker.conf`:

```ini
[program:capetennis-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/batawrry/domains/capetennis.co.za/laraval_app/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=batawrry
numprocs=1
redirect_stderr=true
stdout_logfile=/home/batawrry/domains/capetennis.co.za/laraval_app/storage/logs/worker.log
stopwaitsecs=3600
```

**CRITICAL**: `numprocs=1` - Only ONE worker! Do NOT increase this!

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start capetennis-worker:*
```

### Step 3: Verify Rate Limiting is Working

After restarting queue worker:

```bash
# Watch the logs
tail -f storage/logs/laravel.log | grep SendBulkEmailJob

# You should see entries spaced out by ~6 seconds
```

### Step 4: Check Queue Delay Configuration

Ensure your production server has the delay configuration:

**Check `.env` on production**:
```env
BULK_MAIL_DELAY_SECONDS=6
BULK_MAIL_MAX_PER_MINUTE=10
```

**Or verify config**:
```bash
php artisan config:clear
php artisan tinker
>>> config('mail.bulk_mail.delay_seconds')
=> 6
>>> config('mail.bulk_mail.max_per_minute')
=> 10
```

## Alternative: Use Database Queue with Better Delay Support

If the rate limiter isn't working well, switch to a more explicit delay:

### Update Queue Configuration

**File**: `config/queue.php`

Find the `database` queue connection and ensure it has:

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
    'after_commit' => false,
],
```

Then verify your `.env`:
```env
QUEUE_CONNECTION=database
```

### Why This Matters

Database queue respects job delays better than sync/redis queues. The delay we set in `BulkMailDispatcher` should be honored:

```php
// Each job gets a progressively larger delay
SendBulkEmailJob::dispatch($log->id)
    ->delay(now()->addSeconds($delaySeconds));
```

## Debugging: Check What's in the Queue

```bash
php artisan tinker
```

```php
// Check pending jobs
>>> DB::table('jobs')->count()
=> 50  // example

// Check delays
>>> DB::table('jobs')->select('payload', 'available_at')->get()

// Check bulk email logs
>>> \App\Models\BulkEmailLog::where('status', 'queued')->count()
>>> \App\Models\BulkEmailLog::where('status', 'sent')->count()
```

## The Real Fix: Enforce Delays at SMTP Level

If queue workers still ignore delays, we need to add a **per-connection delay** directly in the send logic.

### Update SendBulkEmailJob

Add a sleep after sending:

**File**: `app/Jobs/SendBulkEmailJob.php`

In the `handle()` method, after sending the email:

```php
// Send the email
Mail::mailer($mailer)->to($log->recipient_email, $log->recipient_name)->send($mailable);

// ✅ FORCE delay after each send (prevents SMTP connection reuse)
sleep(config('mail.bulk_mail.delay_seconds', 6));

// Mark as sent
$log->update([
    'status' => 'sent',
    'sent_at' => now(),
]);
```

**This guarantees** at least 6 seconds between emails from the same worker process.

## Testing

After implementing the fix:

1. Send email to a region/team with 15+ players
2. Monitor the queue:
   ```bash
   watch -n 1 'php artisan queue:monitor database'
   ```
3. Check SMTP logs on the server
4. Verify bulk_email_logs table shows staggered `sent_at` times

## Summary

The issue is **NOT** with the bulk email code logic - it's creating delayed jobs correctly. The issue is the **queue worker configuration** is processing jobs too fast.

**Immediate Actions**:
1. ⬜ Restart queue worker (ensure only ONE worker running)
2. ⬜ Verify `.env` has `BULK_MAIL_DELAY_SECONDS=6`
3. ⬜ Add forced `sleep()` in `SendBulkEmailJob` as fallback
4. ⬜ Monitor queue and SMTP logs

---

**Status**: ⬜ Fixed ⬜ Still Testing  
**Queue Worker Config**: ⬜ Single ⬜ Multiple (fix this!)  
**Rate Limiter Working**: ⬜ Yes ⬜ No  
