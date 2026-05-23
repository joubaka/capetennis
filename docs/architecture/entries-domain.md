# Entries Domain — Architecture Reference

Cape Tennis Platform — Entries (Registrations) Domain

---

## Overview

The entries domain manages player registrations to category-events, withdrawal processing, and entry validation. It is closely coupled to the payments domain (entry fees) and the draw domain (seeding, fixture assignment).

---

## Key Models

| Model | Table | Purpose |
|-------|-------|---------|
| `CategoryEventRegistration` (CER) | `category_event_registrations` | A player's entry in a category-event |
| `Registration` | `registrations` | Player's registration for a tournament (parent of CERs) |
| `Withdrawal` | `withdrawals` | Withdrawal record linked to a CER |
| `CategoryEvent` | `category_events` | A competitive category within an event |

---

## CER Status Lifecycle

```
pending → active → withdrawn (soft-deleted)
                ↘ refunded
```

- `status = active`, `deleted_at = null` → valid, active entry
- `status = withdrawn`, `deleted_at = <timestamp>` → properly withdrawn
- `status = withdrawn`, `deleted_at = null` → **data drift** — run `data:cleanup-withdrawn-softdeletes`
- `status = active`, duplicate `(category_event_id, registration_id)` → **data drift** — run `data:cleanup-duplicate-registrations`

---

## Mutation Rules

### Entry Creation

- Check for existing active CER for the same `(category_event_id, registration_id)` before creating.
- Wrap creation and payment in a DB transaction.
- Never create a CER without a corresponding `Registration`.

### Withdrawal

- Must create a `Withdrawal` record.
- Must soft-delete the CER: `$cer->delete()` (sets `deleted_at`).
- Must set `status = withdrawn`.
- Log: `PlatformAuditLogger::log(WITHDRAWAL_PROCESSED, $cer, before: $before, after: $after)`.
- If a refund is owed, trigger refund flow (see payments domain).

### Withdrawal Reversal

- Only allowed by super-user.
- Restore the CER: `$cer->restore()` (clears `deleted_at`), set `status = active`.
- Must check that the draw slot is still available.
- Log: `PlatformAuditLogger::log(WITHDRAWAL_REVERSED, $cer)`.

---

## Forbidden Patterns

- ❌ `DB::table('category_event_registrations')->delete(...)` — use Eloquent soft-delete
- ❌ Creating a CER without checking for duplicates
- ❌ Setting `status = withdrawn` without also calling `$cer->delete()`
- ❌ Hard-deleting a CER with an associated payment record

---

## Transaction Rules

Entry creation, withdrawal, and reversal must be wrapped in `DB::transaction()`.

`afterCommit`:
- Send confirmation email after successful entry
- Send withdrawal confirmation after withdrawal committed

---

## Integrity Checks

```bash
php artisan platform:health-check --section=registration
php artisan data:cleanup-duplicate-registrations --dry-run
php artisan data:cleanup-orphan-registrations --dry-run
php artisan data:cleanup-withdrawn-softdeletes --dry-run
```
