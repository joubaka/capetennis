# Championship playoffs with a carried lower round robin

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

## Preview and apply

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
