# Payments Domain — Architecture Reference

Cape Tennis Platform — Payments Domain

---

## Overview

The payments domain handles PayFast payment processing, wallet credit, refund issuance, and bank transfer tracking. It is the highest-risk domain — all mutations must be idempotent, logged, and transactional.

---

## Key Models

| Model | Table | Purpose |
|-------|-------|---------|
| `TransactionPf` | `transactions_pf` | PayFast ITN payment record |
| `Wallet` | `wallets` | Player wallet (credit balance) |
| `WalletTransaction` | `wallet_transactions` | Credit/debit entries per wallet |
| `BankRefund` | `bank_refunds` | Bank transfer refund records |

---

## Payment Flow

```
PayFast ITN → TransactionPf created → Wallet credited → CER status updated
```

Refund flow:
```
Admin issues refund → WalletTransaction (debit) → CER refund_status = refunded
Bank refund path:   → BankRefund created        → manual bank transfer → BankRefund confirmed
```

---

## Mutation Rules

### Payment Receipt (ITN)

- Must be **idempotent** — duplicate ITN calls for the same `pf_payment_id` must not double-credit wallets.
- Verify `pf_payment_id` uniqueness before creating `TransactionPf`.
- Wallet credit must be inside a DB transaction with the `TransactionPf` insert.
- Log: `PlatformAuditLogger::log(PAYMENT_COMPLETION, ...)` (via `PerformanceTracker::track`).

### Refund Issuance

- Never mutate wallet balance directly — always insert a `WalletTransaction` debit row.
- Refund must correspond to an existing withdrawal — check for `withdrawals` record first.
- Log: `PlatformAuditLogger::log(REFUND_ISSUED, $cer, before: $before, after: $after)`.
- If no withdrawal exists, surface as a `data:cleanup-refund-without-withdrawal` candidate, not a silent fix.

### Wallet Balance

- Wallet balance = `SUM(credit transactions) - SUM(debit transactions)` — never stored as a column.
- Never cache wallet balance across requests without invalidating on any wallet_transaction change.

---

## Forbidden Patterns

- ❌ `DB::table('wallets')->increment('balance', ...)` — wallets have no stored balance column
- ❌ Creating `TransactionPf` without checking `pf_payment_id` uniqueness
- ❌ Issuing a refund outside a DB transaction
- ❌ Deleting a `WalletTransaction` — debit instead
- ❌ Reading wallet balance with a raw SUM without the proper join (see `PlatformHealthService` for correct pattern)

---

## Transaction Rules

All payment and refund mutations must be wrapped in `DB::transaction()`.

`afterCommit` usage:
- Send payment confirmation email after ITN transaction committed
- Send refund notification after refund committed

---

## Integrity Checks

```bash
php artisan finance:integrity-check
php artisan platform:health-check --section=financial
php artisan data:cleanup-duplicate-payfast-ids --dry-run
php artisan data:cleanup-refund-without-withdrawal --dry-run
```

---

## Duplicate Payment Detection

Known issue: 4 duplicate `pf_payment_id` groups exist in production (pre-existing data drift).
Run the cleanup command before any financial reporting.

Contact for withdrawals/refund issues: **support@capetennis.co.za**
