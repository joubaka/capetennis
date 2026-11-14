# Detailed individual round-robin standings

Standings retain the canonical server order and points. Show completed matches
played, wins, draws, losses, remaining opponents, points, sets won/lost and games
won/lost. Percentages use two decimal places so close ratios are visible instead
of appearing tied after integer rounding. Do not sort again using the displayed
rounded percentages.

Each player's expandable match list shows the opponent, score from that player's
perspective and the canonical outcome. Pending fixtures and scores that do not
yet complete the configured match are distinct from wins, losses and draws.
Walkovers and retirements retain their recorded outcome. A recorded set is not
necessarily a completed match. A fixture's legacy status value alone is not
proof of completion.

`RoundRobinStandingsDetailService` supplies this read-only evidence. Shared admin
and public rendering must escape group, player and opponent names and retain
usable horizontal scrolling and keyboard-accessible details on small screens.
Carried hybrid results appear once per destination group, labelled as carried,
without creating another fixture or score row. Standings detail must not expose
unpublished draws, hidden times or contact/account information.

## Blank scoring settings and one-set results

An old draw with no explicit scoring preset or set count uses the legacy
three-set fallback. One saved set can contribute set/game statistics while no
completed match winner exists. Show that incompleteness honestly; do not turn
every historical blank-format draw into a one-set competition.

When the administrator explicitly confirms that the intended format is one full
set, use the bounded preview/apply correction rather than replaying score entry.
Validate every recorded score against the chosen preset, exact event/draw scope,
eligible entrants and current unchanged evidence. Preserve fixture and result
identities, scores, schedules and publication. Reject frozen or progressed
competition, special outcomes and contradictory winner evidence. Reconcile only
the permitted outcome metadata through the correction service and audit the
actor and before/after state. Correction must not resend match notifications or
generate playoffs, fixtures or duplicate rating evidence.

Production correction, publication and deployment require their own current
authorization. Preparing a command locally does not apply it to a live draw.

The canonical operation is `OneSetRoundRobinCorrectionService`, exposed by
`CorrectOneSetRoundRobin`. Use an existing authorized administrator as the actor:

```text
php artisan draw:correct-one-set DRAW_ID --event=EVENT_ID --actor=USER_ID
php artisan draw:correct-one-set DRAW_ID --event=EVENT_ID --actor=USER_ID --apply --snapshot=REVIEWED_SNAPSHOT
```

The first command is read-only; inspect its proposed format and outcomes before
applying the exact unchanged snapshot. A correction is not score-entry replay.
The focused backend regression is `DetailedRoundRobinStandingsTest`. For manual
browser checks using fictional players and the shipped admin/public renderers,
run `node tests/Browser/detailed-standings-preview.cjs --serve` and visit the
printed loopback URL. This fixture does not read or change an application database.
