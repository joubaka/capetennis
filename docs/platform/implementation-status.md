# Cape Tennis platform unification status

**Audit date:** 22 August 2026  
**Baseline HEAD:** `c337d0049dbd0e936962bb7b083cca5bb0088ae9`  
**Deployment:** not performed

This is an in-progress engineering status report. A phase is not marked complete merely because related code exists; database-backed tests, authorization checks, browser/API smoke checks, and release evidence remain required by the programme.

| Phase | Current status | Evidence / remaining gate |
|---|---|---|
| A Architecture baseline | Implemented | `current-system-map.md`; route/source inventory recorded; full PHPUnit baseline still pending. |
| B Player identity/family | Existing foundation audited; link authorization tightened | `PlayerIdentityService`, duplicate governance, merge preservation, `user_players`, ownership helpers, JTA identity tests, self-or-admin link guards, cross-family claim rejection, and `UserPlayerLinkAuthorizationTest` exist; database execution remains pending. |
| C My Tennis | Initial slice implemented | `MyTennisService`, player switcher, entries, matches, results, rankings, and timeline are present; browser/mobile and database isolation tests remain. |
| D Event command centre | Initial slice implemented | `EventOperationsService` is wired to the authorized overview with counts, warnings, readiness, lifecycle, and finance; event fixture/data-path tests remain. |
| E Event lifecycle | Read-only projection implemented | `EventLifecycleService` and 5-assertion unit test pass; mutation transition matrix still requires database-backed coverage. |
| F Draw/scheduling unification | Existing canonical infrastructure audited | `EngineRouter`, draw guards/policies, score validation, schedule conflicts, publication/locking, and parity suites exist; complete bypass audit remains. |
| G Team tennis | Existing foundation audited | Team format, tie, rubber, roster, payment, and integrity services exist; multi-format end-to-end suite remains. |
| H Results/rankings/history | Timeline slice implemented | `PlayerCompetitionTimelineService` composes published placements/rankings/team appearances; complete historical reproduction and ranking snapshot audit remain. |
| I Calendar/public experience | Initial shared source implemented | `PublicEventCalendarService` now backs JTA v1 with publication filtering and a 100-row bound; public browser parity and stable URL/cache review remain. |
| J Communication | Existing infrastructure audited | Bulk dispatcher, deduplication, retries, throttling, targeting, and email toggles exist; operational target-rule coverage remains. |
| K Finance/closure | Partial convergence implemented | Event operations and team overview use canonical ledger headline totals; in-memory derived expense rows prevent GET mutations; full reconciliation and team-finance suite remain. |
| L Governance/discipline | Existing infrastructure audited | Policies, audit redaction, integrity seals, and privacy tests exist; complete role/state/leakage matrix remains. |
| M API governance | Existing JTA v1 foundation audited | Sanctum, scopes, throttling, pagination, incremental sync, privacy and contract tests exist; final contract evidence remains. |
| N Performance/operations | Partial hardening implemented | Event registration KPIs use database counts; ledger query profiling and benchmark evidence remain. |
| O UX/navigation | Initial My Tennis navigation implemented | Navbar button/dropdown added; five role-based browser journeys and mobile rendering remain. |
| P Production readiness | Audit started | Preflight, schema, integrity, backup, release-audit, queue, and scheduler commands are registered; database-dependent execution, full suite, migration rehearsal, and final closure report remain. |

## Current changed surface

- Architecture and status documentation under `docs/platform/`
- My Tennis controller, service, timeline service, calendar service, lifecycle service, and views
- Event operations aggregation and event overview presentation
- JTA calendar delegation
- Authenticated navbar navigation
- Focused unit coverage for lifecycle projection and ownership aggregation

## Verification record

- PHP syntax checks: passed for changed PHP files.
- Composer manifest validation: passed.
- Final static pass: all eight changed PHP application files passed `php -l`.
- Route-name/source inspection: passed for the new routes and warning links.
- `git diff --check`: passed.
- `EventLifecycleServiceTest`: passed, 1 test / 7 assertions.
- `JtaCalendarContractTest` plus lifecycle test: passed, 2 tests / 14 assertions, including ETag/cache headers.
- Focused database-backed My Tennis test: passed, 1 test / 2 assertions.
- Focused database-backed player-link authorization test: passed, 3 tests / 4 assertions.
- Full Unit suite: passed, 272 tests / 519 assertions.
- Blade compilation: passed with `php artisan view:cache`.
- Feature-suite audit: the previously reported authorization/draw failures were reconciled in regression coverage. The obsolete `/schedule/create` assertion now verifies the route remains unavailable, and draw-publication tests now assign participants before asserting successful publication. The focused classes pass, 38 tests / 67 assertions.
- Database-backed My Tennis test: passed, 1 test / 2 assertions against MySQL `ct_testing`.
- Database-backed player-link authorization test: passed, 3 tests / 4 assertions against MySQL `ct_testing`.
- Environment inspection: MySQL/MariaDB services are running and PDO MySQL/SQLite are available in the current PHP runtime.
- Full Feature suite: passed, 859 tests / 2,292 assertions, with 13 environment-gated skips.
- Platform health/preflight: commands executed against the local `ct` database. Backup verification passed; preflight remains blocked by one pending migration, 807 failed local jobs, and missing audit-seal evidence. The health report also identified existing local data-integrity warnings (orphan fixtures, duplicate fixture results, unpublished locked draws, duplicate active CERs, and withdrawn rows without soft deletes); no cleanup was run.
- Browser/mobile smoke tests: not verified because the installed browser skill is missing its required runtime script in this environment.
- Deployment: intentionally not performed.
