# Backend style rollout — 3 September 2026

Implemented locally from the Head Office reference. All 131 existing backend Blade pages with an application layout, plus the two separate admin refund pages, now explicitly use `layouts.backend`. Included partials inherit their parent page's style. The companion rollout JSON lists every migrated entry view.

## What changed

- Added an explicit horizontal backend shell with the reference light palette, Public Sans typography, navy primary actions, teal accents, restrained cards and borders, and consistent form, table, tab, modal and dropdown styling.
- Added shared page-header, contextual-navigation, panel and empty-state components. Event administration, Head Office, draw workspaces, entries, event settings, event finances, super-admin, refund operations and agreements now use the shared presentation where their page structure needed adaptation.
- Event navigation carries the current event through overview, entries/teams, draws, directors, finances and settings. Links use existing event-scoped gates. Overview links lead consistently to `admin.events.overview`; older event dashboards remain available through their existing routes.
- Individual round-robin and flexible draw workspaces and their schedulers now use the same horizontal shell as the overview. Draw workspaces opt into a wider content area. Existing IDs, tab fragments, player/score controls, draw switching and publication/lock actions remain connected to their existing handlers.
- Adapted Flexible Monrad's outer surface and controls to the shared palette without altering bracket geometry or its public renderer. Matrix, bracket, timetable and printable content keep their specialist layouts.
- Scoped the new stylesheet to the explicit backend body marker, including vendor overlays attached outside page containers. Public pages do not load this theme. Backend pages use the reference light theme and omit the theme switcher; public presentation configuration remains separate.
- Fixed three incorrect event-modal include paths. The legacy draw index now authorizes the supplied event and redirects to its Head Office page instead of returning a missing view. Its event identifier is read through Laravel's Request rather than `$_GET`.
- Corrected the shared font stylesheet URLs to support installations under `/ct/public`, which fixed blank icons locally. Removed the shared viewport restriction on user zoom.
- Fixed mobile menu layering so its close button stays clickable, added Escape-to-close with focus return, and wrapped entry category actions so they do not widen a 320px viewport.

The shared CSS is a versioned static asset at `public/css/backend-workspace.css`; no asset build or migration is required for it. Existing Bootstrap/Vuexy scripts and feature-specific assets remain in use.

## Verification

38 distinct focused tests passed, with 300 assertions, across:

- `BackendWorkspacePresentationTest` — 4 tests / 24 assertions.
- `CanonicalDrawWorkspaceTest` — 5 tests / 54 assertions.
- `HeadOfficeDrawOverviewTest` — 7 tests / 34 assertions.
- `DrawWorkspaceTest` and `DrawSetupTest` — 22 tests / 188 assertions combined.

Coverage includes explicit backend/public style isolation, escaped event titles, event-scoped navigation, authorized legacy redirects, missing identifiers, modal form contracts, draw setup, cross-event rejection, revision handling, roster preservation, scoring safeguards and separate schedule publication.

Blade compilation, PHP lint for the changed controller, CSS parsing, route registration and whitespace checks passed. The view inventory found no remaining ordinary backend/admin entry views extending the old application layouts directly.

Authenticated local browser checks used the existing Overberg event and its draw workspaces. Inspected the dashboard, super-admin, modern and legacy event overviews, individual draw overview, flexible editor, round-robin page, entries, finances and settings. Checked mobile menu open/close hit testing, draw switching, and entry action wrapping. At a measured 320px CSS viewport, entries had a 303px document client width and a matching 303px scroll width after the fix; comparative tables retain their own scrolling. The new-draw modal opened within the 320px viewport (left 12px, right 308px) and was cancelled without submission. Flexible draw canvas scrolling remains contained. Temporary viewport overrides were reset.

Browser verification was read-only with respect to event data. No fixture generation, publication, score saving, financial action, message sending or event-setting submission was performed. Browser screenshot capture was intermittent through one tool surface; desktop finance/settings and flexible-editor screenshots were inspected through the working browser capture API, with DOM/geometry checks supplementing responsive checks.

## Boundaries

This completes the shared style adoption, not a rewrite of every legacy workflow or removal of every inline style. Specialist layout rules and existing feature markup remain where needed. Standalone PDFs/exports, email, public/demo draw documents and old partial-only templates do not receive backend navigation automatically.

Every authenticated route and every role/data combination has not been visited in the browser. Actual printing/PDF pagination and production deployment remain unverified. Other legacy missing-view candidates identified in the original audit, including the generic goal creation entry point and unconfirmed legacy controller methods, are separate functional debt; they were not silently redirected or replaced with speculative workflows.

No commit, push, deployment or production migration was performed. Existing unrelated draw, bracket and scheduling work was preserved.
