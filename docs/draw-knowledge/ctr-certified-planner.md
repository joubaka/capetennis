# CTR Certified event planner

Local implementation; this document is not deployment or certification authority.

The event Draws workspace links to a private CTR planner. Only a super-user can
certify or revoke an individual event, with actor/time and platform audit history.
Certification is separate metadata: it does not publish an event, change its type,
make results eligible, or carry into a copied event. Historical Panarottis events
must be identified by exact reviewed IDs; names, current administrators and
organizer text do not prove who created an event. No automatic backfill is provided.

Shared/public event badges treat missing certification schema as uncertified,
with one schema-readiness check per request rather than one per rendered event.
Planner actions require both certification and saved-plan tables and return a
clear setup-pending response when the migration is absent or incomplete.

Assigned event administrators and active convenors may plan and confirm only this
event's new private competitions. Score-keeper-only assignments are insufficient.
This narrowly scoped ability does not grant general regeneration or publication.
Only eligible, paid, active single-player entries are projected, excluding players
already assigned in the same category. Existing category and ball-level boundaries
remain intact. Red, Orange and standard CTR use separate validated saved pools;
missing, stale or noncomparable scores are never replaced with zero or another pool.
There is no fitting on page load. Unrated and unavailable ratings remain null;
those players start at the bottom of their category rather than disappearing
into an unassigned list. This is a starting placement, not an ability judgment.
Within one valid comparison scale, order comparable CTR descending and append
unknown scores deterministically. Multiple comparison scales stay in separate
labelled bands; never rank their numbers against each other. A deliberate manual
group or linked competition spanning those bands requires explicit acknowledgment. Missing rating alone
requires no acknowledgment. Invalid singles identity or unknown ball context
remains a creation blocker.

The preferred group size defaults to four and can be changed to 3–5. Groups must
contain 3–5 players. Defaults: five stays one group; seven is 4+3; nine is 5+4;
eleven is 4+4+3. Six is one competition with two groups of three and three
straight-over finals: A1–B1 for the winner, A2–B2 for places 3–4, A3–B3 for
places 5–6. This creates six RR matches plus three unqualified final placeholders
through the existing canonical playoff preparation. Ten defaults to an independent
group of four followed by that linked six-player competition: three groups
4+3+3, two draws, fifteen matches. An explicit preferred size of five offers
5+5 instead. Other fields are not silently converted to linked competitions.
The saved proposal records exact straight-over block group numbers.

Version 3 new previews seed a comparable six-player block in serpentine order:
descending usable CTR ranks 1,4,5 in A and 2,3,6 in B. In default ten, the top four
remain in the independent draw; the remaining six use that same local order
(global ranks 5,8,9 and 6,7,10). Unknown scores remain null and follow known scores
in deterministic order, without pretending to be measured ranks. Equal scores
use the stable player identity tie-break. Separate comparison scales are never
numerically ranked together: retain their bands and require explicit review for
any linked competition crossing them. This rule runs only when preparing a new
preview; later manual/AJAX placements are not automatically reseeded.

Aim for three matches per player, with up to four: four-player RR gives three,
five gives four, linked 3+3 gives three. An independent three-player RR gives
only two and must be explicitly acknowledged as a below-target exception before
creation, or rebalanced/excluded. No rematches are invented. Twenty default
players form five groups of four, thirty matches in total.

Each competition allocates one winner and one sportsmanship prize; linked six
groups share one pair. Thus six has two prizes and ten has four prizes, not six.
The planner counts prizes; it neither awards sportsmanship randomly nor creates
financial costs. A category with only one or two players stays visible with
“Not enough players.” The organizer may explicitly exclude any exact category
from this saved draw plan, then include it again before creation. Excluded
players remain registered and their payment state is untouched. All-excluded
plans cannot create an empty programme. Source fingerprints still cover every
original source row, including excluded categories, and creation audits record
the reviewed exclusions.

The group board appears in numeric group order, with comparable CTR descending
and unknowns last within each group. Same-category drag/drop and labelled
Move-to selects/buttons share exact entry-ID placement fields; native selects
also allow a server-reviewed no-JavaScript fallback. Adding/removing empty groups
never removes players. Counts, match totals and prizes update locally, while
invalid sizes, missing shortfall acknowledgment or mixed-band review block
saving. Version 3 source hashes invalidate version 1/2 previews without rewriting
them. Existing applied plans retain their created-draw receipts.

Exclusions, moves and acknowledgments autosave in place through the canonical
review POST after a 400ms debounce. Invalid intermediate boards remain visibly
unsaved and do not send requests. A successful private JSON receipt supplies the
exact saved revision, readiness, exclusions and totals; both forms use that new
revision. This saves only the private plan, never draws. Confirmation remains an
explicit separate token/revision POST. No-JavaScript form submission still uses
the normal POST/redirect review flow.

Only one save is in flight. Edits arriving during it queue the latest valid board
using the last accepted server revision. An older acknowledgment must not clear
newer dirty state, replace membership or enable creation. DOM focus, scroll and
groups stay in place. A 422 validation response saves nothing and permits corrected
edits or explicit retry. Network loss, timeout, malformed/login response, conflict,
session/permission failure, rate limit or uncertain server outcome stop automatic
writes and require explicit recovery by reloading the authoritative saved plan;
never blindly retry a possibly committed revision. A request is not aborted when
new edits arrive. Changes stay unsaved and Create stays disabled until the latest
valid board has an accepted receipt. Client readiness never bypasses stale-source,
identity, duplicate-player, shortfall or comparison-band checks. Current included
group reasons replace obsolete blockers from excluded underfilled categories.

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
