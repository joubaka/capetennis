# Draw workspace audit and improvement plan

Date: 2026-09-03

Status: Implemented locally; not deployed. The audit and proposal below record the original findings and intended scope.

## Implementation and verification

The workspace now has four primary areas with the original nine panels retained underneath them. Player assignment supports search, category filtering, bulk selection, direct add, move, remove, seed ordering, dirty-state protection, stale-save detection, and fixture preview. Shared web/API assignment validation enforces draw ownership and eligible registrations. Group increases retain existing groups; decreases require a destination for affected players and protect existing fixtures/results. Opening the workspace no longer creates groups or fixtures.

The active scripts now own their actions once. Order of Play persists a separate `play_order` without renumbering canonical matches. Scheduling modes, scores, bracket tools, settings previews, rules and all six print choices remain available. Assignment edits do not create or withdraw event entries.

Verification on 2026-09-03:

- Draw feature suite: **250 passed, 1 skipped; 1,235 assertions**. The existing skip is the empty-draw standings case in `P0StabilizationTest`.
- Browser suite: **14 passed**, using real Laravel-rendered test HTML, actual assets and isolated HTTP doubles. This covers assignment, failed-save retention, single requests, generation preview, canonical score names, filtered order persistence, scheduling controls, six populated print documents, navigation and 360/390px layouts. It is not live production browser verification.
- Changed PHP files passed syntax checks; active JavaScript passed syntax checks; route registration, Blade compilation and `git diff --check` passed.
- The sole new migration, `2026_09_03_160000_add_play_order_to_fixtures.php`, was applied to the confirmed local `ct` database. Apply that specific migration during deployment; do not run unrelated pending production migrations.

Browser commands and preview limitations are documented in `tests/Browser/README.md`. Production deployment and production browser verification remain separate. The broader matrix below is a release checklist, not a claim that every permutation received a new browser test.

## Scope and evidence

The target is the round-robin administration workspace shown at `/backend/draw/roundrobin/{draw}`, including its nine tabs, player assignment, settings, playoff configuration, scheduling modal, scoring, printing, and adjacent publication controls.

This audit used the supplied screenshot and current local routes, Blade, JavaScript, controllers, policies, services, and test sources. It did not exercise the live production page or run tests. Findings below are source findings; runtime reproduction belongs to the first implementation phase. Team import and Interpro behavior in this workspace must survive. Dedicated Team Draw v2 and Flexible Monrad pages retain their existing workflows and routing.

## What the audit found

1. **Nine peer tabs mix different jobs.** Matrix, Order of Play, Standings, Brackets, Players & Groups, Settings, Print, Schedule & Venues, and Rules & Notes compete for attention. The default is Matrix even when a draw needs players. Several header layers repeat draw identity and status before the working area.
2. **Setup is split across tabs.** Group count appears in both Settings and Players & Groups. Playoff presets and multiple technical previews sit in a long Settings panel, separated from the resulting brackets.
3. **Player assignment depends on dragging.** There is no player search or bulk assignment control in the active group panel. Changes remain in the browser until Save; regeneration operates on saved server assignments. There is no explicit unsaved-change protection in the inspected page/modules.
4. **Changing group count resets organisation.** `ManageDrawController::recreateGroups()` deletes groups and puts existing players into Group A. The interface warns about this, but it makes routine setup changes expensive and risks existing fixture references. Regeneration deletes RR and playoff fixtures; its impact needs a specific preview.
5. **Some actions have multiple JavaScript owners.** The page loads modular RR scripts, extensive inline scripts, and `draw-roundrobin1.js`. Both the module and legacy script bind Save Groups; modular and inline code bind venue saving and group-count changes. Browser tests must establish and then enforce one request per action.
6. **Refresh paths are fragile.** Inline shims call `RRGroups.refreshGroupsUI()` and `refreshAvailablePlayersUI()`, but the module exports `refresh`, not those functions. The AJAX available-player renderer removes empty category lists, while removal from a group needs an existing source list. Removing a player when everyone is assigned needs a regression case. Initial and refreshed eligibility filtering also differ.
7. **The displayed state has multiple sources.** The header/readiness uses draw registrations while assignment uses group registrations. Verify these counts against representative populated draws. `DrawPolicy` permits scoring on published, unlocked draws, while `DrawMutationPolicy` reports it disallowed. Preserve the functioning round-robin scoring contract and make UI permissions match the server.
8. **Group-save validation needs strengthening.** Both web and API group-save implementations accept submitted group IDs without resolving them through the current draw before deleting/reinserting assignments. Add ownership, event/category eligibility, duplicates-across-groups, and rollback tests before improving the picker.
9. **Viewing a draw can write data.** `RoundRobinController::show()` creates default groups and invokes generation when fixtures are missing. Make creation explicit and test that viewing, refreshing, or switching views cannot change the fixture graph. Preserve convenient first-time setup through an explicit initialise/generate action.
10. **Save Order has an apparent route gap.** `oop.js` posts to `/backend/draw/{id}/save-order`; no corresponding route was found in the route sources. Verify the intended ordering behavior and connect it to an authorised, draw-scoped handler rather than carrying a dead control forward.
11. **Existing tests are useful but insufficient for a UI reorganisation.** Draw authorization, locks, scoring, scheduling, and lifecycle tests exist. `RRHardeningTest::test_admin_can_view_rr_page()` only rejects 401/403 and explicitly permits a 500. Replace that loophole with a representative fixture and successful rendering assertions. Backend tests alone cannot catch missing tabs, duplicate handlers, broken dragging, or printing failures.

## Proposed navigation

Keep one draw workspace with four primary sections. These are freely accessible work areas, not a wizard that forces the admin to repeat earlier steps.

| Section | Contents and retained functionality |
| --- | --- |
| Players & Groups | Eligible player selection, category context, team import where supported, group count, assignment, moves, removals, seed order, save, fixture-generation entry point. |
| Draw & Results | Matrix and score entry as the default view; Standings and Playoff Brackets as local view switches. Retain bracket generation, zoom, scrolling, main/plate/placement paths, and result correction/deletion. |
| Schedule | Combine Order of Play and Schedule & Venues. Keep player/match search, status/court filters, refresh, order saving, score shortcuts, venue/court management, entire-draw/round/match scheduling, automatic scheduling, duration/gap controls, and clearing. |
| Setup & Rules | Match settings, playoff presets and custom configuration, qualification positions, enabled brackets, rules/notes and print toggles. Put accounting, flow, detailed seeding, full seeding matrix, and bracket seed positions under an accessible Advanced previews disclosure. |

Print becomes a persistent labelled action that opens the complete print chooser. Preserve Order of Play, matrix options, populated bracket, empty bracket, combined matrix/fixtures, and the configurable Draw Pack, including its notes, RR fixtures, playoff fixtures, and blank-bracket sections.

Use one compact header for event/draw/category, Switch Draw, Back to Event, status, Print, public-view link where available, and authorised publication/lock actions. Preserve separate draw/schedule publication behavior through the existing services and permissions. Do not equate Published with Locked or infer completion just from those flags.

Show one actionable next-step message, with expandable readiness details. A new empty draw opens Players & Groups; an existing draw can resume its last selected section. Preserve URL/history navigation and resolve old tab links to their new destinations. Switching draws must keep context obvious and protect unsaved work.

## Player-assignment interaction

- Search by player name; filter by source category where multiple categories are allowed. Default category-linked draws to their actual category. Keep registration/player identity visible where names are ambiguous.
- Show eligible, assigned, and unassigned totals from the same server-defined roster. Distinguish no eligible players, no search matches, and all players assigned. Explain exclusions without making unpaid or withdrawn registrations assignable.
- Add checkboxes, Select visible, and Assign selected to group. Offer a direct Add action for one player. Drag-and-drop remains available on desktop; keyboard and touch users can complete the same work without dragging.
- Group cards show live counts and explicit seed/order numbers, with Move, Remove, and reorder controls. Removing from a draw group must not withdraw the event registration or change payment state.
- Keep a persistent Save assignments bar with Unsaved/Saving/Saved/Error states and Discard changes. Retain edits on request failure. Guard reload, navigation, structural changes, and generation when assignments are unsaved. Disable duplicate submissions; detect a stale save if another admin has changed the roster.
- Provide Save & preview fixtures as the primary continuation. Generation must use the successfully saved assignment revision and show group sizes and expected matches before confirmation. For an existing draw, explicitly state the fixtures, playoff graph, and schedule impact; preserve the existing prohibition on regenerating scored fixtures.
- When increasing group count, preserve existing groups and add empty ones. When decreasing it, preview affected players and let the admin choose their destination; do not silently reset all groups. Retain an explicit reset option for admins who want to start again. Validate against existing fixtures/results before committing changes.
- Keep Import from Teams with clear event-wide scope and existing authorization. For a missing entrant, link to the existing event entry workflow and refresh eligible players on return. Any later embedded entry creation must use EntryService and receive separate financial regression coverage.

Automatic balancing or random seeding is not required for the first implementation. Bulk assignment and explicit seed ordering solve the immediate problem without inventing a tournament allocation rule.

## Regression coverage required before release

| Area | Required assertions |
| --- | --- |
| Successful page rendering | Realistic empty, populated, Interpro, published/unlocked, locked, and completed/scored fixtures render successfully. Correct draw-switch destinations, including Flexible Monrad redirects. Every existing control has a documented destination. |
| Access and isolation | Guest, unrelated user/admin, event admin, convenor, and super-user cases. Foreign draw/group/registration/fixture IDs fail without changing either draw. Eligibility is consistent on first load, refresh, search, and save. |
| Assignments | Single/bulk add, move, remove, ordered seeds, category filters, duplicate IDs within/across groups, all-assigned removal, no-player states, persistence after reload, atomic rollback, repeated submissions, stale saves. Exact registration and group-link counts; no payment changes. |
| Group count | Add and remove groups with retained assignments/order, preview cancellation, empty groups, removed-group reassignment, and scored/published/locked guards. Preserve fixture references or require an explicit safe rebuild. |
| Generation | Known even/odd roster scenarios, expected pairings/counts, byes, repeat calls, unsaved assignment protection, empty/single-player groups, and no writes during GET/preview. No loss of existing scores, other draws, or unrelated schedules. |
| Scores and standings | Save/correct/delete from matrix and match list; row orientation, set validation, server standings/ties, published unlocked scoring, locked restrictions, audit events, bracket winner/loser propagation and downstream correction protection. |
| Playoffs | Existing presets/custom names, enabled stages, sizes/qualification positions, accounting and preview outputs, main/plate/placement fixtures, byes and progression links. Preview, zoom, and print must not generate or mutate fixtures. |
| Scheduling | Venue/court add/remove, draw/round/match apply, automatic start/duration/gap, clear scope, persisted order, search/filter/refresh, court/player conflict rejection, and draw/event isolation. Filtering cannot silently drop fixtures from an order save. |
| Publication/locks/notes | Existing role/state transitions, draw versus schedule visibility, live scoring after publication, notes editing where permitted, public-view results, and consistent header/button state after each transition. |
| Print | All six existing print choices plus pack section combinations, matrix options, rules and per-stage notes/toggles. Assert the intended players, fixtures, scores, schedule, and sections; manually inspect pagination and clipping. |
| Browser behavior | One mutation request per click; no JS errors; touch and keyboard assignment; drag/reorder; saved/dirty/error states; failed request retry; reload/back/deep links; modal focus; section switching after edits. Desktop, tablet, and 360/390 px mobile checks with all primary actions reachable. |

Extend the existing DrawManagementRegressionTest, RRHardeningTest, DrawLockHardeningTest, DrawControllerAuthorizationTest, DrawVenueAuthorizationTest, and IndividualScheduleAuthorizationTest. Add focused group-assignment and workspace browser suites rather than relying on text-only Blade assertions. Use deterministic test rosters and verify DB outcomes as well as visible success messages.

## Implementation sequence

1. **Establish the baseline.** Build representative isolated test draws and record the full control-to-route-to-test checklist. Run the current focused draw suites against the confirmed testing database. Record pre-existing failures separately. Reproduce the source findings above; add meaningful failing regressions for confirmed defects.
2. **Stabilise actions and state.** Give each action one JavaScript owner. Resolve group ownership/eligibility, successful rendering, read-only viewing, refresh exports, ordering, and permission consistency. Share the validated assignment behavior across the web/API entry points. Keep generation and progression engines intact.
3. **Reorganise the shell.** Extract the large Blade into workspace partials and move remaining inline behavior into the active modules. Introduce four primary sections, compact context/readiness, and persistent print/publication controls. Maintain old route/link compatibility. Verify the actual scripts served and their build/copy paths.
4. **Improve assignment.** Implement the searchable picker, bulk controls, live counts, accessible moves/reordering, dirty-state protection, safe group resizing, and explicit saved-assignment generation preview. Complete the relevant browser and backend tests before proceeding.
5. **Unify scheduling and finish UX.** Bring scheduling and order-of-play into one area; retain every modal mode and scoring shortcut. Move advanced setup previews behind disclosure. Check all print outputs, roles, draw states, and responsive layouts.
6. **Release verification.** Run focused tests first, then the draw-related regression suites affected by shared changes. Run the complete feature suite if registration/payment behavior is changed. Verify route registration, Blade compilation, `git diff --check`, served assets, and browser workflows. Report automated results, browser results, and any remaining failures separately. Production deployment is a separate action.

## Completion criteria

- Every currently supported action appears in the preservation checklist and works from its new location.
- The admin can select players, assign groups, save, preview/generate fixtures, schedule, publish, score, correct results, inspect standings/brackets, and print without losing context or making duplicate requests.
- Assignment and settings changes do not silently discard work, reset groups, regenerate matches, or affect another event.
- Existing match rules, qualification/seeding behavior, score progression, financial state, and authorisation boundaries remain intact except for explicitly tested defect fixes.
- Tests prove successful outcomes and unchanged record counts where appropriate; a 500 response or a toast alone cannot count as success.
