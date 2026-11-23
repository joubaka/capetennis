# CTR Certified event planner

Local implementation; this document is not deployment or certification authority.

The event Draws workspace links to a private CTR planner. Only a super-user can
certify or revoke an individual event, with actor/time and platform audit history.
Certification is separate metadata: it does not publish an event, change its type,
make results eligible, or carry into a copied event. Historical Panarottis events
must be identified by exact reviewed IDs; names, current administrators and
organizer text do not prove who created an event. No automatic backfill is provided.

Assigned event administrators and active convenors may plan and confirm only this
event's new private competitions. Score-keeper-only assignments are insufficient.
This narrowly scoped ability does not grant general regeneration or publication.
Only eligible, paid, active single-player entries are projected, excluding players
already assigned in the same category. Existing category and ball-level boundaries
remain intact. Red, Orange and standard CTR use separate validated saved pools;
missing, stale or noncomparable scores are never replaced with zero or another pool.
There is no fitting on page load. Manual placement requires explicit review.

The accepted default is nearby-rated independent round robins, targeting four
players, up to five, with groups of three where necessary. Examples: 6 = 3+3,
7 = 4+3, 9 = 5+4, 10 = 5+5, 11 = 4+4+3. The preferred size can be changed to 3–5.
Fewer than three players in an unresolved field require organizer review and cannot
silently produce an undersized competition. There are no crossover stages.
Each competition allocates one winner and one sportsmanship prize. The planner
counts prizes; it neither awards sportsmanship randomly nor creates financial costs.

Preview writes only an expiring private plan. Changed placements must be reviewed
before confirmation. Apply validates the saved proposal revision, current entries,
rating context, certification, actor/event permissions and existing assignments;
locks scope and creates all draws/groups/fixtures in one transaction. Successful
retries return the existing draw IDs. No existing draw is rebuilt, and no entries,
payments, schedules, publication or mail are changed. Legacy nontransactional draw
tables block apply until separately reviewed. The narrowly scoped compatibility
migration converts only `draw_groups` and `draw_group_registrations` from MyISAM
to InnoDB, preflighting both engines and checking exact row counts afterwards.
Other unsupported engines fail closed. This DDL locks tables and commits
implicitly; it is not transactionally reversible, and down intentionally retains
InnoDB. Review table size and arrange a quiet maintenance window before any
separately authorized live execution. Historical local application is not live
execution authority.

Sources: `CtrDrawPlanner`, `CtrPlannerAccess`, `GroupAssignmentService`,
`RoundRobinGenerationService`, `PlayerAbilitySnapshotStore`, `BallRatingContext`.
Regressions: `tests/Feature/Draw/CtrDrawPlannerTest.php` and
`tests/Browser/ctr-planner.cjs`. Report actual test/browser evidence separately.

Required migrations: `database/migrations/2026_11_23_120000_create_ctr_event_planner.php`
and `database/migrations/2026_11_23_121000_make_ctr_draw_groups_transactional.php`,
explicitly listed in `deploy.config`. Allowlisting never authorizes live execution.
