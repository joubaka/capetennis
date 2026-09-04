# Draw and dashboard integration audit

Date: 2026-09-03. Scope: event dashboards, draw navigation, Custom Monrad / Monrad / playoffs editor, players, fixtures, scheduling, publication, final results and exports. Findings refer to the current local working tree, including existing uncommitted work. Production was not changed or retested during this audit.

## Overall finding

The new bracket engine integrates with the existing individual fixture and scheduling records, but the surrounding application does not yet provide one coherent draw workspace. Several dashboard and results screens still assume the older workflow. Returning from the editor does not restore the full tabbed workspace.

## Current connections

| Surface | Current connection | Practical limitation |
|---|---|---|
| Event dashboard / draw cards | Opens different settings, players, engine and draw routes | Actions differ between dashboard variants |
| Normal draw / round-robin hub | Redirects flexible draws to the standalone editor | Schedule, setup, rules, event navigation and draw switching are lost from that workspace |
| Editor → Manage draw | Opens `draws.manage` | Only Settings and Players tabs; not the full draw hub |
| Editor → generation | Writes `fixtures` with stage `FM`, graph and fixture mapping; synchronizes draw registrations | Saved draft placements are not yet the generated draw roster |
| Editor → scores | Writes normal fixture results and resolves winner/loser paths | Legacy score endpoints intentionally reject this format |
| Schedule | Individual scheduler delegates to FlexibleMonradScheduler and shared availability checks | Editor has no schedule link; trials events select the wrong scheduling page |
| Public draw | Public routes redirect to the published flexible view | Flexible view does not expose the timetable |
| Final positions | Computed by FlexibleMonradProgression | No handoff into category_results, which event results and rankings consume |
| Printing | Editor has its own print path; event dashboard has a separate export | Event PDF stage allowlist omits FM |

## Findings, ordered by priority

### 1. P1 — Final-results writes lack event authorization and relationship checks

`EventCategoryResultController::store` validates the shape and duplicate position numbers, then deletes/replaces results. It does not authorize event management, verify that the supplied category belongs to the event, or verify registration membership in that category. The registered route has authentication but no event permission middleware. An authenticated account can reach this write path without the event-specific authorization required by the draw editor.

The withdrawal filter also excludes only the exact `withdrawn` state, unlike the editor's full inactive-state and timestamp checks.

Evidence: `app/Http/Controllers/Backend/EventCategoryResultController.php:17`, `:46`, `:63`; expanded middleware from `php artisan route:list --name=admin.events.categories.results.store -vv`.

Required fix: authorize event management; resolve category through the event; validate distinct registrations against active category membership; preserve atomic replacement. Add authorization and cross-event/category regression cases.

### 2. P1 — Completed draw positions do not reach event results or rankings

The flexible service returns computed positions to its editor/public view. The event results screen instead reads `category_results` and falls back to an unsaved registration ordering. Its Save action submits the displayed order. The ranking calculation reads category results, with no flexible graph adapter in this path. Completing all draw matches therefore does not populate authoritative event results; saving the event results screen can persist an order unrelated to the bracket.

Evidence: `app/Services/Draw/FlexibleMonradService.php:186`; `app/Http/Controllers/Backend/EventResultsController.php:73`, `:98`; `resources/views/backend/event/results/individual.blade.php:283`; `app/Domain/Ranking/Services/RankingCalculationService.php:174`.

Required fix: provide a reviewable import/finalize step from resolved draw positions into category results, including explicit handling of multiple draws in one category, unfilled positions and withdrawals. Define how later score corrections invalidate or refresh finalized results.

### 3. P2 — Navigation removes the full workspace for the new formats

Both the normal draw route and round-robin hub redirect flexible draws directly into the standalone editor. Its Manage draw link targets a page with only Settings and Players. The existing full hub contains draw switching, Back to Event, Schedule, Setup & Rules, publication and lock controls. These are not retained around the new editor.

Evidence: `app/Http/Controllers/Backend/DrawController.php:98`; `app/Http/Controllers/Backend/RoundRobinController.php:47`; `app/Http/Controllers/Backend/FlexibleMonradController.php:112`; `resources/views/backend/draw/manage.blade.php:106`; `resources/views/backend/draw/roundrobin/show.blade.php`.

Required fix: one draw workspace shared by formats, with the editor embedded as Draw & Results and an optional full-screen mode. Preserve event, draw and selected-tab context.

### 4. P2 — Dashboard and player controls offer actions their endpoints reject

The individual event draw card still sends Publish/Unpublish to `draw.toggle.publish`. The common controller authorization guard rejects legacy publish mutations for flexible draws with HTTP 409. The same guard blocks old roster mutations, but Manage draw still presents Add selected, Add all, Remove and drag assignment controls. Its eligible query also differs from the editor's active-category eligibility rules.

The schedule-publication route uses the same guarded publish ability, and the flexible editor has no replacement schedule-publication control.

Evidence: `resources/views/backend/draw/_includes/draw_tab_interpro.blade.php:82`; `resources/views/backend/draw/manage.blade.php:34`, `:166`, `:194`; `app/Http/Controllers/Controller.php:25`; `app/Http/Controllers/Backend/DrawController.php:415`, `:435`, `:628`.

Required fix: use format-aware actions and capabilities everywhere. Show the generated roster read-only outside the editor, or link directly to the correct placement action. Keep the server-side legacy guards.

### 5. P2 — Scheduling works underneath, but timetable access is incomplete

Individual auto/manual scheduling already delegates to the flexible scheduler, which checks dependencies and conflicts. However, the editor has no Schedule entry point and its state payload contains no venue/court/time data, so the public flexible page cannot show its order of play.

For event type 5, `schedulePage` selects the Cavaliers trials page before checking draw format. That page's auto-schedule endpoint explicitly rejects flexible draws and tells the user to use the individual scheduler, while the page route keeps selecting trials.

Evidence: `app/Http/Controllers/Backend/ScheduleController.php:20`, `:149`, `:180`; `app/Services/Draw/FlexibleMonradService.php:202`; `resources/views/backend/draw/flexible-monrad.blade.php`.

Required fix: choose scheduling UI by draw format before legacy event type; include schedule navigation and separately published timetable data in the public workspace.

### 6. P2 — Dashboard completion information uses inconsistent sources

The event draw-card template displays `completion_percent`, but the supplying controller only loads registration counts and there is no accessor/calculation in the application. The default is therefore 0%; the progress style also appends another percent sign to that default.

The event fixtures summary counts completed fixtures by non-null winner. A closed branch following double withdrawal can be resolved with no winner, so it remains counted as pending. Other readiness counts use result rows, which exclude automatic walkovers.

The Head Office scheduled-venue summary queries team_fixtures even when preparing the individual event page; `Draw::fixtures()` likewise refers to team fixtures, whereas `drawFixtures()` refers to individual fixtures. Consumers must choose the correct relation.

Evidence: `resources/views/backend/adminPage/partials/draws.blade.php:34`; `app/Http/Controllers/Backend/EventAdminController.php:482`, `:1224`; `app/Services/Draw/FlexibleMonradService.php:262`; `app/Domain/Draws/Services/DrawReadinessService.php:17`; `app/Http/Controllers/Backend/HeadOfficeController.php:116`; `app/Models/Draw.php:109`.

Required fix: shared draw summary with explicit scheduled, played, walkover, closed and pending counts, selecting the correct fixture model per event/draw.

### 7. P2 — Event PDF exports silently omit flexible fixtures

Event export data includes the fixtures, but the PDF template renders only RR, MAIN, PLATE, CONS, BOWL, SHIELD and SPOON stages. Flexible fixtures use FM and are never visited by that loop. The editor's own Print draw path is separate and does not fix the event export.

Evidence: `resources/views/backend/draw/pdf/event-draws-pdf.blade.php:168`; `app/Http/Controllers/Backend/HeadOfficeController.php:1165`; `app/Services/Draw/FlexibleMonradService.php:64`.

Required fix: shared format-aware export data, including placement sections, exact source labels and final positions.

### 8. P2 — Dashboard Players link targets a page with missing view data

`DrawController::players` passes only `draw`, while `backend.draw.manage-players` iterates `allPlayers`. No composer supplying that variable was found. The dashboard's Players button points to this action, so this path is expected to fail rendering independently of the flexible editor.

Evidence: `app/Http/Controllers/Backend/DrawController.php:648`; `resources/views/backend/draw/manage-players.blade.php:45`; `resources/views/backend/adminPage/partials/draws.blade.php:51`.

Required fix: route to the shared player workspace and supply one scoped eligible roster.

## Recommended target workflow

Event dashboard → Draws → one draw workspace:

- Overview: format, readiness, completion and next available actions.
- Players: active category roster and placement status.
- Draw & Results: the appropriate group/bracket renderer and scoring controls.
- Schedule: venues, courts, manual/automatic allocation and timetable publication.
- Setup & Rules: supported scoring settings, notes and guarded structural changes.
- Event-level Final Results: review positions from draws, finalize, publish and feed rankings.

Every draw page should retain Back to Event and Switch Draw. Full-screen editing should return to the same draw and tab. Publishing the draw, publishing its timetable and publishing event results are separate states and need explicit controls.

Implement result authorization first, then workspace navigation/action routing, results handoff, shared summaries and exports. Preserve the existing transactional graph/scoring/scheduling services.

## Verification

- Current focused suites passed: `php artisan test tests/Feature/Draw/FlexibleMonradTest.php tests/Unit/FlexibleMonradCompilerTest.php` — 37 tests, 6,527 assertions.
- Covers generation, revisions, eligibility, isolation, publication, score corrections, withdrawals, winner/loser progression and shared scheduling constraints.
- Expanded route middleware inspected for both individual event results routes.
- Findings above were traced through current controllers, views, services and routes. They are not all covered by the passing focused suites.
- No production mutations, deployment or authenticated production navigation tests were performed. No application implementation files were changed for this audit.
