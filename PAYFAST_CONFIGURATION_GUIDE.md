# PayFast Configuration Guide

## Issue: ITN Signature Validation Failing

Your PayFast Instant Transaction Notifications (ITNs) are being rejected with `[HYBRID ITN INVALID SIGNATURE]` errors. This prevents registration payments from being marked as complete.

## Root Cause

The PayFast passphrases are not configured in your `.env` file. Without the correct passphrases, ITN signatures cannot be validated.

## Solution: Configure PayFast Passphrases

### Step 1: Get Your Passphrases from PayFast Dashboard

1. Log in to your PayFast merchant dashboard
2. Go to **Settings → Security → Instant Transaction Notifications (ITN)**
3. You should see passphrases configured for your merchant accounts:
   - **Live Merchant (11307280)**: Get the passphrase
   - **Sandbox Merchant (10008657)**: Get the passphrase

### Step 2: Add to `.env` File

Open your `.env` file on the server and add:

```env
# PayFast Sandbox Configuration
PAYFAST_MERCHANT_ID_SANDBOX=10008657
PAYFAST_PASSPHRASE_SANDBOX=your_sandbox_passphrase_here

# PayFast Live Configuration
PAYFAST_MERCHANT_ID_LIVE=11307280
PAYFAST_PASSPHRASE_LIVE=your_live_passphrase_here
```

Replace:
- `your_sandbox_passphrase_here` with the actual sandbox passphrase from PayFast dashboard
- `your_live_passphrase_here` with the actual live passphrase from PayFast dashboard

### Step 3: Verify Configuration

After updating `.env`:

1. Clear Laravel config cache:
   ```bash
   php artisan config:clear
   ```

2. Monitor logs for successful validation:
   ```bash
   tail -f storage/logs/laravel.log | grep "PAYFAST SIG"
   ```

3. Look for `[PAYFAST SIG] ✓ Valid signature matched` messages

## Current Workaround

Until you configure the passphrases, the system will:
- Accept ITNs without signature validation (less secure)
- Log a warning: `[PAYFAST SIG] No passphrase configured - accepting ITN without validation`
- Process payments from both merchants (sandbox and live)

**Important**: This is a temporary fallback. Configure proper passphrases for production security.

## Testing PayFast ITN Locally

For local development without proper passphrases configured:

```php
// Use the simulatePayfast() method to test locally
GET /register/simulate-payfast/{orderId}
```

This marks an order as paid without going through actual PayFast.

## Reference

- **Live Merchant ID**: 11307280
- **Sandbox Merchant ID**: 10008657
- **ITN Endpoint**: https://www.capetennis.co.za/notify
- **Team ITN Endpoint**: https://www.capetennis.co.za/notify_team

## Troubleshooting

### Still Getting "Invalid Signature" Errors?

1. Verify the passphrases are correct in `.env`
2. Check that no spaces are included in the passphrases
3. Ensure `.env` is readable by the web server
4. Clear config cache: `php artisan config:clear`
5. Check logs for detailed validation attempts: `grep "PAYFAST SIG" storage/logs/laravel.log`

### Check Current Configuration

Run this artisan command to verify settings are loaded:

```bash
php artisan tinker
> env('PAYFAST_PASSPHRASE_LIVE')
> env('PAYFAST_PASSPHRASE_SANDBOX')
```

If these return `null`, the environment variables aren't loaded.
