# Admin simplification: batches 37–81

Implemented locally on 8 October 2026. Existing worktree changes from earlier batches were preserved. Established workflows receive scoped mobile controls, visible keyboard focus, wrapping headers and clearer labels; the table identifies the additional process changes and reviewed legacy exceptions.

| Batch | Area | Result |
|---|---|---|
| 37 | Event creation/editing | Correct Information/Venue Notes associations, retained form inputs and optional import disclosure. |
| 38 | Individual/camp/special overviews | Responsive individual overview; empty camp legacy template remains inactive. |
| 39 | Individual draw setup/rules | Accessible format controls; existing guided stages preserved. |
| 40 | Draw allocation | Current-page search/counts, accessible actions and request-failure handling. |
| 41 | Round-robin workspace | Responsive controls and focus; existing draw lifecycle preserved. |
| 42 | Flexible Monrad | Responsive tabs and operational controls. |
| 43 | Specialised schedules | Existing redirect to canonical event venue schedule verified; no duplicate scheduling process introduced. |
| 44 | Scoreboards | Phone layout uses one column and a static sidebar. |
| 45 | Masters setup | Larger touch controls and Top-X stepper. |
| 46 | Masters responses | Search displayed invitees without changing reviewed selection or response actions. |
| 47 | Team workbook imports | Import-preview controls and layout; existing validation retained. |
| 48 | Imported rosters/reserves | Responsive roster controls; existing roster participation rules retained. |
| 49 | Selection emails | Send wording with background-delivery explanation and existing audience-review tokens. |
| 50 | Trials squads | Responsive programme controls and optional settings disclosure. |
| 51 | Trials participation/payments | Existing proof/refund stages retained with scoped operational controls. |
| 52 | Trials communications | Clear send wording and background-delivery explanation; exact audience review retained. |
| 53 | Series directory/create/overview | Form labels, retained inputs, validation recovery and responsive controls. |
| 54 | Ranking lifecycle | Accessible controls; calculate/review/publish/rollback stages remain separate. |
| 55 | Ranking details/audit | Correct three-column detail table and truthful empty state. |
| 56 | Ranking points/categories | Save-button recovery and operational controls. |
| 57 | Player profile/results | Responsive controls and explicit results empty state. |
| 58 | Performance screens | Responsive directory/evidence controls; existing privacy and evidence workflow retained. |
| 59 | Leagues | Search existing categories; fake category links and console-only Add Category action removed. No stub CRUD added. |
| 60 | User directory/roles | Server pagination capped at 100, exact counts/search, escaped display values and named role-action routes. |
| 61 | User detail/linked players | Identity first on phones, paginated ledger history and bounded private-safe player search. |
| 62 | Wallet directory | Existing active workspace retained; unrouted legacy wallet template corrected to its actual wallet dataset. |
| 63 | Wallet detail/adjustments | Paginated full-ledger history; adjustment handlers render correctly, failed inputs/retry keys preserved. Misleading edit/delete actions removed for immutable entries. |
| 64 | Event transactions/refund exceptions | Form labels and inline refund errors; canonical refund calculations and services retained. |
| 65 | Global finance | Responsive operational controls, unchanged financial-year calculations and event statements. |
| 66 | Settlement/full-refund/payout review | Accessible form controls; payout form reopens after validation and retains reference. Exact refund review remains. |
| 67 | Duplicate discovery | Responsive wrapping toolbar, existing bounded candidate filters retained. |
| 68 | Duplicate merge reviews | Accessible review fields; exact digests, confirmations and history protections preserved. |
| 69 | Orphan recovery | Responsive review controls; repair and sandbox actions retain their existing server checks. |
| 70 | Agreements | Accessible versions/forms/actions; acceptance and activation review remain distinct. |
| 71 | Disciplinary cases | Evidence/panel/decision labels, visible errors and retained incident selections. Existing authority, immutable decisions and appeals retained. |
| 72 | Legacy disciplinary history | Accessible correction/history forms and responsive controls. |
| 73 | Disciplinary rules | Accessible settings/type/threshold fields; existing configuration rules unchanged. |
| 74 | Global fees/content | Section shortcuts, phone table containment and truthful content-save failure handling. |
| 75 | Super-admin workspace | Operational controls; refund actions lead to canonical review. Locked wallet-deletion controls removed. |
| 76 | Audit centre | Responsive filters and detail view; metadata disclosed on demand. Filtered export retains its query scope. |
| 77 | API integrations | Responsive status/filter controls; no credential-management flow added. |
| 78 | Health/engine diagnostics | Wrapping summaries/commands, contained tables, accessible status indicators and accurate engine wording. |
| 79 | Print/export consistency | Existing scopes verified; round-robin printing reports blocked pop-ups instead of failing. |
| 80 | Legacy reconciliation | Broken wallet-create and missing goal-create pages redirect to canonical review/directory routes. Empty/stub pages remain inactive. |
| 81 | Consistency pass | Shared scoped touch/focus/layout controls; representative phone/desktop previews and interaction checks. |

## Verification

- Pending batches 32–36: 17 focused tests / 698 assertions passed.
- Tournament and selection scope: 147 focused tests / 1,588 assertions passed on the final clean run.
- Account, finance and governance scope: 142 focused tests / 659 assertions passed on the final clean run. Final label/input-retention follow-up: 19 tests / 113 assertions passed.
- Full Blade compilation, route registration, PHP syntax, rendered inline JavaScript parsing and `git diff --check` passed.
- Synthetic previews: six earlier operations pages at phone and desktop width; 17 representative new pages checked at phone and desktop widths. Narrow 320px checks identified and fixed wallet, settings and duplicate-toolbar overflow. Operational controls meet the 44px target; shared site navigation was outside these scoped checks.
- Player/account search and wallet/role review dialogs were exercised without submitting consequential actions. Blocked-print recovery was checked with an isolated JavaScript harness.
- Independent quality and financial/security source reviews found no remaining introduced blocker.

These are local and isolated checks, not production delivery evidence. No commit, push, deployment, email, role change, publication or payment was performed. Existing backend photo authorization and broader role-policy concerns are outside this presentation scope; UI improvements do not establish a comprehensive security audit.
