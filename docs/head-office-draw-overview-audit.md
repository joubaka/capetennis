# Head Office draw overview

Audited and restructured locally on 2026-09-03. Scope: the individual-event page shown at `/backend/headOffice/233` and other events using the same template. Team and Interpro page layouts are unchanged.

## Findings and implemented changes

| Finding | Change |
|---|---|
| Seven equally prominent actions on every large card obscure the next step. | Compact rows with Open draw and Schedule; secondary actions in an accessible menu. |
| Four event links consume a quarter of the desktop width. | Event overview, Draws and Event directors become wrapping horizontal navigation. System Check is removed from this event workspace; global administration is retained. |
| Engine looks like the main way to run the tournament. | Relabeled Engine diagnostics, moved into the secondary menu and shown only to super-users. |
| Team-Singles describes the old type field even on an individual event. | Show the configured workflow when known, individual fixture counts, publication state and venues. Unknown formats are not guessed. |
| The page has venue handlers but no venue modal. | Restore the shared modal; add request errors, safe text rendering, field labels and duplicate-submit protection. |
| Deletion tries to remove a `.list-group-item`, but the page uses draw cards. | Reload on successful deletion so rows, totals, empty state and print selection agree. Retain confirmation; hide deletion for locked/published draws. |
| Publishing is a repeated tournament-day task but is hidden in each row's overflow menu. | Promote Publish/Unpublish to a visible row action, confirm the public visibility change, then reload all status-derived UI after success. |
| Custom Monrad publication is rejected by the legacy endpoint. | Use its revision-aware publication endpoint directly from the overview; server-side graph/readiness, locking and authorization checks remain authoritative. |
| Bulk PDF/round-robin printing cannot represent all flexible brackets. | Exclude those draws from bulk export and direct users to Print in their draw editor. |
| Individual overview loads unused team summaries, categories and formats. | Return individual view data early; load individual match counts and required relations only. No historical fixtures are loaded into memory. |
| Unused editors, tables, date pickers, validation libraries and obsolete fixture-generation handlers are loaded. | Remove those page dependencies and handlers. Keep the libraries used by the remaining controls. |

## Page structure

1. Event name, Print draws and New draw.
2. Compact event navigation.
3. Draw total and published/draft counts, search and status filter.
4. Draw rows with name, format where known, match count, venues and publication state.
5. Open draw, Schedule and Publish/Unpublish, followed by settings, players, single-draw print, venues and eligible administrative actions in the menu.

The layout stacks on mobile; labels, empty filter results and a live result count support keyboard and screen-reader use. Existing server authorization and mutation services remain authoritative.

## Verification and boundaries

- Four focused render regression tests pass (16 assertions): flexible/legacy navigation and publication, locked/published deletion visibility, and unauthorized mutation/diagnostic visibility.
- PHP and JavaScript syntax checks, Blade compilation, route registration and diff whitespace checks pass.
- A rendered sample-data preview verified desktop and 320px layout, search, combined filters, clear filters, secondary menus and the restored venue modal. This does not establish authenticated end-to-end mutation success.
- The actual local route redirects to login. Authenticated full-page checks, actual create/save/publish/delete requests and production verification remain outstanding.
- No deployment or database mutation was performed. Existing unrelated bracket work is preserved.

## Subsequent work

The separate `draw-dashboard-integration-audit.md` describes broader issues in unified draw navigation, final results, rankings and format-aware exports. Those require dedicated changes beyond restructuring this overview.

## Follow-up: header overlap

The shared horizontal layout inserted a mobile-menu script between the menu and content container. This broke the theme's `.menu-horizontal + [class*='container-']` selector, removing the space reserved for the fixed desktop menu. The script now runs after the content container, retaining its mobile behavior and restoring the existing theme rule.

A full-layout sample preview reproduced the defect at 1440px: the event header started at 86px while the menu ended at 120px. After correction the header starts at 139px, leaving about 19px clearance. Mobile spacing and menu open/close were checked at 375px. Authenticated production verification remains outstanding.

## Follow-up: visual refinement

The individual draw overview now uses a restrained navy/teal palette, a stronger event heading, inline SVG action icons, and aligned Division / Draw format / Matches / Venue / Manage draw columns. Smaller layouts become labeled draw cards with touch-sized actions. Missing workflow values say "Not specified" instead of inventing a format.

Publication filters are counted, accessible toggle buttons. Search matches both division names and configured format names; the live result count and empty state remain. Canonical draw, schedule, settings and print links and existing authorization guards are unchanged.

Verification: seven focused render tests pass (34 assertions), JavaScript syntax and Blade compilation pass, and diff whitespace checks pass. Authenticated local desktop and tablet previews were inspected, along with phone layouts measured at 373px and 417px CSS viewport widths. Format search, combined publication filtering, the empty-result state, and the mobile secondary-action menu were verified. Temporary viewport overrides were reset. Create/save/publish/delete and actual printing were not exercised; no event data was changed or deployment performed.

An unrelated existing failure was observed on the linked Event overview page: `backend.adminPage.admin_show.modals.generateDrawOptionsModal` is missing. Its two existing includes are outside this draw-overview styling change and were left untouched.
