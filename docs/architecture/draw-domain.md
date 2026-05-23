# Draw Domain — Architecture Reference

Cape Tennis Platform — Draw Domain

---

## Overview

The draw domain manages tournament bracket generation, fixture progression, score recording, and result publication. It is the most complex domain in the platform and must be treated with extreme care when modified.

---

## Key Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Draw` | `draws` | Bracket container per category-event. Holds lock/publish state. |
| `Fixture` | `fixtures` | Individual match slot. May have a parent (progression target). |
| `FixtureResult` | `fixture_results` | Score sets for a fixture. One row per set. |
| `DrawAuditLog` | `draw_audit_logs` | Immutable log of draw mutations (generation, deletion, etc.). |
| `EngineRun` | `engine_runs` | Tracks which engine handled each draw operation and the outcome. |
| `EngineMismatch` | `engine_mismatches` | Records when canonical and legacy engines produce different results. |

---

## Service Layer

| Class | Responsibility |
|-------|----------------|
| `DrawGeneratorService` | Orchestrates draw generation — delegates to engine |
| `EngineRouter` | Routes draw operations to legacy, canonical, or hybrid engine |
| `CanonicalDrawEngine` | Pure canonical draw generator |
| `ProgressionService` | Advances winners through bracket rounds |
| `StandingsService` | Calculates group/round-robin standings |

---

## Mutation Rules

### Draw Generation

- Only callable via `DrawGeneratorService::generate()` — never call engine classes directly from a controller.
- Must log to `platform_audit_logs` via `PlatformAuditLogger::log(DRAW_GENERATED, ...)`.
- Must wrap in a DB transaction.
- Must record an `EngineRun` regardless of success/failure.

### Draw Lock / Unlock

- A locked draw cannot have fixtures added or removed.
- Locking must be logged: `PlatformAuditLogger::log(DRAW_LOCKED, ...)`.
- Unlocking requires super-user role.

### Draw Deletion

- Only allowed if draw is unpublished.
- Must cascade-delete fixtures and fixture_results (handled by FK or explicit cascade).
- Must log: `PlatformAuditLogger::log(DRAW_DELETED, ...)`.

### Score Recording / Deletion

- Score save must check fixture lock state.
- Score deletion must log: `PlatformAuditLogger::log(SCORE_DELETED, ...)`.
- Never delete a score without checking if progression has already advanced from it.

### Progression

- Progression must only advance a winner into a target fixture if the target is empty.
- Progression reset must clear `winner_id` from the target fixture and cascade if rounds depend on it.
- Both advancement and reset must log via `PlatformAuditLogger`.

---

## Forbidden Patterns

- ❌ Direct `DB::table('fixtures')->insert(...)` — use `Fixture::create()` via service layer
- ❌ Direct `DB::table('draws')->update(...)` for lock/publish — use `DrawService` methods
- ❌ Bypassing `EngineRouter` for draw generation
- ❌ Deleting fixture_results without checking progression state
- ❌ Setting `draws.published = 1` without setting `draws.locked = 1` first

---

## Transaction Rules

All draw generation, progression, and deletion operations must be wrapped in a DB transaction:

```php
DB::transaction(function () {
    // draw work here
});
```

Use `afterCommit` hooks (via model observers or explicit `DB::afterCommit()`) for:
- Sending draw publication notifications
- Triggering standing recalculations
- Cache invalidation

---

## Engine Modes

See `docs/architecture/engine-modes.md` for full detail.

Summary:
- `legacy` — original draw generator only
- `hybrid` — both engines run, legacy result used, canonical compared
- `canonical` — canonical engine result used

The active mode is read from `config('engine.mode')` and can be overridden per-event via `FeatureFlags::setForEvent()`.

---

## Integrity Checks

```bash
php artisan draw:integrity-check
php artisan schema:integrity-check
php artisan platform:health-check --section=draw
```
