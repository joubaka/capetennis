# Draw questions and verified solutions

Paths below are relative to the repository root. Test references are executable coverage, not a claim they all passed in this review. See [verification](verification.md).

## DK-28 — Number new standalone knockout draws by progression round

**Rule:** New Playoffs only, Monrad only, Feed-in Monrad and Custom Monrad fixtures are numbered across progression stages, rather than completing one branch first. Within a stage, main-draw matches come first, then placement sections from highest finishing positions downward, retaining top-to-bottom topology order. Byes create no fixtures or number gaps. Main-round depth prevents a bye from promoting a quarterfinal ahead of remaining opening matches; placement and challenge stages follow their feeder/prerequisite dependencies.

**Scope:** `FlexibleMonradCompiler::fixtureOrder()` supplies numbering order only during new generation in `FlexibleMonradService`. Graph keys, feeder relationships and layout order remain stable. Existing generated fixtures retain their stored numbers. Reopening/regenerating any existing draw is a separate, explicitly scoped operation; this default does not authorize it. Round-robin-derived playoffs use separate generators and retain their current numbering.

**Sources:** `app/Services/Draw/FlexibleMonradCompiler.php`, `app/Services/Draw/FlexibleMonradService.php`, and `app/Http/Controllers/Backend/DrawSetupController.php`.

**Regression:** `tests/Unit/KnockoutMatchNumberingTest.php` passed locally on 4 November 2026: 5 tests, 512 assertions, covering full and bye-containing draws, placement stages, direct entrants and rolling challenge prerequisites. `tests/Feature/Draw/FlexibleMonradTest.php` includes generation/state and existing-number preservation coverage. Test results do not establish deployment or live draw recreation.

## DK-01 — One group finishes before the other starts

**Question:** Can round-robin groups alternate rounds, with larger groups first?

**Rule:** For individual RR scheduling, visit round 1 of each group before round 2. Within a round visit larger groups first; equal sizes use group name. Use full membership even for a partial fixture selection. Smaller groups stop contributing after their final round. Preserve fixed/played assignments, rest, availability and feeders. This does not change team-tie ordering.

**Solution:** `app/Services/Scheduling/EventVenueScheduleService.php::configureRoundRobinGroupRotation()` adds group-turn dependencies using phase, round, full group size and name. Replanning is distinct from changing an already played schedule.

**Regression:** `tests/Feature/Draw/RoundRobinGroupRotationSchedulingTest.php` covers 5 versus 4, four unequal groups, day batches, partial selections and spare courts.

**History:** `01a190a7-897e-7d20-abec-ca1228e55b29` (Check orange ball match duplicates); `01a1910c-30c6-7af3-97a6-0cae7efe2751` (larger-groups-first scheduling request).

## DK-02 — A valid drawn score is rejected or disappears from totals

**Question:** Can an RR match finish 3–3 or 5–5 and share points?

**Rule:** Valid individual RR draws count as completed, with win/draw/loss points 2/1/0. Do not infer incompleteness merely from a null winner. An incomplete or invalid score is not a draw. Timed playoff ties require a separately selected deciding-point winner while retaining the actual tied game score.

**Solution:** `app/Services/IndividualMatchOutcomeService.php` resolves winners and draws; `app/Domain/Draws/Services/StandingsService.php` shares canonical points. Scoring and counters must use these semantics.

**Regression:** `tests/Feature/Draw/TimedDrawScoringTest.php` covers both score routes, all RR formats, idempotency, invalid partials, deciding points and downstream protection. `tests/Feature/Draw/RoundRobinProgressTest.php::test_timed_draw_review_includes_draws_in_played_and_canonical_points()` protects review totals.

**History:** `01a190d0-4597-7241-88ec-2bf2a5b8f8b8` (drawn-score request); `01a19332-4f59-7502-a1b2-8d51dc32879d` (Check page match count); `01a1896b-2cd4-7a83-8924-9115c777af8d` (Audit draw settings). Counter fixes do not create or schedule additional playoff matches.

## DK-03 — Booking duration is confused with scoring format

**Question:** Why does a short scheduled match still reject timed scoring?

**Rule:** A timed court booking does not select a draw's scoring format. Confirm both settings. Playing time, court changeover and player rest are separate constraints.

**Solution:** `app/Services/Scheduling/MatchTimingService.php` resolves timed defaults and saved booking timing. A 15-minute game with a 5-minute court gap reserves 20 court minutes; this does not mean 20 minutes of play or automatically define rest.

**Regression:** `tests/Feature/Draw/TimedMatchSchedulingTest.php`, `tests/Feature/Draw/ScheduleAuditTest.php::test_saved_court_turnaround_is_checked_separately_from_playing_time()`.

**History:** Audit draw settings and the drawn-score request above. The later workflow audit identified timing-preservation and readiness improvements as recommendations; do not claim every recommendation was implemented.

## DK-04 — Playoff slots stay empty after group scores finish

**Question:** Can qualifiers fill already scheduled playoffs without losing their times?

**Rule:** Review every group after all required RR results exist, confirm the current standings snapshot, and resolve source slots in place. Repeated confirmation must not create duplicate fixtures. Reject stale standings, conflicting occupied slots, unauthorized actors and protected downstream results.

**Solution:** `app/Http/Controllers/Backend/RoundRobinController.php::progress()` rechecks the review under lock and resolves existing sources before progression. Preserve fixture IDs, RR scores and bookings.

**Regression:** `tests/Feature/Draw/RoundRobinProgressTest.php` covers in-place Main/Plate resolution, repeated progression, stale snapshots, event authorization, rollback and scored downstream fixtures.

**History:** `01a190e5-d917-7311-87e8-9cc539a602be` (Check Arrensburg playoff progression). Its later turns supersede earlier audit gaps about missing draws/points in review.

## DK-05 — One challenge waits for an unrelated placement match

**Question:** Why does one 2–3 playoff populate while another stays blank?

**Rule:** Rolling challenges wait for the base positions and earlier challenge stages that actually feed them. A lower placement match outside those sources must not block them. Original fixed challenge compilation and rolling challenge normalization have different gates; do not remove all prerequisites globally.

**Solution:** `app/Services/Draw/FlexibleMonradCompiler.php::withRollingChallenges()` derives prerequisites by following position sources. `FlexibleMonradProgression.php` resolves those dependencies independently of serialized key order. Historical live repairs are not a reusable data-update script.

**Regression:** `tests/Unit/FlexibleMonradRollingChallengesTest.php` checks relevant versus unrelated blockers and legacy gates; `tests/Feature/Draw/FlexibleMonradChallengeTest.php` covers scheduling rolling challenges without lower unplayed positions.

**History:** `01a19636-d1a7-7fa3-8881-a85d0c591379` (Check girls playoff positions); `01a17c41-558c-7d42-af02-378f44192c05` (earlier challenge/scoring work).

## DK-06 — Byes, withdrawals and pending players look alike

**Question:** Can a full bracket show bye branches without inventing results or advancing unknown players?

**Rule:** Display-only bye branches must not create canonical fixtures or scores. An unresolved feeder is not a vacancy; a withdrawal must not erase played evidence. Retrying or JSON key reordering must produce the same resolved state.

**Feed-in presentation:** For the fixed 16/32-slot feed-in templates, restore collapsed bye paths as unnumbered display cards and connect their surviving source to the next round. Keep the original feeder reference for labels; a pending loser remains pending until the feeder result is known. Only project a template when its source signatures agree with the stored graph, leaving incompatible historical graphs intact. Presentation does not renumber matches, add schedule rows or regenerate draws.

**Presentation sources and evidence:** `public/js/tennis-bracket-layout.js::withFeedInByes()` and `app/Services/Draw/FlexibleMonradFeedInDisplay.php` project the same cards for browser/print and PDF. On 4 November 2026 the focused PDF/display/repair-library unit bundle passed 10 tests, 288 assertions; the layout/print/challenge/frontend bundle passed 18 tests, including PHP/JS projection parity for 16/32-slot full, bye-containing and sparse fields. A fictional 15-player local browser preview showed the missing opening feed-in bye connected to the next round. Production and narrow-phone rendering were not verified.

**Solution:** `app/Services/Draw/FlexibleMonradCompiler.php`, `FlexibleMonradProgression.php` and the format-specific presentation layer keep topology, resolved state and display separate.

**Regression:** `tests/Unit/FlexibleMonradCompilerTest.php`; new `tests/Unit/DrawKnowledgeRegressionTest.php::test_json_reordered_graph_keeps_a_pending_qualifier_distinct_from_a_withdrawal_walkover()`.

**History:** `01a181b4-2e14-7d80-8132-399df9534928` (Fix draft draw fixture error).

## DK-07 — Optional challenges accidentally repeat a played pairing

**Question:** Can optional placement challenges avoid rematches?

**Rule:** A previously played pairing suppresses a duplicate optional challenge regardless of side order. A walkover is not a played meeting for this rule. Rolling stages follow current ranks after upsets and may skip an unchanged repeat.

**Solution:** `app/Services/Draw/FlexibleMonradProgression.php` checks played-pair evidence and challenge state; compiler options define supported stages.

**Regression:** `tests/Unit/FlexibleMonradChallengesTest.php`, `tests/Unit/FlexibleMonradRollingChallengesTest.php`; new `DrawKnowledgeRegressionTest::test_a_prior_walkover_does_not_suppress_a_challenge_as_a_played_rematch()`.

**History:** `01a17c41-558c-7d42-af02-378f44192c05`.

## DK-08 — Generate a second draw without carrying over the first

**Question theme:** Repeated setup/generation must preserve each draw's participants and topology.

**Rule:** Reusing a compiler across different formats, field sizes and sparse participant IDs must produce the same second graph as a fresh compiler. Byes and optional challenges from the first draw cannot leak into the next.

**Solution:** `app/Services/Draw/FlexibleMonradCompiler.php::compile()` resets compilation state.

**Regression:** New `DrawKnowledgeRegressionTest::test_reusing_a_compiler_for_another_field_does_not_leak_entrants_or_placement_matches()` combines existing generation/bye rules. This is a new defensive example, not a claim that a historical chat diagnosed a compiler leak.

**History context:** Fix draft draw fixture error and earlier draw generation discussions.

## DK-09 — All players need playoffs, including uneven large fields

**Question:** Why is the playoff preset limited to a small field?

**Rule:** The all-players preset must give every assigned RR player exactly one opening playoff slot, including larger and uneven fields. Do not multiply a position by group count when that position is absent from a smaller group.

**Solution:** Current preset/setup code and dynamic playoff generation in `RoundRobinController.php` use saved group membership and applicable qualifying positions.

**Regression:** `tests/Feature/Draw/AllPlayersPlayoffGenerationTest.php::test_every_assigned_player_gets_one_opening_playoff_slot_for_large_and_uneven_fields()`.

**History:** `01a18dc4-d7a0-7903-b6c0-1cb18abeb6b4` (Option to add walkover for match; earlier turns contain the all-player request); Audit draw settings.

## DK-10 — Round robin can progress to another round robin

**Question:** Can nine players play three groups, then regroup firsts, seconds and thirds?

**Rule:** Three groups of three progress into three position-based groups: places 1–3, 4–6 and 7–9. With no withdrawals this yields 18 matches and four matches per player. Stage one freezes after progression; stage two respects phase barriers, rest, stale review and unresolved ties.

**Solution:** `app/Services/Draw/TwoStageRoundRobinService.php` manages this separate workflow.

**Regression:** `tests/Feature/Draw/TwoStageRoundRobinTest.php` covers the nine-player journey, idempotency, authorization, scoring, final positions, public/print data and scheduling.

**History:** `01a18c09-b52c-7273-b871-f73d58417932` (Add two-round-robin group option).

## DK-11 — Separate group winners within one category

**Question:** Can groups have their own winners without creating separate events?

**Rule:** Separate group competitions remain within the same category. Confirm a group winner only when its required matches are complete and its leading tie is resolved. Before results exist, a compatible competition choice preserves unplayed groups, fixtures and schedules. Recorded tournament results freeze format/group-rule changes; reject the change while preserving scores and bookings.

**Solution:** `app/Services/Draw/RoundRobinGroupResultService.php` annotates completion and winner state using canonical standings.

**Regression:** `tests/Unit/RoundRobinGroupResultTest.php`, `tests/Feature/Draw/RoundRobinGroupCompetitionTest.php`.

**History:** `01a18b59-ec8f-7633-9dea-a383ce06fa28` (Clarify same-category draw setup). This is different from multiple independently named draws in a category.

## DK-12 — A walkover should advance players without fake game scores

**Question:** Can an organizer record a walkover?

**Rule:** A walkover identifies an actual participant as winner, advances required winner/loser paths once and does not invent set scores. RR counts the win; cross-event fixtures and unauthorized users remain rejected. Protected downstream results block unsafe corrections.

**Solution:** Existing match outcome and canonical fixture progression services retain the result kind rather than fabricating games.

**Regression:** `tests/Feature/Draw/MatchWalkoverTest.php`.

**History:** Option to add walkover for match (`01a18dc4-d7a0-7903-b6c0-1cb18abeb6b4`).

## DK-13 — Working changes must not silently replace public times

**Question:** Can the main draw hub show partial publication and publish later edits clearly?

**Rule:** Working schedules and public snapshots are distinct. Preserve existing public times until explicit publication/unpublication. Reject stale revisions and foreign draw IDs before mutation. A draw being public does not mean every time is published or current.

**Solution:** `app/Services/Scheduling/SchedulePublicationService.php` and hub projections represent partial, changed and preview-only states.

The one-day match selector remains the default scheduling screen. The optional **Plan rounds across days** view reuses the supported programme planner: dragging rounds changes the proposed day/order and invalidates old suggestions; **Generate suggested schedule** explicitly requests times and courts. Saving and publication remain separate. Fixed-day entry links open the unscoped workspace for this view. See `tests/Frontend/schedule-day-box-view.test.cjs` and `schedule-programme-drag.test.cjs`; browser appearance is not established by these tests.

Player Plus publication notices use a five-minute quiet period after the latest meaningful public change. Compare normalized public content, not the publication timestamps rewritten on identical republishing. See [publication notifications](../player-plus-publication-notifications.md) and `tests/Feature/Draw/PlusPublicationNotificationTest.php` for audience, backlog and delivery safeguards; delivery remains disabled by default.

**Regression:** `tests/Feature/Draw/DrawHubTest.php`, `SchedulePublicationTest.php`, `TrialPublicDrawSharingTest.php`.

**History:** `01a1898e-7195-7fd2-afc8-59ce13583717` (Audit main draw workflow); `01a17cf5-865d-76d1-b315-88d70f9caa25` (Match draw publishing to team events).

## DK-14 — Format changes need a clear setup path

**Question:** Can required setup become more prominent after a format change?

**Rule:** Resume required format-specific setup without silently rebuilding saved fixtures or resetting settings. Keep the main hub as the return point; preserve separate setup, scheduling and publication steps.

**Solution:** Current setup-guide flow and format-specific workspaces surface scoped next actions and return links.

**Regression:** `tests/Feature/Draw/DrawSetupGuideTest.php`, `DrawHubTest.php`. Historical browser verification of the prominence change was blocked; source/test references do not establish fresh visual acceptance.

**History:** `01a18c08-6fee-7a60-bb27-b9fa567b04fd` (Preserve setup after draw changes); `01a1823a-d4cb-7821-b456-fc1fa1c05f1b` (Test match scheduling workflow).

## DK-15 — Rebuilding unplayed groups leaves ghost court bookings

**Question theme:** Saved groups differ from generated fixtures, and obsolete reservations block replanning.

**Rule:** Use the scoped rebuild service for permitted unplayed rebuilds; remove obsolete bookings transactionally, preserve other draws, reject stale revisions and protect published/played evidence. A historical targeted repair must not become a blanket delete command.

**Solution:** `app/Services/Draw/RoundRobinRebuildService.php`; schedule audit reports orphan reservations without modifying them.

**Regression:** `tests/Feature/Draw/RoundRobinRebuildSafetyTest.php`, `ScheduleAuditTest.php`.

**History:** Earlier turns of Option to add walkover for match; scheduled playoff investigations in Check Arrensburg playoff progression.

## DK-16 — Repeated match numbers do not prove duplicate fixtures

**Question:** Why do two draws show the same match numbers and qualifier labels?

**Rule:** Match numbers and source labels may restart per draw. Diagnose duplication by fixture identity, draw, participants and court/time, rather than by label alone. Use the exact working/public/print view in question.

**Solution:** Venue projections retain draw context; public ordering and schedule audit check distinct fixtures/resources.

**Regression:** `tests/Feature/Draw/PublicDrawOrderingTest.php`, `ScheduleAuditTest.php`. These cover ordering and resource integrity; they are not a claim that every repeated-label presentation has a dedicated regression.

**History:** Check orange ball match duplicates. That investigation found distinct fixtures, so no generic deduplication rewrite is justified.

## DK-17 — Saved valid timed scores can have stale completion metadata

**Question:** Why are valid scored matches still marked in progress?

**Rule:** Explicitly submitting an identical final timed RR score can normalize proven stale completion metadata without altering score rows, playoff participants or times. This is narrowly validated, locked and idempotent; it must not become an automatic bulk finalizer.

**Solution:** `app/Services/Draw/TimedRoundRobinCompletionService.php::finalizeIdentical()`.

**Regression:** `tests/Feature/Draw/TimedDrawScoringTest.php::test_identical_explicit_timed_final_submission_repairs_only_stale_metadata_after_playoffs_exist()`.

**History:** `01a19346-e38a-7b83-a2bc-64e965b7c334` (Find why today’s matches are missing). The referenced test and API controller already had unrelated local edits when this review began; this task does not claim ownership or deployment of them. Rating-model policy is outside this draw library.

## DK-18 — An empty private draft opens a broken fixture page

**Rule and solution:** `app/Http/Controllers/Backend/FlexibleMonradController.php::publicShow()` returns an authorized draft owner to the workspace when no graph exists, without generating fixtures or publishing anything. Guests and unrelated admins do not gain draft access.

**Regression:** `tests/Feature/Draw/CanonicalDrawWorkspaceTest.php::test_saved_draft_without_fixtures_returns_its_admin_to_the_workspace()` and its empty-record companion check privacy, no-store and unchanged state. Display-only bye branches are separately tested in `tests/Unit/tennis-bracket-layout.test.cjs`.

**History:** Fix draft draw fixture error (`01a181b4-2e14-7d80-8132-399df9534928`). Earlier tests expecting a universal 404 for authorized empty-draft access may be obsolete; inspect the intended audience before changing that expectation.

## DK-19 — Moving a match must not rearrange the whole preview

**Rule and solution:** A manual move changes the selected match, leaves its previous slot vacant and preserves other suggestions and unallocated matches. `app/Services/Scheduling/EventVenueScheduleService.php` enforces court, rest, feeder and optional daily-player limits on manual as well as automatic planning. Conflict responses must not disclose private foreign match identifiers.

**Regression:** `tests/Feature/Draw/ScheduleDayBatchTest.php`, `ScheduleMoveConflictTest.php`, `ScheduleDailyLimitTest.php`.

**History:** Review draw names (`01a17cfa-5e00-7d73-87e3-c6a78e15f15f`; latest ten turns reviewed), Audit U/19 interpro draw feed (`01a17d50-faa1-7a50-867a-86aad32ed99d`). Historical duration/rest defaults are configurable, not universal constants.

## DK-20 — A delayed challenge stalls independent matches

**Rule and solution:** Feed-in scheduling uses dependency depth, not local round numbers or template labels. Independent sibling branches may overlap when players and courts are free. A fixed unresolved challenge cannot introduce an unrelated delay; other draws' court reservations remain protected.

**Regression:** `tests/Feature/Draw/FeedInSchedulingRegressionTest.php`, `ScheduleDailyLimitTest.php`. Compiler tests verify complete placements for fields with byes; a full 16-player feed-in has 36 base matches, and a full 32-player feed-in has 92, excluding optional challenges.

**Sources:** `app/Services/Scheduling/EventVenueScheduleService.php::wave()`, `app/Services/Draw/FlexibleMonradCompiler.php`.

**16/32-slot feed-in topology:** New generation follows the fixed template for either size, independent of age group: pair adjacent opening-round losers, then feed second-round losers in reverse main-draw order. In size 32, pair adjacent survivors before feeding quarterfinal losers in main-draw order. Preserve empty branch positions through each crossing; a bye advances the other source without creating a fixture or a loser. Packing only actual losses prematurely can put both direct advances in one branch. Existing saved graphs retain their structure until explicitly reopened and regenerated through the guarded service; never rebuild published or scored draws to adopt this rule. `tests/Unit/FlexibleMonradThirtyTwoTemplateTest.php` verifies a sparse 22-player field (55 matches), separated direct advances, and complete unique positions for fields of 2–32 players. `tests/Unit/FlexibleMonradSixteenTemplateTest.php` verifies sparse 16-slot branch placement, all field counts of 2–16, and semifinal-loser third-place paths.

**History:** Audit U/19 interpro draw feed; Set up U19 event test (`01a17c41-558c-7d42-af02-378f44192c05`). Compare formats by participants and feeder relationships, not spreadsheet match numbers.

## DK-21 — Multiple independent draws in one category

**Rule and solution:** Category draw creation can create multiple independent empty draws. It must not populate players, merge winners or publish those draws. Validate the category belongs to the authorized event. This differs from DK-11's separate winners for groups within one draw.

**Sources:** `app/Http/Controllers/Backend/EventAdminController.php`, `resources/views/backend/headOffice/individual-event-show.blade.php`.

**Regression:** `tests/Feature/HeadOffice/HeadOfficeDrawAuthorizationTest.php` covers two-draw creation, names, counts and foreign category rejection.

**History:** Add multiple draws per category (`01a18965-eaa1-7241-8c14-70dc51bf8908`).

## DK-22 — Choosing a format must not silently destroy setup

**Rule and solution:** Initial format choice is read-only until saved; RR asks for groups before placing players and suggests matching playoffs. Resets need explicit confirmation and preserve other draws and roster data; played results and published schedules remain protected.

**Sources:** Current draw setup controller/service flow; `tests/Feature/Draw/DrawSetupTest.php` first four tests specify read-only selection, groups-first setup, suggestions and safe confirmed resets.

**History:** Fix format setup workflow (`01a18c74-9112-7dd2-9b6a-f6d07c986272`). Its remaining mobile badge/wrapping problems and unreproduced PWA freeze are not verified solutions.

## DK-23 — Editing timeline targets should retain bookings

**Rule and solution:** `public/js/schedule-timeline.js` validates time windows, merges duplicate target timestamps, preserves original bookings, supports cancellation and rejects stale editing contexts.

**Regression:** `tests/Frontend/schedule-timeline.test.cjs` covers these five cases.

**History:** Add timeline editing option (`01a182cf-0cf2-7400-a4dc-fa47393dad03`).

## DK-24 — Printed placeholders and scores must reflect actual state

**Rule and solution:** Unplayed result fields remain blank for handwriting. Resolved participant names override feeder placeholders; unresolved sources retain their real winner/loser match references. Reject foreign-draw label mappings and collapse bye sources safely.

**Sources:** `app/Services/Draw/FlexibleMonradPdfLayout.php`, `app/Services/Scoring/FixtureParticipantLabels.php`.

**Regression:** `tests/Unit/FlexibleMonradPrintResultsTest.php`, `FixtureParticipantLabelsTest.php`.

**History:** Compare U19 interprovincial draws (`01a1892e-a6dd-7272-9e7c-a96833101fa8`). Emailing an order of play remains a separate explicitly confirmed managed-mail workflow; a print preview does not authorize a send.

## DK-25 — Venue scoring should open the fixture queue

**Rule and solution:** The venue scoring workspace presents the fixture queue instead of a round-robin matrix while retaining venue and draw restrictions.

**Sources:** `app/Http/Controllers/Frontend/VenueScoringController.php` and scoring workspace Blade.

**Regression:** `tests/Feature/TeamDraw/TeamRoundRobinMatrixTest.php::test_workspace_always_shows_fixture_queue_without_round_robin_matrix()` and venue-restricted scorer coverage.

**History:** Untitled matrix-removal chat (`01a167ec-1348-76f1-9e6b-5a7ae3afa533`). Tests were deliberately skipped for that historical push; this is not fresh test evidence.

## DK-26 — Prepare team draws while registration remains open

**Rule and solution:** Creating team draws does not close registration; the organiser switch and closing date remain authoritative. New roster claims reconcile upcoming source-aware fixtures through `TeamDrawAdaptationService`. During open registration for non-Trials team events, authorised playing-order saves run automatically through `RosterOrderProtectionService`, including the ordinary team roster controls. They retain the same rollback checks for results, started participants, bookings, publication and unrelated teams. Closed registration keeps the existing preview/confirmation workflow.

Name corrections refresh the display name in eligible upcoming participant snapshots under the event lock, retaining source identity and profile-link attestations. Imported names remain canonical until a roster claim resolves the fixture to a profile. The latest corrected snapshot then stays frozen when play starts or results exist; stale scoring revisions are rejected. Locked draws, placement drafts and fixtures without verifiable source teams are not silently repaired. New ties and structural changes retain their separate scheduling/review/publication requirements.

**Sources:** `app/Services/TeamParticipantHistoryService.php`, `TeamDrawAdaptationService.php`, `TeamRosterService.php`, `TeamSelection/RosterOrderProtectionService.php`, `TeamSelection/ImportedTeamRosterService.php`, and `app/Models/Player.php`.

**Regression:** `tests/Feature/TeamDraw/AutomaticUpcomingFixtureUpdatesTest.php` and `TeamDrawAdaptationTest.php` passed locally on 2 November 2026: 28 tests, 155 assertions, isolated SQLite. This does not establish deployment or live event configuration.

## DK-27 — Choose opening regional encounters and player counts during team draw creation

**Rule and solution:** In category-selected team draws, preview the ties in Match setup, then choose an opening round by its actual regional encounters. Rotate complete rounds, preserving all pairings, byes and home/away assignments. Save the chosen opening round and source-team order so deferred guided generation matches the preview. Bulk draws retain independent opening choices by default. The optional apply-to-all checkbox copies subsequent opening-pairing and tie-order changes (dragging or move buttons) across the selected creation batch by region identity, including byes. Destination draws retain their own team-pair keys and source-round numbers; incompatible or ambiguous regional pairings are skipped with feedback. Existing saved draws are unchanged.

Open a round to move its ties earlier or later for scheduling. Save each edited original round as an exact permutation of its server-resolved team-pair keys; duplicates, missing ties, foreign pairs and pairs from another round are rejected atomically. Opening-round rotation retains these choices, and deferred generation assigns the chosen `tie_nr` to ties and rubbers. Scheduling uses the preference only as its final fallback after availability, rest, existing priorities and rubber sequence. Reassign the opted-in cohort's existing fallback-key slots rather than combining tie numbers into `play_order` or comparing ties conditionally across draws; this preserves stable ordering with competing draws and fixture IDs of different lengths. Pair-key lookup retains original preferences when roster adaptation appends new rounds. Existing played or fixed schedules are not changed by creation controls.

An optional players-per-team count uses the first 1–12 roster positions per source team, without changing team roster settings. Singles create that many rubbers; doubles create half as many and reverse singles require an even count. Mixed doubles use the count separately for boys and girls (six means six boys plus six girls), retaining the existing alternating source positions. Explicit counts use roster positions instead of the event default format; combining a count with an explicitly selected tie format is rejected. Later roster refreshes retain the explicit rubber count. Blank counts preserve automatic behaviour.

**Sources:** `app/Services/TeamDrawSelectionService.php`, `TeamDrawGenerationService.php`, `TeamDrawReadinessService.php`, `TeamDrawAdaptationService.php`, `app/Services/Scheduling/UnifiedTeamScheduleService.php`, `EventVenueScheduleService.php`, `public/js/team-draw-mode.js`, and the head-office team-event view.

**Regression:** The focused selection/readiness/adaptation/scheduling/generation/ordering bundle passed locally on 3 November 2026: 88 tests, 808 assertions, isolated MySQL with transactional rollback. This includes the corrected appended-round fixture, competing-draw scheduling with fixture IDs across a lexical boundary, and `tests/Unit/Services/TeamOpeningRoundScheduleTest.php` (4 tests, 205 assertions) covering even/odd fields, exact permutations, default priority and stable fallback ordering. The three `tests/Frontend/team-draw-*.test.cjs` files passed 35 tests, including repeat preview, independent batch tie choices and count payloads. These checks do not establish browser acceptance, deployment or live event changes.

## Recommendations that remain separate from established rules

The broad workflow audit in the drawn-score chat proposed explicit scoring readiness before publication, combined public-calendar clash checks for selective publication, read-only scheduling previews after explicit playoff preparation, and stronger timing-override preservation. These are investigation candidates, not assertions that all paths already enforce them. Recheck current services and add a failing behavioural example before implementation.
