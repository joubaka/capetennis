# Championship playoffs with a carried lower round robin

## New draws: select, prepare, review and confirm

Before creating later-stage groups or fixtures, choose Round robin → round robin
in Setup and select the top-two playoff / lower round-robin alternative. It uses
exactly two source groups, each with at least three entrants. Equal-position
groups remain the default. Changing this choice after later-stage preparation
is rejected; the choice never resets an existing programme or its bookings.

`PendingHybridRoundRobinService` prepares stable crossed semifinal, final,
third-place and lower cross-group qualification fixtures. Their group-position
sources can be scheduled before results. Actual players remain unqualified.
Only cross-group lower pairings are created; mutual lower-player results later
remain read-only references to their first-stage fixture records.

The last completed first-stage result offers the normal authorized standings
review on both admin and venue scoring screens. Partial sets do not count as
completed outcomes, while valid timed draws and scoreless walkovers do. If ties,
eligibility or malformed topology block qualification, the offer explains the
blocker and opens standings without a progression write. Review and confirm each
group against a fresh snapshot including all three booking stores before filling
the existing slots. Progression is locked, audited and idempotent; no fixtures,
scores or bookings are replaced. Confirmed hybrids suppress further offers and
freeze source results. Only fully unqualified, unplayed pending topology permits
ordinary first-stage score corrections. Prepared playoff fixtures cannot be
scored before confirmation, or while either participant is unresolved.

Generic playoff configuration cannot remove the hybrid marker or replace its
prepared structure. Match scoring settings retain their normal pre-result
editing rules. `PendingHybridProgressionTest` and the shared prompt regressions
cover this new path. This capability does not modify an already confirmed live
hybrid, and it does not run the historical conversion command below.

## Historical completed-source conversion

This conversion applies to an unlocked individual two-stage round-robin draw
with exactly two completed first-stage groups, each containing at least three
players. First-stage standings must be resolved. Any existing second-stage
matches must be unplayed. It is separate from the generic format reset, which
protects recorded results and cannot perform this conversion.

The top two from each source group enter crossed semifinals: A1 versus B2 and
B1 versus A2. Winners meet for positions 1–2; losers meet for positions 3–4.
The remaining players enter one round robin for positions 5 onward. Only their
mutual first-stage matches carry forward. Results against championship qualifiers
do not count in the lower group. Generate only previously unplayed pairings.

For fictional groups of four and five, preserve the original sixteen fixtures,
reference four carried comparisons, and create six new lower-group fixtures plus
four championship fixtures. Never copy played fixture or result records: that
would duplicate rating evidence. Carried matrix cells refer to frozen source
matches; standings and completeness use read-only fixture projections.

### Preview and apply

Verify the deployed schema includes `fixtures.loser_feeder_slot`, which records
the two semifinal losers' destination slots. A testing-only bootstrap definition
does not establish production availability. Deploy the exact reviewed production
migration when missing; do not run unrelated pending migrations. Preview must
reject a missing prerequisite before any competition records are replaced.

Use an existing event administrator or super-user as the audit actor. Preview
the exact event and draw with the canonical command:

```text
php artisan draw:convert-hybrid DRAW_ID --event=EVENT_ID --actor=USER_ID
```

Review the source standings, crossed semifinals, carried fixture references,
replaced fixtures and new match counts. Apply only that unchanged snapshot:

```text
php artisan draw:convert-hybrid DRAW_ID --event=EVENT_ID --actor=USER_ID --apply --snapshot=REVIEWED_SNAPSHOT
```

The service checks authority and event scope, locks the draw and its competition
records, rechecks the fingerprint and records the replaced structure in the draw
audit. It preserves the first-stage fixture identities, scores and memberships.
Obsolete unplayed second-stage matches and their bookings are replaced. New
matches start unscheduled; preserve court availability and player rest when
scheduling them. Scheduling and publication remain separate actions.

Stop on changed standings, changed schedule evidence, an ineligible entrant,
unresolved ties, a locked draw or downstream scoring evidence. Do not bypass a
rejection by deleting results or using the generic reset. Repeating the command
after successful application must not create another programme.

## Owning sources and verification

`app/Services/Draw/HybridRoundRobinService.php` owns conversion and carried
fixture projection. `app/Console/Commands/ConvertHybridRoundRobin.php` exposes
the scoped maintenance operation. Canonical standings, timed standings, matrix
payloads, workflow readiness and final placements consume the hybrid state.

`tests/Feature/Draw/HybridRoundRobinTest.php` is the focused regression. Run it
against an isolated disposable database, then the existing two-stage and relevant
playoff regressions. Report executed checks separately from coverage references.
This guide does not authorize another production conversion or deployment.
