# Production Data Cleanup Runbook

> **Last updated:** 2026-07-01  
> **Applies to:** Cape Tennis platform — Laravel / MySQL production database  
> **Support email:** support@capetennis.co.za

---

## Overview

This runbook covers the safe cleanup of seven categories of historical data integrity issues
identified during the Schema + Data Consistency Hardening phase.

**Order of operations (money and entries first, fixtures last):**

| Step | Command | Rows (prod) | Risk |
|------|---------|-------------|------|
| 1 | `data:cleanup-duplicate-payfast-ids` | 8 | Export-only — no mutation |
| 2 | `data:cleanup-duplicate-registrations` | 2 | Soft-delete older duplicate |
| 3 | `data:cleanup-duplicate-fixture-results` | 2,915 | Hard-delete older duplicate result rows |
| 4 | `data:cleanup-orphan-registrations` | 209 | Soft-delete (skip if payment present) |
| 5 | `data:cleanup-withdrawn-softdeletes` | 34 | Set `deleted_at` only |
| 6 | `data:cleanup-refund-without-withdrawal` | 3 | Export-only — no mutation |
| 7 | `data:cleanup-orphan-fixtures` | 331 | Hard-delete + linked fixture_results |

---

## Prerequisites

### 1. Take a full database backup

```bash
mysqldump -u root -p ct > backups/ct_before_cleanup_$(date +%Y%m%d_%H%M%S).sql
```

Verify the backup is readable before proceeding:

```bash
mysql -u root -p -e "SELECT COUNT(*) FROM ct.fixtures;" < /dev/null
```

### 2. Confirm storage/cleanup directory is writable

```bash
mkdir -p storage/cleanup
chmod 775 storage/cleanup
```

---

## Step 1 — Duplicate PayFast IDs (EXPORT ONLY)

**Rule:** Never auto-delete payment records. Export for manual finance team review.

```bash
# Dry-run + export
php artisan data:cleanup-duplicate-payfast-ids --dry-run \
  --export=storage/cleanup/dup_payfast_$(date +%Y%m%d).csv

# Review the CSV — no --confirm needed, this command never mutates data
cat storage/cleanup/dup_payfast_*.csv
```

**Action required after export:**
1. Open `storage/cleanup/dup_payfast_*.csv`
2. Identify which row per `pf_payment_id` is the true payment vs a retry
3. Manually archive or delete the duplicate after finance team sign-off
4. **Do NOT run with --confirm** — it has no effect (intentional safety gate)

---

## Step 2 — Duplicate Active Registrations

**Rule:** Keep newest row with payment evidence; soft-delete older duplicate.

```bash
# Dry-run with export
php artisan data:cleanup-duplicate-registrations --dry-run \
  --export=storage/cleanup/dup_cer_$(date +%Y%m%d).csv

# Review CSV — check risk_note column for any WARNING rows
cat storage/cleanup/dup_cer_*.csv

# Apply (only after reviewing export — especially WARNING rows)
php artisan data:cleanup-duplicate-registrations --confirm \
  --export=storage/cleanup/dup_cer_$(date +%Y%m%d)_applied.csv
```

**Rollback:**
```sql
-- Undo soft-deletes applied in this step
UPDATE category_event_registrations
SET status = 'active', deleted_at = NULL, updated_at = NOW()
WHERE id IN (<ids from export CSV discard_id column>);
```

---

## Step 3 — Duplicate Fixture Results

**Rule:** Keep highest `id` row per `(fixture_id, set_nr)` pair (incl. NULL set_nr).

```bash
# Dry-run — 2,915 rows expected
php artisan data:cleanup-duplicate-fixture-results --dry-run \
  --export=storage/cleanup/dup_fixture_results_$(date +%Y%m%d).csv

# Apply in batches if desired
php artisan data:cleanup-duplicate-fixture-results --confirm --limit=500 \
  --export=storage/cleanup/dup_fixture_results_batch1_$(date +%Y%m%d).csv

# Repeat until 0 rows found
php artisan data:cleanup-duplicate-fixture-results --dry-run
```

**Rollback:**
Fixture results are derived score data. If rollback is needed, restore from the full DB backup
taken in the prerequisite step. There is no row-level rollback for hard-deletes.

---

## Step 4 — Orphan Registrations

**Rule:** Only soft-delete rows with no payment or refund dependency.
Rows with `pf_transaction_id` or `refund_gross > 0` are skipped automatically.

```bash
# Dry-run — 209 rows expected
php artisan data:cleanup-orphan-registrations --dry-run \
  --export=storage/cleanup/orphan_cer_$(date +%Y%m%d).csv

# Review export — check "HAS PAYMENT" rows manually before confirming
grep "HAS PAYMENT" storage/cleanup/orphan_cer_*.csv

# Apply
php artisan data:cleanup-orphan-registrations --confirm \
  --export=storage/cleanup/orphan_cer_$(date +%Y%m%d)_applied.csv
```

**Rollback:**
```sql
UPDATE category_event_registrations
SET status = 'active', deleted_at = NULL, updated_at = NOW()
WHERE id IN (<ids from export CSV>);
```

---

## Step 5 — Withdrawn CERs Missing `deleted_at`

**Rule:** Set `deleted_at = withdrawn_at ?? updated_at ?? NOW()` only where `status = 'withdrawn'`.

```bash
# Dry-run — 34 rows expected
php artisan data:cleanup-withdrawn-softdeletes --dry-run \
  --export=storage/cleanup/withdrawn_softdeletes_$(date +%Y%m%d).csv

# Apply
php artisan data:cleanup-withdrawn-softdeletes --confirm
```

**Rollback:**
```sql
UPDATE category_event_registrations
SET deleted_at = NULL, updated_at = NOW()
WHERE id IN (<ids from export CSV>);
```

---

## Step 6 — Refunds Without Withdrawal Records (EXPORT ONLY)

**Rule:** Never auto-mutate `refund_status`. Export for manual finance review.

```bash
# Dry-run + export — 3 rows expected
php artisan data:cleanup-refund-without-withdrawal --dry-run \
  --export=storage/cleanup/refund_no_withdrawal_$(date +%Y%m%d).csv

# Review
cat storage/cleanup/refund_no_withdrawal_*.csv
```

**Action required after export:**
1. For each row, verify whether the registration was actually withdrawn
2. If yes: create the missing `withdrawals` record manually, or update `refund_status`
   to `not_refunded` if the refund was cancelled
3. Involve finance team before any mutation

---

## Step 7 — Orphan Fixtures

**Rule:** Hard-delete fixtures whose `draw_id` no longer exists.
Also hard-deletes linked `fixture_results`.

```bash
# Dry-run — 331 rows expected
php artisan data:cleanup-orphan-fixtures --dry-run \
  --export=storage/cleanup/orphan_fixtures_$(date +%Y%m%d).csv

# Apply in batches
php artisan data:cleanup-orphan-fixtures --confirm --limit=100 \
  --export=storage/cleanup/orphan_fixtures_batch1_$(date +%Y%m%d).csv

# Repeat until 0 rows found
php artisan data:cleanup-orphan-fixtures --dry-run
```

**Rollback:**
Restore from DB backup — hard-deletes of fixtures/results are not reversible at row level.

---

## Post-Cleanup Integrity Checks

Run after each step or after all steps to confirm the counts drop to zero:

```bash
php artisan schema:audit
php artisan draw:integrity-check
php artisan finance:integrity-check

# Or all at once:
php artisan schema:integrity-check
```

Expected terminal state after full cleanup:

| Issue | Before | After |
|-------|--------|-------|
| Duplicate PayFast IDs | 4 groups (8 rows) | Manually resolved |
| Duplicate active CERs | 2 pairs | 0 |
| Duplicate fixture_results | 2,498 pairs (2,915 rows) | 0 |
| Orphan CERs | 209 | ≤ rows with payment (not auto-fixed) |
| Withdrawn CERs without deleted_at | 34 | 0 |
| Refunds without withdrawal | 3 | Manually resolved |
| Orphan fixtures | 331 | 0 |

---

## Option Reference

| Option | Description |
|--------|-------------|
| `--dry-run` | Read-only preview. Never mutates data. |
| `--confirm` | Required to apply any destructive changes. |
| `--limit=N` | Process at most N rows in one run. Safe for batching. |
| `--export=path` | Write affected rows to a CSV file at `path`. |

---

## Warnings Before Production Cleanup

1. **Always take a backup first.** All hard-delete operations (fixture results, orphan fixtures)
   are irreversible without a backup.

2. **Review every export CSV before confirming.** The `risk_note` column flags rows that need
   manual judgment — especially `WARNING: discard row has payment ref`.

3. **PayFast and refund commands never auto-delete.** `data:cleanup-duplicate-payfast-ids`
   and `data:cleanup-refund-without-withdrawal` are export-only regardless of `--confirm`.

4. **Batch large cleanups.** Use `--limit=100` for fixture results and orphan fixtures
   to avoid long-running transactions.

5. **Run integrity checks after each step** to confirm the row counts drop as expected.

6. **Do not run cleanup commands concurrently** with active tournament draws or payment
   processing. Schedule during a maintenance window.
