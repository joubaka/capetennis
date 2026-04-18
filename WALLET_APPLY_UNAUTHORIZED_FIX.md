# Wallet Apply Unauthorized Error - Troubleshooting Guide

## Problem
When clicking "Apply Wallet Balance" on the checkout page, you get an "Unauthorized" error (403 response).

```
www.capetennis.co.za says
Unauthorized.
```

## Root Cause
The issue occurs when:
1. **User session expires** between page load and AJAX request (common on slow connections or shared hosting)
2. **Session cookies not sent** with the AJAX request 
3. **Session table not synchronized** across server instances (on load-balanced hosting)
4. **CSRF token mismatch** between the page and the request

## Fixes Applied (v1.0.1)

### 1. **Improved AJAX Request (Frontend)**
Added `xhrFields: { withCredentials: true }` to ensure session cookies are sent:

```javascript
$.ajax({
  url: APP_URL + '/registration/hybrid/apply-wallet',
  type: 'POST',
  xhrFields: {
    withCredentials: true  // 🔐 Ensures session cookies sent with request
  },
  data: {
    _token: $('meta[name="csrf-token"]').attr('content'),
    order_id: {{ $orderId }}
  },
  ...
})
```

### 2. **Better Error Messages (Frontend)**
Now distinguishes between:
- `403 Unauthorized` → Session expired, refresh and login again
- `401 Unauthorized` → Not logged in, redirect to login
- `400/500` → Specific errors from the server

### 3. **Enhanced Logging (Backend)**
Added detailed logging in `RegistrationPaymentController::applyWallet()`:

```php
Log::warning('WALLET APPLY: User not authenticated', [
  'order_id' => $orderId,
  'session_id' => session()->getId(),
  'ip' => $request->ip(),
  'user_agent' => substr($request->userAgent(), 0, 100),
]);
```

Check logs in: `storage/logs/laravel.log`

### 4. **Error Handling & Try-Catch**
Added exception handling to prevent crashes and provide better error responses.

## Troubleshooting Steps

### Step 1: Check Session Table Exists
On production, run:
```bash
php artisan session:table
php artisan migrate
```

This creates the `sessions` table needed for database-driven sessions.

### Step 2: Verify Session Configuration
Check `.env` on production:
```
SESSION_DRIVER=database      # Must be 'database' or 'file'
SESSION_LIFETIME=120         # Minutes before session expires
```

If using `file` driver on shared hosting, switch to `database`:
```
SESSION_DRIVER=database
```

### Step 3: Check CSRF Token
Open browser DevTools (F12) and verify the CSRF token exists:
```javascript
// In Console, run:
$('meta[name="csrf-token"]').attr('content')
```

Should return a token like: `Zd7kL9mN2pQ...`

### Step 4: Check Server Logs
On production, check the error logs:
```bash
# SSH into server and run:
tail -f /home/username/ct/storage/logs/laravel.log | grep "WALLET APPLY"
```

Look for entries like:
```
[2024-01-15 10:30:45] local.WARNING: WALLET APPLY: User not authenticated {...}
```

### Step 5: Check Session Persistence
If using shared hosting with multiple PHP-FPM processes, sessions might not persist:

**Option A: Switch to database sessions** (Recommended)
```env
SESSION_DRIVER=database
```

**Option B: Use Redis sessions** (If Redis available)
```env
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

**Option C: Use file sessions with shared storage**
```env
SESSION_DRIVER=file
```

### Step 6: Test Locally
On local machine, test the wallet apply flow:
1. Go to: `http://localhost/ct/public/register`
2. Create an order with PayFast payment
3. Go to checkout page
4. Click "Apply Wallet Balance"

If it works locally but not on production, it's a **session persistence issue**.

## Production Deployment Checklist

- [ ] Session table created: `php artisan migrate`
- [ ] Session driver in `.env` is `database` or `redis`
- [ ] Clear caches: `php artisan config:cache`
- [ ] Clear view cache: `php artisan view:clear`
- [ ] Check server logs: `tail -f storage/logs/laravel.log`
- [ ] Test wallet apply on staging/production
- [ ] Verify session cookie is being set in browser (DevTools → Application → Cookies)

## Support Contact
For withdrawal support or payment issues, email: **support@capetennis.co.za**

## Version Info
- **Fixed in**: Cape Tennis v1.0.1
- **Date**: January 2024
- **Related Issues**: Wallet apply returning 403 Unauthorized on production
