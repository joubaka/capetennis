# Super Admin dashboard audit — 2 November 2026

Scope: the supplied screenshot of `/backend/superadmin/workspace`, all ten workspace tabs and four Settings subtabs, the separate `/backend/superadmin` landing page, and connected financial/settings endpoints. Evidence is current local source, route registration and the supplied screenshot. Production code/version, live totals and browser performance were not verified.

Capester coordinated independent UX, quality and financial/authorization reviews. Existing unrelated worktree edits were preserved. The findings below describe the initial audit; their dispositions now include the subsequently requested local remediation.

## Findings and disposition

| Priority | Finding | Owning source | Disposition |
| --- | --- | --- | --- |
| High | Content editor save callbacks call `saShowToast` outside its lexical scope; feedback throws and the save button can remain disabled. | `resources/views/backend/superadmin/index.blade.php`, Quill callbacks and ready handler | Included in local UI correction. |
| High | Save All serializes disabled autosaving checkboxes, omitting their values. The bulk settings endpoint interprets missing booleans as false. A competing full save can overwrite a toggle. | Same view; `SettingsController::store` | Toggle, annual-price, template and content requests now coordinate with full-form save, with duplicate bulk submits blocked. Monthly-only fields remain separate. |
| High | Legacy payout creation lacks request idempotency and writes its activity separately. Repeated submissions can create duplicate payout records. Deletion removes legacy payout rows, although an activity snapshot is retained. | `SuperAdminFinanceController`; `EventPayout` | Canonical service now records exact, event/actor/payload-bound idempotent payouts with transaction-bound critical audit. Confirmed, reasoned opposite entries replace deletion. Original rows remain immutable. Signed corrections propagate through event and director balances. |
| Medium | Every workspace visit builds hidden-tab datasets, including annual event ledgers, mail reporting, wallets, refunds, audits and discipline. Pending refund and agreement lists remain unbounded. | `SuperAdminController::workspace` | Main navigation now selects a server page. Hidden detail queries and markup are absent. Queues are server-paginated; Finance streams the full year in chunks independently of displayed rows. No measured production timing claim. |
| Medium | Wallet deep-link activation runs before its lazy table listener is registered. | Workspace script | Included in local UI correction. |
| Medium | Disciplinary tile stretches beside the taller Player Plus controls, creating the screenshot's empty area. | Registration Settings markup | Included in local layout correction. |
| Medium | Monthly renewal fields require a separate save, but Save All suggests broader coverage. | Workspace; `backend/settings/partials/player-plus-monthly.blade.php` | Clarify save boundaries; preserve monthly endpoint and pricing behavior. |
| Medium | Players badge reports a maximum 15-row preview; wallet results stop at 500; completed bank refunds stop at 250 per type and wallet withdrawals at 250. These are not complete totals. | Workspace and controller query limits | True aggregate counts and 25-row server pagination replace silent caps for players, wallets, agreements, refunds and suspensions. Each refund type retains its own paginator. |
| Medium | Bulk settings writes are not transactional; generic model logs do not provide one safely redacted, transaction-bound settings operation. | `SettingsController`; `SettingsMutationService`; `RecordAuditRequest` | All six endpoint families now use one authorized, allowlisted transactional service with safe old/new business evidence. An unavailable audit rejects changes. Cache invalidation follows commit. Request attempts/completions store field names only. Generic model audit already existed; the initial claim of no settings audit was too broad. |
| Low | Manual debit retry checks available balance before recognizing a prior idempotent success, so a successful debit retry can return insufficient funds. | Both manual wallet controllers; `ManualWalletAdjustmentService` | Canonical locked duplicate lookup precedes balance checks; actor/payload mismatch is rejected. Both dashboard and profile entry points preserve their error contracts. |
| Low | Dismissing the reusable Settings alert removes its DOM element, preventing subsequent feedback. | Workspace alert | Included in local UI correction. |
| Low | Ten equal-weight main tabs, nested subtabs, six stacked mobile metrics, and inconsistent location restoration obscure the next action. | Workspace navigation/styles/script | Main destinations use shareable GET links, Settings/Audit retain allowlisted local subtab context, and mobile metrics stay in two columns. Audit tables initialize only when visible. Overview profile-health categories are exclusive. |

## Coverage by workspace

| Area | Audit observations |
| --- | --- |
| Overview | Only its health/recent-user details load. Four profile-health categories are mutually exclusive; shell badges use true aggregates. |
| Finance | Only this selection builds canonical yearly ledgers. Displayed events are paginated; complete yearly totals are accumulated in 25-event chunks. |
| Withdrawals | Only the selected pending/completed/wallet queue loads, with independent paginators and complete queue totals. Financial state and refund services are unchanged by pagination. |
| Agreements | Agreement management is server-paginated. The active agreement drives acceptance and pending-player statistics. |
| Players | The full attention queue is reachable through pagination; badge counts are not page counts. |
| Audit & Activity | Activity limited to 100, login audit to 50; these are recent windows, not full historical audit search. Dedicated audit page exists. |
| Mail history | Server pagination and selected-tab context are implemented. History selects metadata rather than stored bodies, signed links or raw errors. |
| Settings | All form subtabs remain present on this selected page to preserve bulk-save semantics. Toggle, annual-price, template and content saves coordinate with bulk save; monthly-only keys remain separate. History links open the canonical Audit Centre. |
| Wallets | Ledger-derived balances, server search and pagination. Manual adjustments use canonical service and idempotency keys. Editing/deleting transactions and deleting wallets remain blocked. |
| Disciplinary | Statistics and recent violations load only here; active suspensions are paginated. Disabling the feature preserves historical records and active sanctions. |

## Confirmed protections and limits

Workspace, financial, wallet and settings routes require `super-user`. PayFast diagnostics abort in production and redact sensitive fields. This review found no confirmed route authorization bypass. It does not establish that every connected workflow is free of defects.

The separate local landing page already offers a lighter navigation structure, bounded recent activity and deferred balance loading. This does not prove the supplied live site runs the same revision.

No production access, real payments, outbound messages, production migrations, commits or deployment were performed. Synthetic test databases exercise settings and financial changes. Migration application and final verification are recorded separately below.

## Financial correction contract and migration

Recording a payout records evidence of an already-paid amount; it does not transfer funds. A correction requires confirmation and a reason, appends an exact opposite linked record, and does not represent money returned by a bank. Existing linked director/clothing settlements require their own reconciliation and cannot be corrected through this generic endpoint.

Generic and legacy recipient-bound payouts and their signed corrections now reduce or restore director balances consistently. Unknown, unmatched, orphaned, conflicting or ambiguous recipient allocations block affected settlements; names are never used to infer financial identity.

The required schema change is `database/migrations/2026_11_02_220000_add_event_payout_corrections.php` (unique nullable correction link and reason), explicitly listed in `deploy.config`. Its down/up round trip passed against isolated MySQL, and this exact migration was applied to the verified local `ct` database. Other pending migrations were not run. This allowlist is not production execution authority.

## Settings audit contract

`SettingsMutationService` authorizes `super-user`, whitelists keys and their groups, reads locked database snapshots in sorted key order, and commits settings and a canonical `settings.updated` record together. Actor, changed keys, and scalar operational before/after values are retained. Template/legal/contact/logo values use hashes and lengths; raw submitted values are also excluded from attempted/completed settings-request logs. Existing model auditing for unrelated callers is preserved. Cache invalidation follows successful commit; the normal cached `SiteSetting::get` behavior is unchanged.

## Local implementation validation

- Final focused MySQL run: **130 tests, 1,088 assertions passed**. It includes settings mutation/privacy, all workspace loading/pagination, authenticated rendering, refund summaries, mail history, 17 payout integrity tests, both manual-wallet entry points, director/general/full-net reconciliation, ledger parity and audit history. A dedicated local test database kept application/shared test data separate.
- **Nine executable JavaScript regressions pass**, covering save coordination, GET destinations, local subtab restoration and visible audit-table initialization. These use a VM harness, not a browser DOM.
- `DeploymentConfigTest`: **42 tests, 190 assertions passed with fail-on-skipped**, outside the Windows sandbox after its temporary-file replacement/child-process restrictions were identified. No deployment was executed.
- Edge layout QA passes at **320, 390, 768 and 1440px** for Registration settings and the synthetic payout form. Screenshots were visually inspected. Mobile table correction details have a readable minimum width and bounded height within horizontal table scrolling; the check caught and fixed an excessively tall narrow-cell layout. Capture-producing tests delete stale captures and export only after assertions pass. Scripts are stripped: this is layout/form evidence, not a real settings save, bank operation or every-tab browser acceptance.
- New Settings audit assertions compare strict field values rather than MySQL JSON key ordering. The ledger collision fixture deliberately chooses matching independent order IDs instead of assuming MySQL auto-increment counters rewind on rollback. No ledger assertions were weakened.
- Repair records RK-007 and RK-009–RK-011 document the reusable repairs; **three catalogue tests, 65 assertions pass**. Unrelated existing records, including RK-008, were preserved.
- Playwright uses the existing TeamSheet installation through process-local `NODE_PATH`; no packages were installed. Browser fixtures block non-local requests. Synthetic mail transports are arrays.
- Complete feature checks are partitioned across all 440 feature test files, with independent in-memory databases, compiled-view folders and reports. Final broad-suite outcomes are recorded separately; the focused MySQL pass does not imply every legacy feature test passes on SQLite.

The task's changes cover the selected-workspace controller/view and regressions; payout recording/correction service, canonical ledger facade and recipient projections; manual-wallet retry paths; settings mutation service/controller and request-audit privacy; the explicit migration/allowlist; browser checks; and this report/repair records. Unrelated concurrent Player Plus, ratings and agent-guidance edits were preserved. `SiteSetting::get` remains unchanged.
