# Championship playoffs with a carried lower round robin

## New draws: select, prepare, review and confirm

Before creating later-stage groups or fixtures, choose Round robin → round robin
in Setup and select the top-two playoff / lower round-robin alternative. It uses
exactly two source groups, each with at least three entrants. Equal-position
groups remain the default. Changing this choice after later-stage preparation
is rejected; the choice never resets an existing programme or its bookings.

Before generation, the canonical RR generator and pending hybrid preparation
check the shared fixture-schema prerequisite. This includes
`fixtures.loser_feeder_slot`, provided by
`database/migrations/2026_11_14_100000_add_loser_feeder_slot_to_fixtures.php`.
Missing prerequisites must be reported before deleting or creating competition
records. Transaction rollback alone is insufficient on legacy installations
whose group tables still use a nontransactional engine. After an interrupted
attempt, inspect actual fixtures, groups and marker state; do not blindly retry
or use a broad format reset to remove a partial shell. Migration allowlisting
does not authorize running it on another environment.

`PendingHybridRoundRobinService` prepares stable crossed semifinal, final,
third-place and lower cross-group qualification fixtures. Their group-position
sources can be scheduled before results. Actual players remain unqualified.
Only cross-group lower pairings are created; mutual lower-player results later
remain read-only references to their first-stage fixture records.

For groups of four and five, generation creates 26 distinct fixtures: 16 opening
RR matches, six lower cross-group placeholders and four championship fixtures.
Every later participant remains unresolved. The two semifinals use A1–B2 and
B1–A2 sources; both winners feed the final and both losers feed the third-place
match. Qualifier labels and feeder paths are placeholders, not dummy player or
registration records. Separate single-group RR draws with four and five players
create six and ten matches respectively and have no additional phase fixtures.

The generation preview separates opening matches from later placeholders. After
generation, the authorized read-only hub refreshes all phase groups and the
progression summary together. An unqualified lower group shows its actual saved
fixture numbers and source-position pairings as noninteractive cards at every
width. The positions mean first-stage finishing places, not starting seeds.
Playoff feeder labels refer to the saved semifinal match numbers. Do not create
fake roster entries or enable score entry just to display the placeholders.

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

`HybridGenerationPrerequisiteTest` additionally exercises the normal HTTP
generation route with engine fallback enabled. Its missing-column case asserts
zero competition mutation queries, not merely a successful SQLite rollback;
its success case verifies all 26 fixtures and preserved unresolved sources.

Local verification on 15 November 2026: two HTTP generation tests passed with 75
assertions, and 17 frontend checks passed. Browser and read-only database checks
verified the 26-fixture hybrid and six/ten independent programmes, all ten
unqualified later fixtures, preserved opening membership, zero scores and zero
schedule/publication records. The broader 28-test hybrid bundle had 26 passes
and two failures in a preexisting dirty venue score-button Blade expression;
that unrelated view was not changed by this repair. Later-phase qualification
was not executed in the local rehearsal.
