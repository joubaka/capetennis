# Engine Modes — Architecture Reference

Cape Tennis Platform — Draw Engine Modes

---

## Overview

The platform supports three engine execution modes, allowing safe progressive rollout of the canonical draw engine while keeping the legacy engine as a fallback.

---

## Modes

### `legacy`

- Only the legacy draw generator runs.
- No canonical engine is invoked.
- Default mode for safety.
- Set via: `ENGINE_MODE=legacy` or `FLAG_CANONICAL_ENGINE=false`, `FLAG_HYBRID_ENGINE=false`

### `hybrid`

- Both legacy and canonical engines run for every draw operation.
- The **legacy result is used** (returned to the caller).
- The canonical result is compared, and any difference is recorded in `engine_mismatches`.
- Mismatches are logged to `engine_runs.mismatch_detected = true`.
- Purpose: validate canonical engine correctness before switching production to it.
- Set via: `ENGINE_MODE=hybrid` or `FLAG_HYBRID_ENGINE=true`

### `canonical`

- Only the canonical draw engine runs.
- Legacy engine is not invoked.
- The canonical result is returned.
- Engine fallback to legacy may occur if canonical throws an exception (recorded in `engine_runs.fallback_used = true`).
- Set via: `ENGINE_MODE=canonical` or `FLAG_CANONICAL_ENGINE=true`

---

## Mode Resolution Priority

```
Per-event FeatureFlag override
    → Admin runtime override (cache)
        → Environment variable / config
```

Code:
```php
// Check current mode
$mode = config('engine.mode', env('ENGINE_MODE', 'legacy'));

// Check canonical flag
if (FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE, $eventId)) {
    // use canonical
}
```

---

## Switching Modes

### Via Feature Flag (no deployment required)

```php
// Enable hybrid for all events
FeatureFlags::enable(FeatureFlags::HYBRID_ENGINE);

// Enable canonical for one event only
FeatureFlags::setForEvent(42, FeatureFlags::CANONICAL_ENGINE, true);

// Revert
FeatureFlags::disable(FeatureFlags::HYBRID_ENGINE);
FeatureFlags::clearForEvent(42, FeatureFlags::CANONICAL_ENGINE);
```

### Via Environment (requires deployment)

```env
ENGINE_MODE=hybrid
FLAG_HYBRID_ENGINE=true
FLAG_CANONICAL_ENGINE=false
```

Then:
```bash
php artisan config:clear
```

---

## Mismatch Monitoring

Monitor mismatches in real time:

```bash
# CLI dashboard
php artisan platform:health-check --section=engine

# Release audit (last hour)
php artisan platform:release-audit --since=1hour

# Direct DB query
SELECT operation_type, COUNT(*) as runs,
       SUM(mismatch_detected) as mismatches,
       ROUND(100 * SUM(mismatch_detected) / COUNT(*), 1) AS mismatch_pct
FROM engine_runs
WHERE created_at >= NOW() - INTERVAL 24 HOUR
GROUP BY operation_type;
```

**Safe rollout thresholds:**
| Mismatch Rate | Action |
|--------------|--------|
| 0–1% | Healthy — proceed |
| 1–5% | Monitor — investigate mismatch details |
| >5% | Roll back immediately |

---

## Fallback Behaviour

When the canonical engine throws an unhandled exception in `canonical` mode:
1. The `EngineRouter` catches the exception.
2. The legacy engine is invoked as fallback.
3. `engine_runs.fallback_used = true` is recorded.
4. `PlatformAuditLogger::log(ENGINE_FALLBACK, ...)` is written.
5. The fallback result is returned to the caller — no user-facing error.

Fallback should be rare. A spike in fallback rate indicates a canonical engine bug.

---

## Rollback

See `docs/platform-recovery.md` → Engine Rollback Guidance.
