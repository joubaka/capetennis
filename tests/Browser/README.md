# Draw workspace browser regressions

`node tests/Browser/mobile-workspace.cjs` runs presentation regressions at 320,
375, 430, 768 and 1280px in both workspace themes. It uses the real Manage Players
toolbar and photo form fragments with local theme/authentication/mobile CSS and
deterministic table, auth and header examples. It checks horizontal containment,
table action reachability, touch targets, player selection and editable inputs.
It requires Node 20+, Playwright and installed Microsoft Edge; set `NODE_PATH`
when Playwright is installed outside the repository. The 320px screenshot is
written under `storage/app/testing`. These fixtures do not verify authenticated
application journeys, persistence, or server operations.

These checks serve HTML rendered by Laravel's test suite together with the real public assets. Mutating endpoints are isolated in-memory HTTP doubles; no application database is changed by the browser harness. Backend persistence, eligibility, authorization and rollback are covered by the draw feature tests.

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
