# Mutation Rules — Architecture Reference

Cape Tennis Platform — Canonical Mutation Paths and Forbidden Patterns

---

## Principle

Every data mutation on the platform must:
1. Go through the **service layer** — never directly from a controller or command
2. Be **wrapped in a DB transaction**
3. Be **logged** via `PlatformAuditLogger`
4. Be **reversible** (or have a documented recovery path)
5. Respect **domain ownership** — cross-domain writes go through the owning service

---

## Canonical Mutation Paths

| Action | Canonical Path | Forbidden Alternative |
|--------|---------------|----------------------|
| Generate draw | `DrawGeneratorService::generate()` | Direct `Draw::create()` + fixture loop |
| Lock draw | `DrawService::lock()` | `DB::table('draws')->update(['locked' => 1])` |
| Delete draw | `DrawService::delete()` | `Draw::destroy()` |
| Save score | `ScoreService::save()` | `FixtureResult::create()` direct |
| Delete score | `ScoreService::delete()` | `FixtureResult::destroy()` |
| Advance progression | `ProgressionService::advance()` | Direct `Fixture::update(['winner_id' => ...])` |
| Issue refund | `RefundService::issue()` | Direct `WalletTransaction::create()` |
| Process withdrawal | `WithdrawalService::process()` | `$cer->update(['status' => 'withdrawn'])` |
| Create entry | `EntryService::create()` | Direct `CategoryEventRegistration::create()` |

---

## Transaction Rules

### Always use `DB::transaction()`

```php
DB::transaction(function () {
    // all domain work here
});
```

### Never nest transactions silently

If a service method already wraps work in a transaction, callers must not wrap it again unless using `DB::beginTransaction()` with proper nesting awareness.

### Use `afterCommit` for side effects

Side effects that should only fire when the transaction succeeds:

```php
DB::afterCommit(function () use ($draw) {
    // send notification
    // invalidate cache
    // dispatch job
});
```

**Never** send emails or dispatch jobs inside the transaction body — they will fire even if the transaction rolls back.

---

## Audit Logging Rules

Every governed mutation must log to `platform_audit_logs`:

```php
PlatformAuditLogger::log(
    action:    PlatformAuditLogger::DRAW_GENERATED,
    subject:   $draw,
    before:    null,            // state before (null if creating)
    after:     $draw->toArray(),
    meta:      ['event_id' => $draw->category_event_id],
);
```

### Minimum required audit events

| Domain | Events to log |
|--------|--------------|
| Draw | DRAW_GENERATED, DRAW_LOCKED, DRAW_UNLOCKED, DRAW_DELETED |
| Scores | SCORE_SAVED, SCORE_DELETED |
| Progression | PROGRESSION_ADVANCED, PROGRESSION_RESET |
| Payments | PAYMENT_COMPLETION (via PerformanceTracker) |
| Refunds | REFUND_ISSUED, REFUND_REVERSED |
| Entries | WITHDRAWAL_PROCESSED, WITHDRAWAL_REVERSED |
| Engine | ENGINE_FALLBACK, ENGINE_MODE_CHANGED |
| Cleanup | CLEANUP_RUN (with dry_run flag in metadata) |
| Admin | ADMIN_OVERRIDE (for any direct DB fix) |

---

## Forbidden Patterns

The following patterns are prohibited in production code:

### Debug output
```php
// ❌ Never commit these:
dd($variable);
dump($variable);
var_dump($variable);
```

### Direct financial mutations
```php
// ❌ Never:
DB::table('wallets')->increment('balance', 100);
DB::table('wallet_transactions')->delete();
WalletTransaction::find($id)->forceDelete();
```

### Raw draw mutations
```php
// ❌ Never:
DB::table('fixtures')->insert([...]);
DB::table('draws')->where('id', $id)->update(['locked' => 1]);
```

### Cross-domain direct writes
```php
// ❌ Never write to a draw from a payment controller:
Fixture::where('draw_id', $drawId)->update(['winner_id' => null]);
// ✅ Instead: call ProgressionService::reset() from within DrawService
```

### Unsafe migrations
```php
// ❌ Never in a migration without a pre-backup step:
Schema::drop('transactions_pf');
$table->dropColumn('pf_payment_id');
// ✅ Instead: mark column deprecated, keep it, migrate data, remove in a later release
```

---

## Service Ownership

| Domain | Owning Service(s) | Controller(s) |
|--------|------------------|---------------|
| Draw | `DrawGeneratorService`, `DrawService`, `ProgressionService` | `DrawController`, `FixtureController` |
| Payments | `PaymentService`, `PayFastService` | `PayFastController`, `WebhookController` |
| Refunds | `RefundService` | `RefundController`, `SuperAdminFinanceController` |
| Entries | `EntryService`, `WithdrawalService` | `RegistrationController`, `WithdrawalController` |
| Engine | `EngineRouter`, `CanonicalDrawEngine` | (never called from controllers directly) |
| Platform | `PlatformHealthService`, `PlatformAuditLogger`, `FeatureFlags` | `PlatformHealthController` |

---

## Feature Flag Checks

Before running any engine-mode-sensitive operation:

```php
use App\Services\FeatureFlags;

if (FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE, $eventId)) {
    // canonical path
} elseif (FeatureFlags::enabled(FeatureFlags::HYBRID_ENGINE, $eventId)) {
    // hybrid path
} else {
    // legacy path
}
```

Never hardcode `config('engine.mode')` directly in controllers — always go through `FeatureFlags`.
