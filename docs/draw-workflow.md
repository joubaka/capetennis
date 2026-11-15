# Draw workflow and preservation checklist

For the visitor-facing event, draw, schedule and fixture journey, see
[Public draws, schedules and fixture navigation](draw-knowledge/public-workflow.md).

## User journey

The individual draw journey is **Setup → Players → Matches → Schedule → Review & publish → Results**. This is navigation over existing draw operations, not permission to change tournament state. Existing team workflows remain supported.

- Setup contains the competition format, scoring and applicable qualification rules. Explain whether losers stop or continue for finishing places; keep all seven existing format choices.
- Players contains the existing group or bracket editor. Saved player placement and generated matches are different milestones.
- Matches contains match creation and review. Opening a step never generates or resets matches.
- Schedule retains the event planner, saved calendar, selected-day tools and manual operations. Save privately before reviewing publication.
- Review & publish distinguishes draw visibility, saved match times and the public schedule snapshot. Publication remains explicit and scoped.
- Results contains existing scoring, progression and finishing positions. Later-stage qualification may require returning to matches and scheduling; initial-stage readiness is not tournament completion.

Each step should identify the current draw, saved state and next action. Existing deep links and advanced operations remain available. Back and step navigation do not mutate state. A server safeguard takes precedence over a progress indicator.

### Readiness and score revisions

Draw visibility, saved times for generated matches, current published times and
completed results are separate milestones. A visible draw can have private or
partial times. Scheduling every generated match does not prove that unprepared
later stages are scheduled. Explicit format/scoring readiness must not reinterpret
historical blank scoring settings.

Results completion uses canonical match outcomes, including valid round-robin
draws, rather than a winner flag or the presence of a score row. Staged draws also
require the configured finishing paths and confirmed progression; prepared
placeholders do not establish completion. Progress refreshes remain read-only.

Ordinary individual and team score forms submit a revision of the participants,
effective scoring rules and saved result they displayed. Validate it again under
the existing tournament locks before saving or clearing scores. Keep participant
authorization and team participant revisions separate. Missing or stale score
revisions must not write scores, progression or audit entries. Preserve the user's
draft on rejection; never fetch a new revision and silently retry that draft.
Player assignment saves require their reviewed assignment revision. Round-robin
rebuilding additionally requires a separate fingerprint of fixture identities,
results, settings and private/public bookings, so unchanged player groups cannot
authorize destroying a newer saved schedule.

Focused coverage: `DrawPhaseOneReadinessTest`, `DrawScoreRevisionTest`, the
existing score/progression/rebuild regressions and
`tests/Browser/draw-workspace.cjs`. A coverage reference does not establish a
passing run or deployment; report execution evidence separately.

### Selected players and independent category draws

Saving group assignments explicitly selects the players for that draw; it need not include every entrant in the category. Readiness uses an existing canonical draw roster, or the current event/category-bound `groups_saved` selection and assignment revision. Legacy or unsaved partial groups are not assumed to be a deliberate subset. A stale selection, an ineligible player, duplicate player identity or incomplete placement stays incomplete. Readiness never creates a roster or matches. An unchanged older draft must still permit explicit confirmation of its selected players.

For two independent round-robin draws in one category, select disjoint players for each draw. Both saved group assignments and canonical draw rosters reserve those player identities against the other draw; a second registration for the same player is not a different competitor. The available-player list and server save checks must agree. Separate events and explicitly different categories retain their own competition scope.

Round robin → round robin requires at least two first-stage groups in both initial setup and later group editing. The top-four playoffs/lower-round-robin variant requires exactly two. Explain the selected variant consistently on Setup and Players; choosing the hybrid must not describe equal-position qualification.

## Editing contract

| Intent | Existing operation to retain | Required explanation |
| --- | --- | --- |
| Change name or notes | Existing authorised saves | Show saved/error state; retain differences in name and notes permissions. |
| Change scoring or qualification | Existing competition-rule services | Explain locking and the event-wide result freeze where applicable. |
| Change draft players/groups | Existing assignment save with revision | Distinguish unsaved edits from saved placement and match generation. |
| Swap generated players | Existing safe swap service | Preserve its started/result checks and schedule assignments. |
| Reopen starting positions | Existing flexible reopen service | Explain that fixtures are removed; recorded results and existing bookings block reopening. |
| Change format | Existing reviewed format reset | Show actual blockers and removed counts; retain roster; explain which starting placements survive. |
| Move a match | Existing schedule preview/apply | Keep the selected draw/day scope, rest, courts, dependencies and protected bookings. |
| Correct a score | Existing score correction | Retain dependency/reset warnings and stale-revision protection. |
| Publish or hide | Existing draw and day/schedule publication services | State precisely what becomes public or hidden; saving is not publication. |

Do not automatically unlock, unpublish, delete bookings, rebuild fixtures or reset results to satisfy an edit. Show the permitted next action and require the existing review/confirmation.

## Capability inventory

Use this checklist when changing the interface or reviewing a release. Presence in the list is not a claim that a particular test run passed.

| Boundary | Capabilities that must remain reachable |
| --- | --- |
| Formats | Round robin; round robin followed by playoffs; two-stage round robin; knockout; Monrad; feed-in Monrad; custom Monrad. |
| Player setup | Category/event roster, search and assignment, group ordering, seeding, bracket size, explicit byes, system-player addition, custom starting rounds. |
| Match management | Generation, reviewed rebuilding, safe player swaps, alternate replacement, late withdrawal and withdrawal redraw, placement and feed-in paths, optional and rolling challenges. |
| Scheduling | One draw or multiple draws, selected matches/rounds/day, multi-day programmes, venue/court setup, manual moves, add unscheduled matches, edit saved times, explicit broader replanning. |
| Schedule safeguards | Court availability, player rest, fixed/played bookings, playoff dependencies, round-robin group rotation, existing team age/discipline/roster-band rules. |
| Publication | Independent draw and schedule visibility, per-day review/publish/hide, current versus changed public snapshots, public preview, existing notification policy. |
| Tournament play | Result entry and corrections, walkovers/retirements, protected history, qualification/progression, standings and all finishing positions. |
| Utilities | Switching draws, rules/notes, all existing print modes, public view, sharing, event draw list, old route/hash destinations. |
| Access and concurrency | Event-scoped policies, restricted scorers, exact revision checks, no mutation on GET, no blind mutation retries after uncertain responses. |

## Acceptance scenarios

1. A new organiser creates an eight-player Monrad draw, recognises that positions 1–8 will be played, saves players, creates matches, saves a schedule and explicitly publishes the intended scope.
2. Leave and resume at each saved milestone. Refresh, browser Back and old deep links retain a meaningful destination.
3. A saved fully placed draft reports players separately from match generation. Changes after generation do not silently retain an obsolete ready state.
4. Repeat the journey for each format, including byes, custom entry rounds and later-stage qualifiers. Keep later stages visibly conditional.
5. Locked, published, scheduled and scored draws explain the actual blocker. Results elsewhere in the event remain protected where the canonical rule requires it.
6. Cancelled resets, stale saves and failed requests leave state intact. Unsaved edits are guarded when leaving their editor.
7. From the scheduler/calendar, return to the same draw's Schedule destination with the selected date retained. Broader event planning is explicitly labelled.
8. Partial scheduling, private edits and stale public snapshots remain distinct. Saving never publishes or changes another draw/day.
9. Advanced features in the inventory remain reachable and authorised; team workflows retain their existing semantics.
10. Verify keyboard interaction and narrow phone, wide phone, tablet and desktop layouts using local rendered fixtures. Report browser evidence separately from PHP/JavaScript tests and production verification.

## Evidence

The implementation handoff must name the exact tests executed and their outcomes. Relevant existing suites include `DrawSetupTest`, `DrawSetupGuideTest`, `DrawWorkspaceTest`, `CanonicalDrawWorkspaceTest`, `FlexibleMonradTest`, `TwoStageRoundRobinTest`, `SchedulePublicationTest` and scoped schedule-preservation tests. Rendered browser harnesses live under `tests/Browser`.

Check the journey with precompiled Blade views as well as normal rendering, using an isolated `VIEW_COMPILED_PATH`. In the guide and calendar, use paired `@php` / `@endphp` blocks for assignments before later raw PHP blocks. Mixing an earlier inline `@php(...)` with a later block can let the Blade raw-block parser consume intervening directives; successful cache generation alone does not prove that the resulting view renders. The organiser and calendar feature checks must render those cached views.

No migration, deployment, public announcement or live draw reset is authorised by this document.
