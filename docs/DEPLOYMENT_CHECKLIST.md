# Bulk Email Fix - Deployment Checklist

## Quick Deployment Steps

### 1. Pull Latest Code
```bash
cd /path/to/capetennis
git pull origin main
```

### 2. Update Environment
Add to `.env`:
```env
BULK_MAIL_MAX_PER_MINUTE=10
```

### 3. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
```

### 4. Clear Old Queue Jobs (Optional)
⚠️ **WARNING**: This will delete any pending emails in the queue
```bash
php artisan queue:clear
```

### 5. Restart Queue Workers
```bash
# If using Supervisor
sudo supervisorctl restart capetennis-worker:*

# Or kill and restart manually
pkill -f "queue:work"
php artisan queue:work --tries=3 --timeout=120 --daemon
```

### 6. Verify Deployment
```bash
# Check config is loaded
php artisan tinker
>>> config('mail.bulk_mail.max_per_minute')
=> 10

# Check queue worker is running
ps aux | grep queue:work

# Monitor email sending
tail -f storage/logs/laravel.log | grep SendBulkEmailJob
```

## Test Sending Emails

### Send Test Announcement
1. Log into admin panel
2. Navigate to an event
3. Go to Announcements
4. Create and send an announcement to a small group (2-3 people)
5. Monitor logs:
   ```bash
   tail -f storage/logs/laravel.log | grep -E "SendBulkEmailJob|BulkMailDispatcher"
   ```

### Expected Behavior
- Jobs dispatched with 10-second delays
- Runtime rate limiting enforces max 10 emails/minute
- No `SendQueuedMailable` in logs
- Status updates from `queued` → `sent` in `bulk_email_logs` table

## Rollback Plan

If issues occur:

### Immediate Rollback
```bash
git revert HEAD
php artisan config:clear
sudo supervisorctl restart capetennis-worker:*
```

### Check Old Queue Jobs
```bash
# See what's in the queue
php artisan tinker
>>> DB::table('jobs')->count()
>>> DB::table('jobs')->first()
```

## Monitoring After Deployment

### First 24 Hours
Monitor for:
- SMTP rate limit errors in Exim logs: `/var/log/exim4/mainlog`
- Application errors: `storage/logs/laravel.log`
- Failed jobs: Check `failed_jobs` table

### SQL Monitoring Queries

**Email send rate**:
```sql
SELECT 
    DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i') as minute,
    COUNT(*) as emails_sent
FROM bulk_email_logs 
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i')
ORDER BY minute DESC;
```

**Failed emails**:
```sql
SELECT mail_type, COUNT(*) as failed_count
FROM bulk_email_logs
WHERE status = 'failed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY mail_type;
```

**Queue backlog**:
```sql
SELECT COUNT(*) as pending_jobs FROM jobs;
SELECT COUNT(*) as queued_emails FROM bulk_email_logs WHERE status = 'queued';
```

## Success Criteria

✅ No SMTP rate limit errors in Exim logs  
✅ Emails send at ~10 per minute (or configured rate)  
✅ No `SendQueuedMailable` jobs created  
✅ All tests passing  
✅ Queue workers stable and processing jobs  

## Support Contacts

- Email system issues: support@capetennis.co.za
- Deployment issues: Check GitHub issues or contact dev team

---

**Last Updated**: 2024-06-18  
**Related Docs**: 
- `docs/QUEUE_WORKER_SETUP.md` - Full queue worker documentation
- `docs/BULK_EMAIL_FIX_SUMMARY.md` - Technical fix details
