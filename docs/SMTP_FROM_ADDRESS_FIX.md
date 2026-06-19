# SMTP "From Address" Rejection Fix

## Problem

Emails were failing with SMTP error:
```
550-Account noreply@capetennis.co.za can not send emails from
550 capetennis@capetennis.co.za.
```

This happened when:
- Email was sent through `noreply@capetennis.co.za` SMTP account
- But the "From" header was set to `capetennis@capetennis.co.za`
- SMTP servers reject this as spoofing/impersonation

## Root Cause

The `SendEmailTest` mailable had hardcoded FROM address:

**File**: `app/Mail/SendEmailTest.php` (line 33)

```php
// ❌ WRONG - Hardcoded address doesn't match SMTP account
$fromEmail = 'capetennis@capetennis.co.za';

return new Envelope(
  from: new Address($fromEmail, $fromName),
  ...
);
```

### Why This Failed

When using multiple SMTP accounts (noreply@, noreply1@, noreply2@):
- `MailAccountManager` picks an available account
- Job dispatches with correct mailer name
- But mailable always used `capetennis@capetennis.co.za`
- SMTP server rejects: "noreply@ can't send from capetennis@"

## Solution

Updated `SendEmailTest` to use the FROM address from the data passed by the controller:

### Before Fix
```php
// ❌ Hardcoded - always capetennis@capetennis.co.za
$fromEmail = 'capetennis@capetennis.co.za';
$fromName = $this->data['fromName'] ?? 'Cape Tennis';
```

### After Fix
```php
// ✅ Dynamic - uses the mailer's own email address
$fromEmail = $this->data['fromEmail'] ?? config('mail.from.address', 'noreply@capetennis.co.za');
$fromName = $this->data['fromName'] ?? config('mail.from.name', 'Cape Tennis');
```

## How It Works Now

### EmailController Flow

1. **Pick mailer** via `MailAccountManager`:
```php
$mailer = app(MailAccountManager::class)->getMailer();
// Example result: 'noreply1'
```

2. **Set FROM email to match mailer**:
```php
$details['fromEmail'] = match ($mailer) {
    'noreply1' => 'noreply1@capetennis.co.za',
    'noreply2' => 'noreply2@capetennis.co.za',
    default => 'noreply@capetennis.co.za',
};
```

3. **Pass to mailable**:
```php
Mail::mailer($mailer)
    ->to($recipient)
    ->send(new SendEmailTest($details));
```

4. **Mailable uses correct FROM**:
```php
// Extracts fromEmail from $details
from: new Address($fromEmail, $fromName)
```

### Result

| Mailer | SMTP Account | FROM Address | Status |
|--------|-------------|--------------|--------|
| noreply | noreply@capetennis.co.za | noreply@capetennis.co.za | ✅ Match |
| noreply1 | noreply1@capetennis.co.za | noreply1@capetennis.co.za | ✅ Match |
| noreply2 | noreply2@capetennis.co.za | noreply2@capetennis.co.za | ✅ Match |

SMTP servers accept emails because FROM address matches the authenticated SMTP account.

## Why Reply-To Still Works

Users can still reply to the original sender:

```php
'replyTo' => filter_var($request->replyTo, FILTER_VALIDATE_EMAIL)
    ? $request->replyTo
    : (auth()->user()->email ?? 'info@capetennis.co.za'),
```

**Example**:
- FROM: `noreply1@capetennis.co.za` (SMTP account - must match)
- REPLY-TO: `admin@capetennis.co.za` (user who sent the email - can be different)
- When recipient clicks "Reply", email goes to `admin@capetennis.co.za`

## Email Header Example

### Before Fix (FAILED)
```
From: capetennis@capetennis.co.za
SMTP-Auth-User: noreply@capetennis.co.za
❌ 550 Account noreply@ can not send from capetennis@
```

### After Fix (SUCCESS)
```
From: noreply@capetennis.co.za
Reply-To: admin@capetennis.co.za
SMTP-Auth-User: noreply@capetennis.co.za
✅ 250 OK - Message accepted
```

## Testing

### Clear Failed Jobs
```bash
# Remove old failed jobs with wrong FROM address
php artisan queue:clear
php artisan queue:failed  # Check failed_jobs table
php artisan queue:flush    # Clear failed_jobs if needed
```

### Test Email Sending
1. Navigate to team/region page
2. Click "Send Email"
3. Fill in form and send

**Expected**:
- ✅ Email sends successfully
- ✅ No SMTP 550 errors
- ✅ FROM matches SMTP account
- ✅ REPLY-TO set to sender

### Verify Logs
```bash
tail -f storage/logs/laravel.log | grep SendEmailJob
```

Should see:
```
[SendEmailJob] ✅ SENT SUCCESS
```

Instead of:
```
[SendEmailJob] ❌ SEND FAILED: 550 Account can not send emails from
```

## Configuration

SMTP accounts configured in `.env`:

```env
# Default mailer
MAIL_FROM_ADDRESS=noreply@capetennis.co.za
MAIL_FROM_NAME="Cape Tennis"

# Backup accounts for load balancing
MAIL_NOREPLY1_USERNAME=noreply1@capetennis.co.za
MAIL_NOREPLY2_USERNAME=noreply2@capetennis.co.za
```

Each account can only send with its own email as FROM address.

## Files Modified

✅ `app/Mail/SendEmailTest.php`
- Removed hardcoded `capetennis@capetennis.co.za`
- Now uses `$this->data['fromEmail']` from controller
- Falls back to `config('mail.from.address')` if not provided

## Related Systems

This fix works with:
- ✅ `MailAccountManager` - Automatic mailer selection
- ✅ `EmailController` - Sets correct FROM per mailer
- ✅ `SendEmailJob` - Queued email sending
- ✅ Multi-account load balancing

## Security Note

**FROM address** = Technical sender (must match SMTP account)  
**REPLY-TO address** = Where replies go (can be different)

This separation allows:
- Load balancing across multiple SMTP accounts (FROM)
- Replies still reach the original sender (REPLY-TO)
- No SMTP spoofing/rejection errors

---

**Date**: 2024-06-18  
**Error**: `550 Account noreply@ can not send emails from capetennis@`  
**Status**: ✅ Fixed  
**Impact**: Critical - All queued emails were failing
