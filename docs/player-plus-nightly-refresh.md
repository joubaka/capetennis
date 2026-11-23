# Player Plus overnight calculations

Player Plus reads saved ratings, history and match charts. Page requests never
run rating fits or start a reconstruction job. The normal automatic calculation
path is one ordered overnight pipeline, starting at **03:02 Africa/Johannesburg**.
This is the first documented host cron tick after 03:00, not evidence that the
hosting scheduler is installed or running.

## Order and audience

1. Refresh the canonical current ability snapshot.
2. Refresh the saved twelve-month rating history, then atomically publish small
   database history/evidence projections for the current active Plus audience.
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
never bypass revocation checks to keep old results visible.

After `2026_11_23_140000_create_player_history_projections.php` is applied and a
successful offline history refresh completes, Profile, Results and Rating can
read indexed exact-player monthly ratings and accepted evidence from the
database. The full network publication manifest remains attached to the build;
it is not reduced to the player's own events. Canonical artifact validation,
current exact source fingerprints, TeamSheet access, publication, identity,
relationship and entitlement checks retain their existing meaning. Request-only
memoization ends after each response; there is no cross-request freshness TTL.

Projection rows and the active build pointer activate in one database transaction.
Canonical history replacement first clears the previous database pointer. If the
new projection build fails, the command reports failure and pages use the new
validated canonical file rather than indefinitely preferring an older database
build. Missing schema, missing player rows, malformed payloads and obsolete
policies use the same validated file fallback. Nothing is generated on GET.
The projection audience is bounded to 10,000 active Plus players and each player
payload to 2 MiB; exceeding a bound fails the projection build without partial
activation. Admin previews outside this audience retain the file path.

This removes all-player JSON decoding, rating validation and evidence traversal
from successful player-projection reads. It does **not** remove the exact global
source-fingerprint scans, full network publication checks, saved match-graph
validation or leaderboard discovery. Those still need representative latency
monitoring; a smaller payload is not a production response-time guarantee.

Results navigation also waits for its normal polling interval after fresh server
HTML. Restoring a cached Results view refreshes immediately, without repeating
the fresh page's initial request. `PlayerHistoryProjectionTest`, the existing
history/read-scope/record regressions and the My Tennis navigation browser
harness cover the local behavior.

See [before/after match reconstruction](performance/match-ctr-timeline.md) for its
scheduled-order limitation and [readiness diagnostics](player-plus-readiness.md)
for read-only saved-data and configuration checks.
