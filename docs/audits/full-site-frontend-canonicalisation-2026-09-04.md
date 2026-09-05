# Cape Tennis full-site frontend audit and canonicalisation

Date: 4 September 2026
Scope: local application only; no deployment, financial mutation, registration change, publication change, or production migration.

## Coverage

- Inventoried 488 Blade views: 82 frontend views and 261 backend/admin views.
- Reviewed the shared horizontal, content-navbar, blank/auth, and backend layouts.
- Rendered representative public and authenticated surfaces: home, public rankings, event detail, public draw, My Tennis, profile dashboard, event operations, Head Office draw overview, and the Flexible Monrad workspace.
- Checked desktop presentation and a 390 x 844 phone viewport, including overflow geometry and accessible page structure.
- Kept specialist draw, scoring, timetable, PDF, and print geometry outside the shared visual overrides.

## Findings

### Resolved in this pass

1. **Public/member pages lacked a canonical shell.** The backend already used the navy/teal workspace, while public pages still inherited Vuexy purple, mixed card radii, different focus states, and inconsistent form/button treatment. A scoped `ct-frontend` shell now provides the same design tokens and component language without applying backend navigation.
2. **Small-screen horizontal drift.** The public shell now constrains the content backdrop, columns, cards, tables, and the guest account control. The rendered ranking page previously measured 380px scroll width against a 375px document width.
3. **Event identity consumed too much of the first viewport.** The event banner is reduced from the vendor default 250/150px to 132/88px, with a smaller mobile logo and tighter identity block. Event information now begins substantially earlier.
4. **Event list rows could force narrow layouts.** Category/player rows now have bounded content and switch to a stacked mobile presentation.
5. **Brand colour drift.** Home filters, home ranking links, ranking cards, and both ranking hero variants now use the same navy/teal palette as the backend shell.
6. **An event page depended on a third-party icon stylesheet.** The individual-event view now uses the already-bundled Tabler icon set and no longer loads Bootstrap Icons from jsDelivr for one icon.
7. **Missing decorative-image semantics.** The event banner is now explicitly decorative (`alt=""`), while the event logo retains its descriptive alternative text.

### Follow-up roadmap

1. Consolidate repeated inline page CSS into feature stylesheets after visual baselines exist for checkout, fixtures, photos, disciplinary, and profile flows.
2. Convert the long public event player area into an accessible category disclosure/filter workspace for high-entry events; this needs product agreement on the default expanded state.
3. Replace remaining Bootstrap Icon references across team fixtures and shared draw partials with the bundled Tabler set, then remove the dependency globally if no other consumer remains.
4. Standardise public page headers through a reusable Blade component, starting with My Tennis, registration/refund, fixtures, and player profiles.
5. Add role-based browser journeys for guest, player/parent, convenor, event admin, and super-user. This audit used the available local session and did not mutate records.
6. Perform dedicated print/PDF pagination checks and production deployment verification separately.

## Canonical boundaries

- `resources/views/layouts/backend.blade.php` plus `public/css/backend-workspace.css` remains the admin shell.
- `resources/views/layouts/commonMaster.blade.php` now attaches `public/css/frontend-workspace.css` only when the backend workspace is not active.
- Draw engines, scoring logic, publication state, payments, wallets, refunds, and registration lifecycle were not changed.

## Release verification — 5 September 2026

- Blade cache compilation, route registration, and `git diff --check` passed.
- Frontend, ranking, and authentication coverage passed: 14 tests / 72 assertions.
- The complete unit suite passed: 290 tests / 6,374 assertions.
- Registration, wallet, and refund coverage passed 47 of 48 tests / 118 assertions. The one failure is the existing PayFast checkout expectation when the test environment has no merchant configuration; the rendered page correctly uses its safe "Online payment is temporarily unavailable" state.
- The complete feature-suite command exposed an existing test-order isolation defect: `AddExpiresAtToPersonalAccessTokensMigrationTest` leaves `ct_testing` with partial schema state, after which unrelated suites fail with duplicate table/column errors. The dedicated `ct_testing` database was rebuilt with `migrate:fresh --env=testing --force`, and the scoped tests passed afterward.
- Desktop and 390 x 844 browser smoke checks covered home, rankings, event detail, login, the public Flexible Monrad draw, and the frontend/backend stylesheet boundary. Every normal page had document scroll width equal to client width, specialist draw overflow stayed contained, and no browser console errors were recorded.
