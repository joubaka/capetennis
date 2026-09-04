# Cape Tennis backend style audit

Audit date: 3 September 2026. Scope: the supplied screenshot, the current local Blade tree, shared layouts and assets, and relevant controller/route connections. This is a source audit and implementation specification, not a completed redesign or a browser audit of every page.

**Recommendation: adopt this style across the backend.** Use its navy headings, teal accents, quiet surfaces, consistent spacing, contextual navigation and clear actions as a shared design system. Keep specialist layouts for brackets, schedules, financial records and print.

The screenshot is a visual reference. Its browser debugging banner is browser chrome and is not part of the application design.

## Coverage and evidence

- Inventoried all **481 Blade templates**, including **255 under `resources/views/backend`**. The CSV and JSON beside this report contain every file, its layout, size, style indicators, plugin indicators and literal reference candidates.
- Backend templates comprise **131 with an explicit layout** and **124 without `@extends`**. The latter include partials, exports and standalone documents; these are not 124 missing layouts.
- **120** backend templates extend `layoutMaster` (including three using dot notation); **11** directly extend `contentNavbarLayout`.
- **54** backend templates contain `<style>` blocks; **94** contain inline `style=` attributes; **22** contain `!important`. These counts overlap. Print rules, script-generated markup and legitimate dynamic sizing contribute to these indicators.
- Route registration succeeded: **799 non-vendor application routes**, **656 with a `backend` URI prefix**. Those counts include mutations and data endpoints, not just screens. Some administrative screens exist outside that prefix.
- `php artisan view:cache` succeeded. `git diff --check` succeeded with existing LF/CRLF conversion warnings. Compilation does not resolve every nested include or exercise controller data.
- Deep inspection covered the reference implementation, both application shells, event dashboard dispatch, draw workspace composition, scheduling, representative financial/operational screens, and missing view references.
- No authenticated browser sweep, all-role interaction test, accessibility certification, print/PDF rendering or production verification was performed in this audit. No application source was changed. Existing working-tree changes were preserved; Blade compilation refreshed the local compiled-view cache.

Inventory references are literal string matches in application PHP, routes and Blade. A route name can resemble a view name; dynamic view selection can also be missed. Zero references is a review candidate, never proof that a template is unused. Style/plugin flags are search indicators, not automatic defects.

## Reusable reference implementation

The requested appearance is already implemented locally in:

- `resources/views/backend/headOffice/individual-event-show.blade.php`: page entry and page assets.
- `resources/views/backend/headOffice/partials/individual-draw-overview.blade.php`: heading, event navigation, toolbar, search, status filters and empty states.
- `resources/views/backend/headOffice/partials/individual-draw-row.blade.php`: responsive row, labelled state and action hierarchy.
- `resources/views/backend/headOffice/partials/draw-icon.blade.php`: SVG icons.
- `public/css/head-office-draws.css` and `public/js/head-office-draws.js`: appearance and filtering/interaction support.

Promote the general presentation out of these draw-specific names. Do not load this stylesheet everywhere and expect unrelated markup to adopt its structure.

| Design decision | Reference value / proposed standard |
| --- | --- |
| Heading and primary action | Existing navy `#172e45` |
| Supporting text | Existing `#66788a`; check contrast at each actual size/background |
| Accent | Existing teal `#14796e` |
| Borders | Existing `#e4eaf0` |
| Surface | White cards on a pale neutral page background |
| Typography | Existing Public Sans; a consistent page heading, section heading and body scale |
| Shape | Reference 14px card corners and 8px control corners |
| Width | Reference 1440px maximum for ordinary workspaces; wider contained canvases where required |
| Actions | One prominent page action, quiet secondary actions, labelled overflow menu |
| Status | Text plus a restrained colour marker; retain meaningful warning/error/success distinctions |
| Mobile | Stacked page header, wrapping controls, labelled list rows and reachable actions |

The screenshot's green/orange/purple global navbar actions remain a separate style from the navy/teal workspace. Include the backend navbar treatment in the rollout if the whole backend should feel consistent.

## Findings and required treatment

### 1. Two shells prevent a consistent navigation experience

`layouts/layoutMaster.blade.php` dispatches using configuration; `config/custom.php` defaults to horizontal. The 11 pages directly extending `contentNavbarLayout` include the round-robin and flexible workspaces, setup screens, two schedulers, Masters screens and interpro draw screens. Several explicitly set `myLayout` to vertical. Moving from the supplied draw overview into an editor can therefore change the whole navigation frame.

Create an explicit backend layout entry point with a shared page identity and navigation contract. Default ordinary backend pages to the reference frame. Make a wide workspace variant for editors. Migrate direct vertical-layout users deliberately, preserving their needed navigation destinations and available canvas width.

The shared `commonMaster`, navbar and theme styles also serve public/player pages. For example, `frontend/roundrobin/show.blade.php` directly uses `contentNavbarLayout`. A global `.card`, `.btn`, `.nav-link` or theme-primary override would affect more than the requested backend.

**Implementation:** give backend pages an explicit layout/body marker; load a backend stylesheet only for that surface. Include backend modals rendered outside the page wrapper and vendor controls attached to the body in that scope. Do not infer presentation or permission solely from a `/backend` URL.

### 2. Page-specific styling needs component migration

The reference uses navy/teal; dashboard tabs and draw navigation still use purple (`backend/dashboard.blade.php`, `public/css/draw-workspace-navigation.css`, `public/assets/css/draw-workspace.css`). Super-admin uses a gradient hero. Entries use purple category borders. Finance pages have their own heading/table treatment. Masters has another row and table scale.

Extract reusable page headers, contextual navigation, panels, filter bars, badges, action groups, form sections and empty states. Move repeated styling to shared tokens and scoped components. Retain small feature-specific styles for geometry or interaction. Vendor-generated DataTables, Select2, Flatpickr, Quill and SweetAlert content needs matching treatment too.

Large mixed markup/script templates make a one-pass CSS replacement fragile: `superadmin/index` is 2,275 lines, `event/finances` 1,822, and `event/individual/entries` 1,331. Extract presentation around stable IDs and form contracts; avoid combining a visual change with business logic rewrites.

### 3. Event navigation spans competing dashboard families

`eventAdmin.show` renders `backend/adminPage/show.blade.php`, which dispatches event types into several older `admin_show` partials. `admin.events.overview` renders the newer overview family. Head Office has separate individual, team, Cavaliers and interpro pages.

Use one event identity and permission-aware contextual navigation across overview, entries/teams, draws, fixtures/schedule, finances, settings and directors. Show only destinations appropriate to the event and user. Preserve event, division and current workspace context. Do not redirect every existing event screen to a single replacement before checking event-type-specific controls.

### 4. Broken view references need resolution before visual acceptance

These are current source defects, separate from colour and spacing:

| Evidence | Impact / next action |
| --- | --- |
| `adminPage/admin_show/individual_show.blade.php:478` and `schools.blade.php:104` include `backend.adminPage.admin_show.modals.generateDrawOptionsModal` | The file actually lives under `admin_show/tabs/modals`. These partials are selected for event types 6 and 12. Correct and verify required variables/controls before accepting those event overviews. |
| `adminPage/admin_show/cavaliers_trials_show.blade.php:340` includes `backend.adminPage.admin_show.modals.nominationModal` | Existing file is under `admin_show/tabs/modals`; affects the event-type-5 branch when rendered. |
| `DrawController::index()` at line 48 returns missing `backend.draw.draw-index` before its team/individual branches | `draw.index` is registered at `GET backend/draw`. Once an authorized event resolves, the missing view prevents rendering and later branches are unreachable. Resolve this entry point against the intended canonical navigation. |
| `GoalController::create()` refers to missing `backend.goal.create-goal` | `goal.create` is registered. Resolve the intended general/career goal workflow. |
| `DrawController::showBoxMatrix()`, `EventAdminController::entries_new()` and `TeamController::showRankingImport()` reference missing views | Source debt; route/caller reachability for these methods is not established by this audit. Check before either migrating or retiring. |
| `backend/draw/show.blade.php` includes absent `backend.schedule.schedule-table` and `backend.schedule._modal` | No literal incoming view reference found. Treat as a legacy candidate; establish reachability before investing in redesign or deletion. |

Blade compilation passed despite these references: a compiled template can still fail when a missing nested view is rendered. These failures were source-traced, not reproduced through every authenticated route during this audit.

### 5. Draws need shared chrome and specialist content

Keep the canonical individual workspace at `backend.draw.roundrobin.show`, its shared `workspace-header`, supported fragments (`#groups`, `#matrix`, `#schedule`, `#settings`, `#print`), and context-preserving draw switching. Apply the new header, navigation, controls, panels and status treatment there.

Keep the round-robin matrix, playoff bracket, Flexible Monrad editor, connector geometry, zoom/pan, drag/drop and score entry as specialist components. Team and interpro surfaces need their own adaptation; they cannot be replaced wholesale by the individual draw list.

`commonMaster` includes shared bracket assets, and public/print views reuse draw presentation. Preserve `tennis-bracket` renderer contracts and isolate backend chrome from public and print rendering. Distinguish draw publication, timetable publication and locking; a single generic status must not conceal these separate states.

### 6. Mobile, accessibility and print require explicit acceptance

The reference already includes labelled mobile row fields, publication text, search labelling, an announced result count, current-page navigation and reduced-motion handling. Carry those patterns across components.

Entries currently force a 760px minimum table width; retain contained horizontal scrolling for genuinely comparative tables and provide a compact list treatment where users primarily act on individual records. Avoid making the whole page overflow. Bracket canvases and schedule grids need contained pan/scroll rather than shrinking text to fit a phone.

`commonMaster.blade.php` currently disables user zoom through its viewport settings. Remove that restriction as part of an accessibility pass. Verify keyboard focus, modal focus return, dropdown placement, error associations, touch targets and non-colour status cues. Review dense 11–13px reference labels before treating them as universal sizes.

The theme contains light/dark machinery while the reference uses hard-coded white surfaces. Establish backend token variants for any supported theme selection, or make the backend's supported light-only presentation explicit. Do not leave an unreadable hybrid.

Keep print/PDF output separately styled: readable monochrome, fitting columns, correct page breaks and no navigation chrome. A passing browser screen review does not verify a printed draw.

## Proposed reusable structure

The following paths are proposed; they have not been created by this audit:

- `resources/views/layouts/backend.blade.php`: explicit backend shell and page surface marker, with an ordinary/wide content option.
- `resources/views/components/backend/page-header.blade.php`: title, context, state and action slot.
- `resources/views/components/backend/context-nav.blade.php`: event or module navigation; callers supply authorized links and active state.
- `resources/views/components/backend/panel.blade.php`, `filter-bar.blade.php`, `status-badge.blade.php`, `empty-state.blade.php`, and `form-section.blade.php`.
- `public/css/backend-workspace.css`: shared tokens and scoped components, consistent with the existing versioned static CSS approach. Keep Bootstrap/Vuexy behaviour and plugin assets in place.

Load shared backend presentation after vendor theme styles and before migrated page-specific exceptions. Use one declared order and remove obsolete page rules as their components migrate. No frontend framework replacement is needed for this design.

## Rollout order and completion criteria

1. **Foundation and navigation:** resolve confirmed missing active views, add the scoped backend shell and components, and align the backend navbar and mobile frame.
2. **Event operations:** reference draw overview, all Head Office event types, event dashboards, entries/teams and directors. Make navigation consistent across these routes.
3. **Draw and schedule workspaces:** shared chrome for individual, flexible, team and interpro flows; preserve editor geometry, deep links, publication, locking and printing.
4. **Financial and operational screens:** super-admin, event finances, refunds, wallet, payments, Masters, disciplinary and audit tools. Preserve amounts, forms, permissions and state semantics.
5. **Remaining administration:** player/user/venue, rankings/series, agreements, settings, photos, clothing, imports, goals, nominations and peripheral views; resolve or retire legacy candidates after caller verification.

For each phase, require authenticated desktop and narrow-screen inspection of representative populated, empty and error states; correct role-scoped actions; working search/filter/pagination; and functional modals and forms. Check ordinary widths at 375/768/1440px, narrow 320px stress cases, and desktop zoom. Add focused regression checks only for affected contracts; any financial flow changes require the repository's financial test gates.

Before release: compile Blade, verify routes/assets, run whitespace checks, exercise navigation between event types and draw formats, and inspect print/PDF output. Keep final completion tied to the inventory so small settings pages and alternate event branches are not missed.

## Complete backend area map

Counts below include pages, partials, modals and exports. They are workload indicators, not distinct screen counts. Every template is itemized in the companion inventory.

| Area | Templates | Files with style blocks | Migration treatment |
| --- | ---: | ---: | --- |
| adminPage | 33 | 7 | Unify event header/navigation and table/actions across event-type branches; repair missing modal includes. |
| agreements | 4 | 0 | Shared list/detail/form components; retain rich agreement content. |
| categoryEvent | 1 | 1 | Category controls and status in the event workspace. |
| clothing | 3 | 1 | Order lists, forms and modal actions; preserve monetary columns. |
| convenor | 1 | 0 | Director list and assignment controls in event context. |
| (root) | 2 | 1 | Dashboard tabs, event lists and primary actions; inspect the superAdminDashboard stub. |
| disciplinary | 9 | 0 | Case/list/detail/forms and labelled status; preserve restricted actions. |
| draw | 56 | 11 | Shared chrome; specialist matrices, bracket/editor geometry, scoring and print remain. |
| engine | 2 | 0 | Compact diagnostic tables with consistent headings; preserve access restrictions. |
| event | 31 | 13 | Overview, entries, teams, settings, finance, announcements and export variants. |
| eventAdmin | 1 | 0 | Align the additional event entry point with canonical event navigation. |
| fixture | 4 | 0 | Fixture lists, score/replacement modals and venue context. |
| goal | 2 | 0 | Form sections and stepper treatment; repair registered missing create view. |
| headOffice | 15 | 2 | Extend the reference beyond individual events to team, trials, interpro and venue fixtures. |
| import | 1 | 0 | Consistent upload/form and validation feedback. |
| league | 2 | 0 | List and category modal components. |
| masters | 3 | 2 | Invitation/review rows, selection state and actions; align shell. |
| nominations | 2 | 0 | Compact table and selected-state treatment within parent event screens. |
| payments | 1 | 0 | Order-item table and monetary alignment; establish current callers. |
| photo | 9 | 0 | Gallery/list, upload and folder/image modals; retain media proportions. |
| platform | 3 | 1 | Health table/status and diagnostic hierarchy. |
| player | 5 | 0 | Player list/profile/detail/edit with standard headings and forms. |
| ranking | 9 | 5 | Ranking/settings/results/points/series audit tables; preserve dense comparison. |
| refunds | 2 | 0 | Queue/detail/approval controls with labelled state and aligned amounts. |
| schedule | 3 | 1 | Shared header and controls; keep timetable, court layout and conflict distinctions. |
| scoreboard | 6 | 1 | Readable score tables and grouped breakdowns; preserve score semantics. |
| series | 11 | 1 | Consistent series navigation/list/settings/ranking screens; resolve older parallel pages. |
| settings | 1 | 0 | Grouped settings forms and a clear save action. |
| superadmin | 11 | 3 | Modular dashboard, finance/audit/duplicate-review tables, restrained hero and actions. |
| system | 1 | 1 | Consistent diagnostic heading and status panel. |
| team | 1 | 0 | Replacement form partial in its parent workflow. |
| team-fixtures | 10 | 2 | Fixture administration, edit/replace forms and home/away/result partials. |
| team-schedule | 2 | 0 | Wide timetable with contained overflow and shared controls. |
| teamSelection | 1 | 0 | Selection table and confirmation actions. |
| user | 2 | 1 | User list/detail and role/action modals. |
| venue | 1 | 0 | Venue detail and court/venue modals. |
| wallet | 4 | 0 | Wallet list/detail/transaction/deposit controls with monetary alignment. |

Administrative and shared dependencies outside `backend` also need inclusion: four `admin` templates (including the separate refund screens), `orders` and shared `multiend` player-profile views, `layouts`, `components`, `_partials` and the shared `draw`/`bracket` templates. Public, authentication, email and standalone export templates are inventoried for dependency awareness; they should not automatically receive backend navigation or spacing.

The attribute scan flagged `data-target` in the goal forms, but these belong to bs-stepper and are not Bootstrap migration defects. An older `data-dismiss="alert"` remains in `backend/adminPage/_includes/team_type.blade.php`; verify its caller before adapting that alert.
