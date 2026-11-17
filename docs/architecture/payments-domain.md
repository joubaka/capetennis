# Payments Domain — Architecture Reference

Cape Tennis Platform — Payments Domain

---

## Overview

The payments domain handles PayFast payment processing, wallet credit, refund issuance, and bank transfer tracking. It is the highest-risk domain — all mutations must be idempotent, logged, and transactional.

---

## Key Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Transaction` | `transactions_pf` | Provider payment receipt |
| `Wallet` | `wallets` | Player wallet (credit balance) |
| `WalletTransaction` | `wallet_transactions` | Credit/debit entries per wallet |
| `BankRefund` | `bank_refunds` | Bank transfer refund records |

---

## Payment Flow

```
Verified PayFast COMPLETE ITN → canonical order finalization + provider receipt + entry/invitation confirmation
```

Individual registration settlement is atomic across these writes. Reserved wallet
funds are debited only during verified finalization; a PayFast registration does
not credit a payer wallet. Browser returns must report saved payment state rather
than imply settlement. See [individual PayFast confirmation](../payfast-registration-confirmation.md)
for retry, replay, delivery diagnostics and expired-checkout recovery boundaries.

Refund flow:
```
Verified withdrawal → canonical refund service → provider reversal, wallet credit or reviewed bank refund
Refund state records the reversal; original paid history remains an audit record.
```

---

## Mutation Rules

### Payment Receipt (ITN)

- Must be **idempotent** — duplicate ITN calls for the same `pf_payment_id` must not duplicate receipts, wallet debits or entries.
- Verify `pf_payment_id` uniqueness and ownership before creating a provider receipt.
- Any required wallet ledger debit must be inside the complete settlement transaction with the provider receipt and entry/invitation updates.
- Log: `PlatformAuditLogger::log(PAYMENT_COMPLETION, ...)` (via `PerformanceTracker::track`).

### Refund Issuance

- Never mutate wallet balance directly — append the appropriate credit/debit ledger entry through the canonical financial service.
- Refund must correspond to an existing withdrawal and pass the canonical ownership, deadline, payment and idempotency checks.
- Log: `PlatformAuditLogger::log(REFUND_ISSUED, $cer, before: $before, after: $after)`.
- If no withdrawal exists, surface as a `data:cleanup-refund-without-withdrawal` candidate, not a silent fix.

### Wallet Balance

- Wallet balance = `SUM(credit transactions) - SUM(debit transactions)` — never stored as a column.
- Never cache wallet balance across requests without invalidating on any wallet_transaction change.

---

## Forbidden Patterns

- ❌ `DB::table('wallets')->increment('balance', ...)` — wallets have no stored balance column
- ❌ Creating a provider receipt without checking `pf_payment_id` uniqueness and ownership
- ❌ Issuing a refund outside a DB transaction
- ❌ Deleting a `WalletTransaction` — use the canonical service to append the appropriate compensating ledger entry
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

Use current read-only integrity evidence to identify duplicate `pf_payment_id`
groups. Historical counts are not current production evidence. Review exact
records and obtain the applicable authorization before any repair; a reporting
request does not authorize cleanup or deletion.

Contact for withdrawals/refund issues: **support@capetennis.co.za**
