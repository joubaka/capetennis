# Email Controller Bulk Email Integration Fix

## Problem

The `EmailController` was **NOT** using the `BulkMailDispatcher` system when sending emails to teams or regions. This caused the **Exim 10-email per connection limit** problem we solved earlier.

### What Was Happening

When sending emails to a team or region:
1. Loop through all players
2. Call `queueMail()` for each player (line by line)
3. Each call dispatches a `SendEmailJob` immediately
4. All jobs execute at once (no delays)
5. 💥 Exim rejects: "no immediate delivery: more than 10 messages received in one connection"

### Example Issue

- Team has 15 players
- Admin sends email to team
- 15 `SendEmailJob` dispatched instantly (no delays)
- Queue worker processes them quickly
- Exim sees 15 emails in rapid succession → Rate limit error

## Solution

Updated `EmailController` to use `BulkMailDispatcher` for teams and regions with 10+ recipients.

### Changes Made

#### 1. Added BulkMailDispatcher Import

**File**: `app/Http/Controllers/Backend/EmailController.php`

```php
use App\Services\BulkMailDispatcher;
```

#### 2. Updated `sendToTeam()` Method

**Before**:
```php
foreach ($team->players as $player) {
  if (!empty($player->email)) {
    $details['email'] = trim(strtolower($player->email));
    $this->queueMail($details, $mailer);  // ❌ No delay, direct queue
  }
}
```

**After**:
```php
// Collect all emails
$recipients = [];
foreach ($team->players as $player) {
  if (!empty($player->email)) {
    $recipients[] = trim(strtolower($player->email));
  }
}

// Use BulkMailDispatcher for 10+ recipients
if (count($recipients) >= config('mail.bulk_mail.batch_threshold', 10)) {
  app(BulkMailDispatcher::class)->dispatch(
    recipients: $recipients,
    mailType: 'team_email',
    relatedType: Team::class,
    relatedId: $team->id,
    payload: [
      'subject' => $details['subject'],
      'message' => $details['message'],
      'from_name' => $details['fromName'],
      'reply_to' => $details['replyTo'],
    ]
  );
} else {
  // Small teams still use direct queueing
  foreach ($recipients as $email) {
    $details['email'] = $email;
    $this->queueMail($details, $mailer);
  }
}
```

#### 3. Updated `sendToRegion()` Method

Same pattern as `sendToTeam()`:
- Collect all player emails from all teams in region
- Use `BulkMailDispatcher` if 10+ recipients
- Mail type: `region_email`

#### 4. Added Mail Type Support

**File**: `app/Jobs/SendBulkEmailJob.php`

Added `team_email` and `region_email` to the switch statement:

```php
case 'bulk_event_mail':
case 'team_email':      // ✅ NEW
case 'region_email':    // ✅ NEW
    return (new \App\Mail\BulkEventMail(
        $payload['subject'] ?? 'Event Update',
        $payload['body'] ?? $payload['message'] ?? '',
        $payload['from_name'] ?? 'Cape Tennis',
        $payload['reply_to'] ?? 'info@capetennis.co.za'
    ));
```

## How It Works Now

### Team Email (15 players example)

**Before Fix**:
- ❌ 15 jobs dispatched instantly
- ❌ All execute within seconds
- ❌ Exim sees burst → rate limit error

**After Fix**:
- ✅ 15 jobs dispatched with 10-second delays (0s, 10s, 20s, ..., 140s)
- ✅ Runtime rate limiting: max 10/minute
- ✅ Spread over ~2.5 minutes
- ✅ No Exim errors

### Region Email (50 players example)

**Before Fix**:
- ❌ 50 jobs dispatched instantly
- ❌ All execute rapidly
- ❌ Massive Exim rate limit errors

**After Fix**:
- ✅ 50 jobs with 10-second delays (0s, 10s, 20s, ..., 490s)
- ✅ Runtime rate limiting enforced
- ✅ Spread over ~8 minutes
- ✅ Safe, controlled delivery

## Threshold Behavior

**Configured in**: `config/mail.php`

```php
'bulk_mail' => [
    'batch_threshold' => 10,  // 10+ recipients triggers BulkMailDispatcher
    'delay_seconds' => 10,    // 10-second delay between dispatches
    'max_per_minute' => 10,   // Runtime rate limit
]
```

### Small Recipients (< 10)
- Uses direct `queueMail()` (faster, no delays needed)
- Example: Team with 5 players

### Large Recipients (≥ 10)
- Uses `BulkMailDispatcher` (throttled, safe)
- Example: Team with 15 players, Region with 50 players

## Affected Features

✅ **Team Emails** - Now throttled  
✅ **Region Emails** - Now throttled  
✅ **Event Announcements** - Already throttled (from previous fix)  
✅ **Bulk Admin Emails** - Already throttled (from previous fix)  
✅ **Bank Refund Reminders** - Already throttled (from previous fix)  
✅ **Disciplinary Alerts** - Already throttled (from previous fix)

## Testing

### Test Team Email (15+ players)

1. Navigate to team page with 15+ players
2. Click "Send Email to Team"
3. Fill in subject/message
4. Click Send

**Expected**:
- ✅ Success message
- ✅ Jobs queued with delays in `bulk_email_logs`
- ✅ Emails send at ~10/minute rate
- ✅ No Exim errors in logs

### Test Region Email (50+ players)

1. Navigate to region page
2. Click "Send Email to Region"
3. Fill in email form
4. Click Send

**Expected**:
- ✅ Success message
- ✅ Jobs spread over ~8 minutes
- ✅ Runtime rate limiting enforced
- ✅ No SMTP errors

## Monitoring

```sql
-- Check recent team/region emails
SELECT mail_type, status, COUNT(*) as count
FROM bulk_email_logs
WHERE mail_type IN ('team_email', 'region_email')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY mail_type, status;

-- Check send rate (should be ~10/minute)
SELECT 
    DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i') as minute,
    COUNT(*) as emails_sent
FROM bulk_email_logs
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i')
ORDER BY minute DESC;
```

## Files Modified

✅ `app/Http/Controllers/Backend/EmailController.php`
- Added `BulkMailDispatcher` import
- Updated `sendToTeam()` method
- Updated `sendToRegion()` method

✅ `app/Jobs/SendBulkEmailJob.php`
- Added `team_email` and `region_email` mail type support

## Benefits

1. **Prevents Exim Rate Limiting** - No more "10 messages per connection" errors
2. **Consistent Throttling** - All bulk email features now use the same system
3. **Logging & Tracking** - All team/region emails logged in `bulk_email_logs`
4. **Retry Support** - Failed emails automatically retry with backoff
5. **Duplicate Prevention** - Built-in dedupe checking

## Configuration

Control throttling via `.env`:

```env
BULK_MAIL_DELAY_SECONDS=10      # Delay between job dispatches
BULK_MAIL_MAX_PER_MINUTE=10     # Runtime rate limit
BULK_MAIL_BATCH_THRESHOLD=10    # Trigger threshold for bulk dispatch
BULK_MAIL_MAX_TRIES=3           # Retry attempts for failed emails
```

---

**Date**: 2024-06-18  
**Issue**: Team/Region emails not using bulk throttling  
**Status**: ✅ Fixed  
**Impact**: High - Prevents SMTP rate limit errors on team/region emails
