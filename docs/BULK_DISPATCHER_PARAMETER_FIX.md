# BulkMailDispatcher Parameter Fix

## Problem

Email sending failed with error:
```
Unknown named parameter $relatedType
```

**Location**: `app/Http/Controllers/Backend/EmailController.php:505`

## Root Cause

The `BulkMailDispatcher::dispatch()` method was called with incorrect parameter names.

### Actual Method Signature

```php
public function dispatch(
    string $mailType,
    $related = null,           // ✅ Expects model instance
    $recipients = [],
    array $payload = [],
    bool $allowDuplicates = false
)
```

### Incorrect Call (What I Did Wrong)

```php
app(BulkMailDispatcher::class)->dispatch(
    recipients: $recipients,
    mailType: 'region_email',
    relatedType: TeamRegion::class,  // ❌ Wrong parameter name
    relatedId: $region->id,          // ❌ Wrong parameter name
    payload: [...]
);
```

The method expects a single `$related` parameter (the model instance), not separate `relatedType` and `relatedId` parameters.

## Solution

Updated both `sendToTeam()` and `sendToRegion()` calls to use correct parameters:

### Fixed Call

```php
app(BulkMailDispatcher::class)->dispatch(
    mailType: 'region_email',
    related: $region,              // ✅ Pass the model instance
    recipients: $recipients,
    payload: [
        'subject' => $details['subject'],
        'message' => $details['message'],
        'from_name' => $details['fromName'],
        'reply_to' => $details['replyTo'],
    ]
);
```

## Changes Made

### 1. Team Email Call
**File**: `app/Http/Controllers/Backend/EmailController.php` (~line 410)

**Before**:
```php
app(BulkMailDispatcher::class)->dispatch(
    recipients: $recipients,
    mailType: 'team_email',
    relatedType: Team::class,    // ❌ Wrong
    relatedId: $team->id,        // ❌ Wrong
    payload: [...]
);
```

**After**:
```php
app(BulkMailDispatcher::class)->dispatch(
    mailType: 'team_email',
    related: $team,              // ✅ Correct
    recipients: $recipients,
    payload: [...]
);
```

### 2. Region Email Call
**File**: `app/Http/Controllers/Backend/EmailController.php` (~line 502)

Same fix applied - pass `$region` instance instead of class name and ID.

## Why The Original Design

The `BulkMailDispatcher` extracts type and ID internally:

```php
// Inside BulkMailDispatcher
'related_type' => $related ? get_class($related) : null,
'related_id' => $related?->id ?? null,
```

This design is better because:
- ✅ Single parameter instead of two
- ✅ Type-safe (ensures model instance)
- ✅ Handles null gracefully
- ✅ Automatic type extraction

## Testing

### Before Fix
```
❌ 500 Error: Unknown named parameter $relatedType
```

### After Fix
```
✅ Email dispatches successfully
✅ BulkEmailLog entries created
✅ Jobs queued with delays
```

## Parameter Order

For clarity, the recommended parameter order when calling `dispatch()`:

```php
app(BulkMailDispatcher::class)->dispatch(
    mailType: 'team_email',        // 1. Mail type
    related: $team,                // 2. Related model (optional)
    recipients: $recipients,       // 3. Recipients array
    payload: [...],                // 4. Email data
    allowDuplicates: false         // 5. Duplicate flag (optional)
);
```

## Files Modified

✅ `app/Http/Controllers/Backend/EmailController.php`
- Fixed `sendToTeam()` BulkMailDispatcher call
- Fixed `sendToRegion()` BulkMailDispatcher call

## Related Documentation

- `app/Services/BulkMailDispatcher.php` - Service definition
- `docs/EMAIL_CONTROLLER_BULK_FIX.md` - Bulk email integration
- `docs/QUEUE_WORKER_SETUP.md` - Queue worker setup

---

**Date**: 2024-06-18  
**Error**: `Unknown named parameter $relatedType`  
**Status**: ✅ Fixed  
**Impact**: Critical - Team and region emails were failing
