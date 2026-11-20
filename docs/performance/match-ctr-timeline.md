# Reconstructed CTR before and after a match

CTR means Green/Yellow only, across all ages. Red and Orange results and scores
remain in their own pools and cannot enter this reconstruction or fill missing
CTR values. Old mixed-ball artifacts must be withheld until a compatible rebuild.

The Player Plus match timeline reconstructs Overall CTR immediately before and
after each of the latest five eligible matches in recorded scheduled order. It
does not recover archived ratings: native fixtures do not record verified match
completion timestamps, and their schedule is only a proxy for play order.

The interface must say **Reconstructed using scheduled match order**. Never label
these values as actual recorded before/after ratings. Corrected results and
current publication, identity and rating policy can change a later reconstruction.

## Profile visibility

The authorized selected Plus player's Profile shows a prominent compact timeline
immediately below the shared player header, before the season/month report.
It projects the same saved `PlayerMatchProgress::forPlayer()` data as Rating,
with at most five separate oldest-to-newest pairs on a fixed 0–100 scale.
Before/after values, signed changes, results and unavailable reasons are also
available as readable rows. Narrow screens retain a keyboard-scrollable chart.
The Profile link opens the detailed explanations on Rating; that full timeline
remains available there. Missing, stale or mismatched artifacts never show
zero-valued pairs. Active Plus players see the overnight-update state. An
authorized Super User preview of a player outside the active Plus audience
instead explains that an administrator must generate the graph separately;
waiting for the nightly job will not generate it. Ordinary non-Plus and unrelated
Profile views do not read this private projection. Profile GETs never build, fit
or queue reconstructions.
Verify `MyTennisOverviewTest`, `PlayerMatchProgressTest` and
`tests/Browser/profile-match-progress.cjs` when changing this placement.

## Calculation

For each target match date, load the canonical historical snapshot for that day.
This preserves results that were then inside the 730-day window even if they are
too old for today's snapshot. Use played singles only for Overall CTR.

The before pool contains evidence known to precede the target scheduled time.
Unrelated matches scheduled at exactly the same time are excluded from both
fits: no arbitrary order is assigned to simultaneous play on other courts.
The after pool is that same pool plus the target match. Exclude later dates and
later same-day matches. Fit both pools using the canonical model at the same
target calendar date, preserving score-margin targets, recency and event budgets.

The displayed change is **after minus before**. These are chronological prefix
calculations, not today's rating with a match removed. Day-based recency changes
and comparison-network changes mean match changes must not be treated as fixed
awarded points or assumed to sum to a monthly movement.

The latest five matches share one fixed 0–100 CTR grid. Each before/after pair
has its own arrow, numeric endpoints, signed change, opponent and player-facing
result. Green rises and orange falls supplement text and accessible descriptions;
color alone never conveys the change. Gaps between pairs are deliberate: there
is no connecting line implying continuous progress across different match dates.
Unavailable pairs have no numeric arrow. When every pair is unavailable, Rating
and Profile show compact readable match results and visible reasons instead of
an empty numeric grid. Rating retains each match's explanation control; Profile
opens its result/availability disclosure by default. Mixed availability retains
the fixed-scale chart without inventing numeric points for missing pairs.

Each match can open its saved calculation explanation from the grid. The
explanation separates eligible network evidence from the selected player's own
matches and opponents. It shows the before pool, the one added result and the
after pool, using the same cutoff day on both sides. It does not expose other
players' evidence or fit a model on click. The displayed difference is rounded
after CTR minus rounded before CTR, not a fixed award for the result. Event
budgets are reapplied during each refit, so adding one result can also alter
existing evidence weights.

The explanation's individual-result table is the selected player's own accepted
match evidence, with opponent and score in that player's perspective. Before and
After columns show membership in each fit; the selected result is added only to
After. Earlier displayed results remain in both columns. Wider network evidence
is summarized separately and must not disclose unrelated players' private results.
Resolve details from each target day's validated historical sources, not only
today's latest-month history. Missing, restricted or bounded-out details must be
counted honestly; never invent a score or silently call a partial list complete.
Restricted-source rules apply independently to every evidence row. Individual
details are generated offline with the saved reconstruction, not fetched or
calculated when a match is clicked.

The saved list is bounded to the latest 100 earlier own matches plus the selected
match. The same displayed earlier subset is used for both columns; the model
still fits its complete eligible pool. Omitted, missing and restricted counts
explain any difference between the displayed list and the model's own-match
count. Shared catalog entries are deduplicated across the five explanations and
kept private; browser projections contain no internal source references.

Opponent CTR beside an evidence row belongs to the selected comparison's before
and after fits. It is not today's rating or an archived rating at that older
result's date. Reuse the existing two fits, save each opponent's pair per
comparison, and require comparable converged scores on an unchanged reference
context. Withhold the pair when those checks fail; never substitute zero or a
local disconnected score. An opponent's estimate can change when the target
match is added, just as the selected player's can. Displaying these values does
not turn a result into a fixed points award or isolate opponent strength from
score margins and evidence weighting.

Owning a match record does not by itself authorize the opponent's numeric CTR.
Apply the canonical `PlayerRatingTopTen::forMatchPlayers` public comparison gate
to resolved opponent identities before projecting their saved values. Its current
rating is used only to establish permitted membership, never as a replacement for
the saved reconstruction. Fail closed to unavailable values when permission
cannot be established; private IDs and permission lookup results are not exposed.

## Chronology and availability

Resolve exact canonical source identities to their owning fixture, event and
unique schedule. Require the scheduled date to agree with the evidence date and
the event interval. Order verified same-day matches by scheduled time, never by
fixture ID or record update time.

Unknown ordering or a simultaneous match involving either target participant
makes the pair unavailable. An unrelated simultaneous network match does not:
the strictly earlier baseline excludes it, and the after fit adds only the
selected match. This is a scheduled-cutoff estimate, not actual completion order.
An unscheduled match in an
event that spans the cutoff can represent future evidence even if its proxy date
is earlier; do not silently include it. Unsupported sources must not be assigned
invented timestamps.

Each numeric pair requires a converged, comparable rating on an unchanged
component, reference population, recent backbone and connection status. Otherwise
show an unavailable reason. Scheduled order never proves actual completion order.

## Private saved data and refresh

```powershell
php artisan player-match-progress:refresh PLAYER_ID
```

The [overnight pipeline](../player-plus-nightly-refresh.md) generates these
artifacts automatically for active Plus players after the current rating and
monthly history builds. The command above remains a targeted offline operator
refresh. The
versioned, bounded per-player artifact is tied to its player, current source and
policy, snapshot context and build date. Generation uses a consistent source
read, a lock, freshness revalidation and atomic private replacement. It changes
no source results, ratings, rankings, player links, entitlements or payments.

Version 6 additionally captures a bounded raw [historical-input context](match-graph-historical-context.md).
Future event signups and unreferenced player creation do not invalidate that
context. Historical result, schedule, identity, ball or publication changes still
withhold the graph. Existing version 5 files retain their original strict global
source-fingerprint check; page reads never upgrade or rewrite them.

Ordinary member requests require the existing player relationship and Plus
entitlement; Super User preview remains separate. Page requests only validate and
project that player's saved rows. They never fit a model, write or enqueue work.
Stale or mismatched artifacts are withheld. Nightly work uses bounded, resumable
batches; page requests never start a calculation.

For a missing admin-preview graph, first check the canonical active-player
resolver, private artifact and saved pipeline status. A successful overnight
run does not mean that players outside its entitled audience were processed.
Do not add a membership, grant or account link to repair a preview. A targeted
operator refresh may generate its artifact without changing entitlement. The
builder requires matching current saved ability and monthly history; when source
fingerprints have changed, refresh those prerequisites through their canonical
commands before rebuilding the graph. Production recalculation requires explicit
authorization, and rebuilding the shared base can invalidate other derived
artifacts until they too are rebuilt. Preserve last-good data and all current
publication, identity, chronology and policy checks.

The separate [leave-one-match-out tool](match-influence.md) remains an offline
sensitivity analysis. Its without/with comparison must not be relabelled as a
historical rating change or substituted for this timeline.

## Local regression evidence

The 2026-11-16 cutoff correction reproduced the unrelated simultaneous-match
rejection before the fix. Calculator, saved-progress, chronology-source and
event-progress regressions then passed: 21 tests, 537 assertions on PHP 8.5
with isolated SQLite for feature tests. My Tennis overview and Rating
presentation passed 14 tests and 170 assertions; the existing rating-history
endpoint rendering regression also passed. Independent calculator/presentation
verification passed 9 tests and 294 assertions, and repair-library checks passed
3 tests and 412 assertions. Responsive rendered Profile/Rating checks covered
360, 390, 768 and 1440px, including unavailable/mixed states and keyboard
explanation controls. Route registration and isolated Blade compilation passed.
These are local checks, not deployment or a rebuilt live player artifact.
