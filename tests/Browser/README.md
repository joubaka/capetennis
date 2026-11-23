# Draw workspace browser regressions

## CTR Certified planner

Export fictional full-page states from `CtrDrawPlannerTest` with
`CT_CTR_PLANNER_QA_HTML=1` (requires `APP_ENV=testing` and either MySQL database
`ct_testing` or isolated SQLite `:memory:`),
then run `node tests/Browser/ctr-planner.cjs` using Node 24 and Playwright on
`NODE_PATH`. The harness reads `storage/app/testing/ctr-planner/` and serves only
those exports and local static assets. The full exporter produces thirteen states:
initial, preview, shortfall, unrated, mixed, blocked, excluded2, six, ten, twenty,
categories, sparse and created. At 360, 390, 768 and 1440 pixels this gives 52
state/width checks; four Move/native-drag/boundary/exact-POST sequences plus a
cross-category rejection give 57 checks. It verifies numeric group ordering,
unrated suffix placement, short-match acknowledgment, reversible plan exclusions,
and confirmation disabled until edits are reviewed. Six is one linked 3+3
competition with nine matches and two prizes; default ten is independent four
plus linked 3+3, fifteen matches and four prizes. It never connects a browser to
application data or creates live draws.

## Interpro preview

`node tests/Browser/interpro-preview.cjs` checks the fictional Interpro walkthrough
at 360, 390 and 1440px, including four RR groups, all 18 playoff routes, carried
scores, phone scrolling, blank draws and 12 final positions. First export the
focused `InterproRoundRobinFormatTest` full-progression case with
`INTERPRO_PREVIEW_EXPORT=1`; set `INTERPRO_PREVIEW_ASSET_BASE` to the local Wamp
asset root when needed. The exporter requires the isolated `ct_testing` database.
The harness loads only local assets, checks the actual print handlers with
success/empty/failure responses, and writes screenshots and a PDF under
`storage/app/testing/interpro-preview`. Use Node 20+, installed Microsoft Edge
and Playwright in `NODE_PATH`. Optional `--serve` opens the read-only walkthrough
at `http://127.0.0.1:8765/`; it has no application mutation endpoints.

`node tests/Browser/all-age-ratings.cjs` checks the all-age ratings directory at
320, 390, 768 and 1440px with synthetic shared and withheld players. First run
`AllAgeRatingIntegrationTest` with `CT_ALL_AGE_QA_HTML=1` to render the fixture.
Use Node 20+ and configure `NODE_PATH` for Playwright when necessary. The harness
loads actual rendered stylesheet links in source order and blocks external
requests. It checks containment, touch targets, filters and evidence disclosure;
it does not submit application mutations. Screenshots are saved under
`storage/framework/testing/all-age-ratings-*.png`.

`node tests/Browser/mobile-workspace.cjs` runs presentation regressions at 320,
375, 430, 768 and 1280px in both workspace themes. It uses the real Manage Players
toolbar and photo form fragments with local theme/authentication/mobile CSS and
deterministic table, auth and header examples. It checks horizontal containment,
table action reachability, touch targets, player selection and editable inputs.
It requires Node 20+, Playwright and installed Microsoft Edge; set `NODE_PATH`
when Playwright is installed outside the repository. The 320px screenshot is
written under `storage/app/testing`. These fixtures do not verify authenticated
application journeys, persistence, or server operations.

`node tests/Browser/pathway-recommendations.cjs` checks the actual fictional
Tournament options route at 360, 390, 768 and 1440px. Generate its fixture with
`CT_PATHWAY_QA_HTML=1` and the `PlayerPathwayTest` filter
`test_stage_event_links_show_saved_update_state_and_remain_private`. The harness
uses local assets, blocks external requests, and checks saved-stage messaging,
the highlighted card, the matching-events anchor, public event action and
horizontal containment. Screenshots are written under `storage/framework/testing`.
Use Node 20+ and set `NODE_PATH` to the installed Playwright modules if needed.
This verifies rendered fictional data, not live event availability or registration.

These checks serve HTML rendered by Laravel's test suite together with the real public assets. Mutating endpoints are isolated in-memory HTTP doubles; no application database is changed by the browser harness. Backend persistence, eligibility, authorization and rollback are covered by the draw feature tests.

`node tests/Browser/profile-match-progress.cjs` checks the authorized Profile's
last-five-match CTR graph at 360, 390, 768 and 1440px. Generate its populated and
pending fixtures using `CT_PROFILE_MATCH_QA_HTML=1` and `MyTennisOverviewTest`.
It loads actual fictional route HTML with local assets, blocks external requests,
and checks prominent placement, exact chronological before/after/signed changes,
unavailable gaps, pending states, keyboard scrolling and readable result rows.
Screenshots are under `storage/framework/testing`. Use Node 20+ and configure
`NODE_PATH` for Playwright as needed. This does not verify live player artifacts
or run any rating calculations.

`node tests/Browser/results-overview.cjs` checks the Results overview with actual
fictional Blade fixtures and local styles at 360, 390, 768 and 1440px. Generate the
five `results-overview-*-qa.html` fixtures with `CT_RESULTS_QA_HTML=1` and
`PlayerPlusRecordTest`. It checks exact season statistics, chart placement, month
filter URLs, native keyboard disclosure, zero/unavailable/100% states and the
real navigation script's polling preservation of open state and focus. External
requests are blocked. Screenshots are under `storage/framework/testing`; these
are rendered-fixture checks, not a live player or registration journey. Use
Node 20+ and set `NODE_PATH` to installed Playwright modules when necessary.

Prerequisites: the application's configured testing database, PHP dependencies, Node.js 20 or newer, Playwright and its Chromium browser. If Playwright is installed outside this repository, set `NODE_PATH` to that installation's `node_modules` directory.

From the repository root in PowerShell:

```powershell
$env:DRAW_WORKSPACE_SNAPSHOT = '1'
php artisan test tests/Feature/Draw/DrawWorkspaceTest.php
Remove-Item Env:DRAW_WORKSPACE_SNAPSHOT
node --preserve-symlinks tests/Browser/draw-workspace.cjs
```

The generated fixture and desktop/mobile screenshots are under `storage/app/testing`. Regenerate the fixture after changing Blade or backend page data. The tests block cross-origin browser requests and verify the real frontend against deterministic response data.

For a temporary manual preview:

```powershell
node --preserve-symlinks tests/Browser/draw-workspace.cjs --serve
```

Open `http://127.0.0.1:8187`. This is a labelled test preview, not the running application: settings, team imports, publication and other server operations are not implemented by its doubles. Stop the process when finished. Real integration verification uses the Laravel feature suite and an authenticated application session.
