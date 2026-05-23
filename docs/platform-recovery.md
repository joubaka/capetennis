# Platform Recovery Guide

Cape Tennis Platform — Backup, Rollback, and Recovery Procedures

---

## Table of Contents

1. [Before Any Destructive Action](#before-any-destructive-action)
2. [Backup Procedure](#backup-procedure)
3. [Backup Verification](#backup-verification)
4. [Migration Rollback Checklist](#migration-rollback-checklist)
5. [Engine Rollback Guidance](#engine-rollback-guidance)
6. [Cleanup Command Rollback Guidance](#cleanup-command-rollback-guidance)
7. [Emergency Recovery Steps](#emergency-recovery-steps)
8. [Contact](#contact)

---

## Before Any Destructive Action

**Always complete this checklist before running any migration, cleanup, or engine mode change in production:**

- [ ] Take a full database backup (see section below)
- [ ] Run `php artisan platform:preflight` — must pass
- [ ] Run `php artisan platform:health-check` — note current warning count as baseline
- [ ] Announce a maintenance window to the team
- [ ] Have rollback plan written down before you start

---

## Backup Procedure

### Full MySQL Dump

```bash
# Replace <db_name>, <user>, <pass> with production values
mysqldump -u <user> -p<pass> --single-transaction --routines --triggers <db_name> \
  > /backups/ct_$(date +%Y%m%d_%H%M%S).sql

# Verify file size is non-zero
ls -lh /backups/ct_*.sql | tail -1
```

### Key Tables Only (faster, for partial recovery)

```bash
mysqldump -u <user> -p<pass> --single-transaction <db_name> \
  users events category_events category_event_registrations \
  draws fixtures fixture_results transactions_pf wallets wallet_transactions \
  > /backups/ct_key_tables_$(date +%Y%m%d_%H%M%S).sql
```

### Verify the Backup

```bash
# Point artisan at the backup DB or run against a restored copy
php artisan platform:verify-backup
php artisan platform:verify-backup --min-rows=500
```

---

## Backup Verification

Run the built-in verification command after every backup:

```bash
php artisan platform:verify-backup
# Options:
#   --min-rows=N     Minimum expected row count per table (default: 100)
#   --table=users    Only check specific table(s)
```

The command checks:
- Row counts on all key tables
- Fixture → Draw reference integrity
- CER → CategoryEvent reference integrity

Exit code `0` = verification passed. Exit code `1` = issues found (do not use backup for recovery).

---

## Migration Rollback Checklist

### Before Running `migrate`

1. Backup the database
2. Read the migration file — confirm it is reversible (`down()` method is correct)
3. Check if the migration touches financial tables — extra caution required
4. Run `php artisan migrate:status` to see current state

### Rolling Back a Migration

```bash
# Roll back the last batch
php artisan migrate:rollback

# Roll back a specific number of steps
php artisan migrate:rollback --step=2

# Roll back to a specific migration (manual — requires editing migration status table)
# 1. Note the batch number of the migration you want to remove
# 2. UPDATE migrations SET batch = <current_max + 1> WHERE migration = '<name>';
# 3. php artisan migrate:rollback --step=1
```

### Unsafe Migration Patterns (never deploy these)

- `Schema::drop()` on a table with live data without a pre-backup gate
- `->change()` on a financial column without a before/after snapshot
- Removing a column that is still referenced in code
- Adding a NOT NULL column without a default on a large table (table lock risk)

### After Rollback

```bash
php artisan platform:health-check
php artisan schema:integrity-check
```

---

## Engine Rollback Guidance

The engine mode is controlled by the `ENGINE_MODE` environment variable and the `FLAG_*` feature flags.

### Rolling Back from Canonical → Hybrid → Legacy

**Step 1 — Immediate runtime rollback (no deployment needed)**

```bash
# Via artisan (takes effect within 60 seconds due to config cache TTL)
php artisan tinker
# In tinker:
\App\Services\FeatureFlags::disable('canonical_engine');
\App\Services\FeatureFlags::disable('hybrid_engine');
```

**Step 2 — Environment rollback (requires deployment)**

```env
# In .env
ENGINE_MODE=legacy
FLAG_CANONICAL_ENGINE=false
FLAG_HYBRID_ENGINE=false
```

Then:
```bash
php artisan config:clear
php artisan cache:clear
```

**Step 3 — Verify**

```bash
php artisan platform:health-check --section=engine
```

### Monitoring During Engine Mode Changes

Watch mismatch rate via the dashboard at `/platform/health` or:

```bash
# Check mismatch rate live (last 2 hours)
php artisan platform:release-audit --since=1hour
```

**Safe thresholds:**
- Mismatch rate < 1% = healthy
- Mismatch rate 1–5% = monitor closely
- Mismatch rate > 5% = roll back immediately

### Resolving Unresolved Mismatches

High-severity mismatches block the `platform:preflight` check (as warnings). Resolve them:

```bash
php artisan tinker
# Mark a mismatch resolved after investigation:
\App\Models\EngineMismatch::where('severity', 'high')->where('resolved', false)
    ->update(['resolved' => true, 'resolution_notes' => 'Accepted: legacy behavior expected']);
```

---

## Cleanup Command Rollback Guidance

All cleanup commands support `--dry-run` and export affected rows to a CSV before deletion. The CSV is the rollback artifact.

### General Rollback Process

1. **Find the export CSV** created when the command was run with `--export=path/to/file.csv`
2. **Restore rows manually** using the CSV as a reference
3. For soft-delete corrections (`data:cleanup-withdrawn-softdeletes`), the rollback is:
   ```sql
   UPDATE category_event_registrations
   SET deleted_at = NULL
   WHERE id IN (<ids from CSV>);
   ```
4. For deleted duplicate results (`data:cleanup-duplicate-fixture-results`), restore from backup:
   ```bash
   # Restore only fixture_results from backup
   mysqldump -u <user> -p<pass> <db_name> fixture_results > /tmp/fr_backup.sql
   # Selectively restore rows by ID using the CSV
   ```

### If No Export Was Taken

Restore from the full database backup taken before the cleanup run. Use the timestamp of the backup to identify the correct file.

```bash
# Restore full DB (destructive — replaces entire database)
mysql -u <user> -p<pass> <db_name> < /backups/ct_<timestamp>.sql

# Restore single table
mysql -u <user> -p<pass> <db_name> -e "DROP TABLE fixture_results;"
mysql -u <user> -p<pass> <db_name> < /backups/ct_<timestamp>.sql
```

---

## Emergency Recovery Steps

### Scenario: Production site down / database corrupted

1. Switch maintenance mode on: `php artisan down`
2. Identify the most recent verified backup
3. Restore: `mysql -u <user> -p<pass> <db_name> < /backups/ct_<timestamp>.sql`
4. Verify: `php artisan platform:verify-backup`
5. Clear caches: `php artisan cache:clear && php artisan config:clear`
6. Run integrity check: `php artisan schema:integrity-check`
7. Run health check: `php artisan platform:health-check`
8. If all green: `php artisan up`

### Scenario: Runaway cleanup deleted too much

1. `php artisan platform:health-check` — identify affected section
2. Restore from backup or from CSV export (see cleanup rollback above)
3. Re-run `php artisan platform:health-check` to confirm recovery
4. Review audit log: `SELECT * FROM platform_audit_logs WHERE action LIKE 'cleanup%' ORDER BY created_at DESC LIMIT 50;`

### Scenario: Engine mismatch spike

1. Immediately run `php artisan platform:release-audit --since=1hour`
2. Roll back engine mode (see Engine Rollback above)
3. Investigate mismatches: `SELECT * FROM engine_mismatches WHERE resolved = 0 ORDER BY created_at DESC LIMIT 20;`
4. Check engine runs: `SELECT * FROM engine_runs WHERE mismatch_detected = 1 ORDER BY created_at DESC LIMIT 20;`

---

## Contact

For platform withdrawal support: **support@capetennis.co.za**
