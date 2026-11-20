# Player Plus overnight calculations

Player Plus reads saved ratings, history and match charts. Page requests never
run rating fits or start a reconstruction job. The normal automatic calculation
path is one ordered overnight pipeline, starting at **03:02 Africa/Johannesburg**.
This is the first documented host cron tick after 03:00, not evidence that the
hosting scheduler is installed or running.

## Order and audience

1. Refresh the canonical current ability snapshot.
2. Refresh the saved twelve-month rating history.
3. For each currently entitled Plus player from the canonical active-player
   resolver, generate chronological before/after match data, then refresh that
   player's past-event rating history before advancing to the next player.

Super User preview permission does not place a player in this active Plus
audience. A player without an active entitlement can have readable saved ratings
and results but no per-player match graph. Its preview must explain the separate
administrator generation requirement rather than promise an overnight update.
See the [match reconstruction procedure](performance/match-ctr-timeline.md#private-saved-data-and-refresh).

Do not run dependent stages when a prerequisite fails. Builders retain their
existing locks, publication/identity rules and last-good saved data. The pipeline
does not grant access, publish events, send mail, change results or mutate payment
records. Existing notification and financial schedules remain separate.

Transient ability/history failures have a bounded retry allowance in the same
overnight window. A changed source context stops the old run rather than mixing
different snapshots. Persistent failures require operator review.

## Bounded continuation

The nightly start replaces the independent midnight ability job, standalone
history job and automatic daytime pending-ability fits. Scheduled continuation
runs only in the overnight window, **03:07–06:57 SAST**. The documented host's
five-minute invocations are sufficient to reach these continuation ticks.

Each invocation has a player batch and runtime budget, checked between canonical
commands. A global pipeline lock and private run state preserve the current
stage, source context and player cursor. A partial run is not reported as complete.
Resume the same context instead of rebuilding the base snapshots or restarting
the audience at zero. Unfinished players retain priority across nightly runs;
the audience is traversed once before wrapping. A changed source context must not
be mixed into a partially built run.

Per-player failures are recorded without preventing unrelated eligible players
from being processed. Inspect partial/failure status and actual runtime before
claiming every user was refreshed. A bounded job cannot guarantee a completion
time independent of workload.

Completing the audience with some failed reconstructions is an explicit error
outcome, not full success. Operator/cron exit status reflects that distinction;
manual progress messaging can still distinguish successfully saved base ratings
and history from unavailable per-player reconstructions.

## Explicit operator refresh

```powershell
php artisan player-plus:refresh-derived
php artisan player-plus:refresh-derived --resume
```

These are deliberate offline operator actions. The `--scheduled` flag enforces
the automatic overnight window; ordinary page visits never invoke either path.
Manual background refresh controls preserve their authorization, launch leases
and diagnostics while using the ordered calculation path.

Only run the commands in an authorized environment. Local code and tests do not
authorize deployment, production execution, cron changes or mail delivery.

## Freshness and page speed

Pages still enforce current access, identity and publication restrictions. New or
changed source data may withhold a saved artifact until the next coherent build;
never bypass revocation checks to keep old results visible. Cached calculations
remove model-fitting work from requests, but do not prove request latency: global
history decoding and source-validation reads still need representative monitoring.

See [before/after match reconstruction](performance/match-ctr-timeline.md) for its
scheduled-order limitation and [readiness diagnostics](player-plus-readiness.md)
for read-only saved-data and configuration checks.
