# Cape Tennis current system map

**Baseline:** 21 August 2026  
**Repository:** `main`  
**HEAD:** `c337d0049dbd0e936962bb7b083cca5bb0088ae9`  
**Scope:** Phase A architecture and integrity baseline. This document records the current implementation; it does not change runtime behavior.

## Inventory

The baseline contains approximately 104 controllers, 163 Eloquent models, 57 service files, 110 migrations, and 123 test files. The route collection is assembled primarily from `routes/web.php` and `routes/api.php`, with Laravel, Fortify, Jetstream, Livewire, Sanctum, and Debugbar routes also present. The complete route collection should be captured with `php artisan route:list --json` for each release audit; the named application surfaces are summarized below.

## Architecture diagram

```mermaid
flowchart LR
    U[Guest / Player / Parent / Convenor / Admin] --> WEB[Web routes and Blade / Livewire UI]
    I[JTA integration client] --> API[/api/v1/integrations/jta]
    WEB --> C[HTTP controllers]
    API --> C
    C --> AUTH[Policies, role middleware, event ownership, Sanctum scopes]
    C --> APP[Application and domain services]
    APP --> ID[PlayerIdentityService / PlayerDuplicateService]
    APP --> ENTRY[EntryService / eligibility]
    APP --> PAY[PaymentOrchestrator / TeamPaymentService]
    APP --> REF[Refund request and execution services]
    APP --> DRAW[Draw, publication, lock, scoring, scheduling services]
    APP --> TEAM[Team draw, tie, rubber and roster services]
    APP --> RANK[Ranking calculation, rebuild, publication and audit]
    APP --> COMM[Bulk mail dispatcher, jobs and listeners]
    APP --> DISC[Discipline and eligibility services]
    ID --> DB[(Player / user / family-related records)]
    ENTRY --> DB
    PAY --> LEDGER[(Wallet transactions / payment and finance ledger)]
    REF --> LEDGER
    DRAW --> COMP[(Draws / fixtures / scores / schedules)]
    TEAM --> COMP
    RANK --> HIST[(Results / ranking scores / publication snapshots)]
    COMM --> QUEUE[(Queue and mail delivery)]
    DISC --> AUDIT[(Audit and governance records)]
    APP --> AUDIT
    CMD[Scheduled commands, cleanup, integrity and preflight] --> APP
```

## Capability classification

| Capability | Current canonical entry point | Status | Notes / risk |
|---|---|---|---|
| Player identity and duplicate review | `PlayerIdentityService`, `PlayerDuplicateService` | canonical | Multiple historical creation paths still require audit in Phase B. |
| Player profile and eligibility | `PlayerProfileController`, `PlayerEligibilityService`, entry eligibility services | canonical | Profile, eligibility and registration are separate workflows. |
| Individual entry | `EntryService`, registration controllers | canonical | Financial values must continue to resolve from the order. |
| Team entry and payment | `TeamPaymentService`, team controllers | canonical | Team format and roster paths remain broader than individual entry. |
| PayFast, wallet and hybrid payment | `PaymentOrchestrator`, registration payment services, wallet ledger | canonical | Controllers must not mutate financial state directly. |
| Withdrawal and refunds | `RefundRequestService`, `RefundExecutionService`, registration refund controllers | canonical | Pending liabilities and completed reversals must remain distinct. |
| Draw generation and mutation | `DrawGenerationService`, draw domain services, `DrawMutationPolicy` | transitional | Legacy controllers and newer domain draw paths coexist. |
| Team draw / tie / rubber | `TeamDrawGenerationService`, `TeamTieGenerationService`, validation and assignment services | canonical | Engine is mature but needs full multi-format acceptance coverage. |
| Score validation and scheduling | `ScoreValidationService`, `ScheduleConflictService` | canonical | Some legacy controller paths need routing audit in Phase F. |
| Results and publication | draw/result controllers plus publication services | transitional | Publication and historical visibility need one timeline in Phase H. |
| Ranking and series ranking | ranking calculation, rebuild, publication and audit services | canonical | Legacy ranking command/path remains active and must be isolated. |
| JTA integration | versioned JTA controller and integration export services | canonical | `/api/v1/integrations/jta` is the compatibility boundary. |
| Communication | `BulkMailDispatcher`, queued mail jobs, listeners, team communication | canonical | Operational targeting and retry observability are Phase J work. |
| Discipline and governance | disciplinary services, policy, audit listeners and seal commands | canonical | Public/private data separation requires continued leakage tests. |
| Integrity and operations | schema, draw, finance, cleanup and platform preflight commands | canonical | Commands are numerous; release checklist consolidation is Phase N/P. |
| Public calendar and event discovery | public event/calendar controllers and existing event models | transitional | JTA calendar and public surfaces need source-of-truth verification in Phase I. |
| Unified player/parent dashboard | existing profile/registration surfaces | apparently unused | No confirmed canonical `My Tennis` aggregation service; Phase C must add it. |
| Unified event command centre | existing event admin, draw, finance and refund screens | apparently unused | No confirmed single readiness aggregation surface; Phase D must add it. |

## Lifecycle map

The implemented data flow is distributed across existing models and services:

`User / player profile -> eligibility -> event/category entry -> order/payment -> draw or team draw -> fixture/tie/rubber -> score/result -> publication -> ranking -> JTA exports`.

Financial and operational flows run alongside it:

`payment method -> wallet/payment ledger -> withdrawal -> refund request -> refund execution or liability -> event reconciliation`.

Communication is delivered through queued jobs/listeners, while audit listeners, disciplinary services, integrity commands and platform preflight provide cross-cutting governance.

## Route and authorization boundary

- Web routes cover public event, draw, ranking and registration pages; authenticated player/profile, payment, withdrawal/refund, draw administration, team administration, finance, discipline and audit surfaces; and role-specific admin/convenor operations.
- API routes include authenticated draw operations and the Sanctum-protected, throttled JTA v1 integration endpoints for calendar, player resolution and results/rankings.
- Authorization is split between `Authenticate`, role middleware, policies such as `RegistrationPolicy`, `DrawPolicy`, `TeamDrawPolicy`, `SeriesPolicy`, `WalletPolicy` and `DisciplinaryCasePolicy`, plus service-level ownership and state checks. This split is a review target in Phases B, E, F, L and M.
- Debugbar/ignition routes are framework development surfaces and must remain unavailable or disabled in production configuration.

## Migration and schema baseline

There are 110 migration files. The schema includes users/roles, players and profiles, events/categories/registrations, teams and team competitions, orders/payment records, wallet transactions, draws/fixtures/scores/schedules, ranking/series records, refunds, audit/governance and JTA integration data. Migration ownership and duplicate vendor-wallet migration risk are documented separately in the payment architecture notes; Phase P must perform a disposable migration rehearsal and inspect rollback safety rather than blindly running all pending migrations.

## Known duplicate or legacy paths

1. Legacy individual draw controllers and newer draw-domain services coexist.
2. Team Draw v2 services coexist with older team fixture/result paths.
3. Ranking services coexist with legacy rebuild commands and historical controller calculations.
4. Public calendar/event pages and the JTA calendar export are separate read surfaces over overlapping event data.
5. Profile, registration, payment, withdrawal/refund and admin operations are separate UI journeys with no single player or event aggregation layer.
6. Cleanup and repair commands are valuable operational tools but must remain explicit, audited and outside normal request paths.
7. `EventAdminController::buildFinanceData()` retains legacy team-event presentation, but its gross, PayFast-fee, and Cape Tennis-fee totals now receive canonical `FinancialLedgerService` values through the event operations path. Derived system expense rows are now in-memory presentation rows, so the overview no longer mutates expense history during a GET.

These paths are classified as transitional or legacy-but-active until usage and data parity are proven. No legacy path is removed by Phase A.

## Phase A acceptance evidence

- Current branch: `main`.
- Current HEAD: `c337d0049dbd0e936962bb7b083cca5bb0088ae9`.
- Working tree was clean at baseline inspection.
- Route inventory was successfully generated with `php artisan route:list --json`; the collection includes web, API, JTA v1, authenticated draw, registration/payment/refund, team, ranking, and public event surfaces.
- Source inventory confirmed: 104 controllers, 163 models, 57 service files, 110 migrations, 123 test files.
- Full PHPUnit baseline: **not yet recorded**. The initial command was interrupted before producing a result and must be rerun as part of the release audit.
- No runtime or schema changes are included in Phase A.

## Phase A residual risks

- The route collection is large and contains unnamed/legacy routes; route ownership and authorization need a machine-readable audit in Phase M/P.
- The exact event-state transition matrix is not yet centralized.
- Multiple draw, ranking and public-calendar paths require parity tests before consolidation.
- A family/guardian relationship layer is not confirmed as a single canonical model and is deferred to Phase B.
- Browser/mobile journey evidence is not part of this source-only baseline and is required in Phases C, D, I and O.
- The event command centre now uses database counts for registration KPIs instead of materializing every registration; ledger row volume remains a measured Phase N optimization target.

## Unification work added after the baseline map

The first implementation slices now present in the working tree are:

- `MyTennisService` and the authenticated `my.tennis` route, which aggregate only player-owned profiles (pivot plus legacy `players.userId` links), existing entries, published draws and existing payment/status accessors.
- `EventOperationsService`, wired into the authorized event overview, which composes bounded registration/draw queries, `DrawReadinessService`, and `FinancialLedgerService` into counts and severity/action warnings without mutating financial or competition state.
- `PlayerCompetitionTimelineService`, used by My Tennis, which composes bounded entries, published placements, ranking scores, published series rankings, and published team appearances from their existing canonical records.
- `PublicEventCalendarService`, used by the JTA v1 calendar endpoint, which provides one publication-safe, bounded upcoming-event source while preserving the existing v1 response contract.

These additions are transitional read-model surfaces until database-backed regression tests and browser journeys are run against a working test environment.

## Subsequent-phase evidence audit

The repository already contains substantial implementations for the later phases and they should be extended rather than replaced:

- **Lifecycle / draw unification:** `EngineRouter` selects `legacy`, `hybrid`, or `canonical` per draw/event; `DrawGuard`, draw policies, publication/lock services, `ScoreValidationService`, and `ScheduleConflictService` form the current mutation boundary. Existing parity, pilot, lifecycle, bracket, rollback, authorization, and scheduling tests cover important transitions.
- **Team tennis:** team draw generation/regeneration, tie generation, rubber assignment, auto-assignment, validation, team payment, and team communication services are present. The remaining programme risk is end-to-end coverage across multiple format definitions and replacement/withdrawal paths.
- **Results/rankings:** ranking calculation, rebuild, publication, audit, series publication, and JTA result/ranking exports are present. Historical player timeline aggregation is still not a single canonical read model.
- **API governance:** JTA v1 routes are Sanctum-authenticated, scope-checked, throttled, audited, and covered by contract/privacy tests. A generic public API remains intentionally out of scope.
- **Governance / discipline:** `DisciplinaryCasePolicy` separates super-user, event-admin, assigned/recused panel, reporter, and owned-player access; `AuditWriter` redaction/integrity and `audit:seal` provide tamper evidence. Existing `AuditTrailTest`, disciplinary workflow, JTA privacy, and API authorization suites are the current evidence boundary. Sensitive discipline fields must remain outside public resources.

This evidence prevents later phases from duplicating already-canonical services; future changes should target the remaining gaps and add tests at their existing boundaries.
