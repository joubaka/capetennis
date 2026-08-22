# Cape Tennis unified-platform release checklist

Use this checklist only after the named phase changes are reviewed and committed. Do not treat command availability as a passing result.

## Repository and build

- [ ] Confirm branch and commit: `git status -sb`, `git rev-parse HEAD`.
- [x] Validate Composer manifest: `composer validate --no-check-publish`.
- [x] Run PHP syntax checks on changed files.
- [x] Run `git diff --check`.
- [ ] Build frontend assets with the project’s approved production build command.
- [ ] Render and inspect the five role journeys at mobile and desktop widths.

## Database and migrations

- [ ] Start the approved disposable test database; do not use production data for migration rehearsal.
- [ ] Run the focused identity, ownership, entry, payment, refund, draw, team-draw, ranking, API, finance, governance, and new My Tennis tests.
- [ ] Run the complete PHPUnit suite and record failures by test and environment.
- [ ] Run `php artisan schema:audit` and `php artisan schema:integrity-check`.
- [ ] Inspect pending migrations and rehearse forward/rollback paths safely.
- [ ] Run duplicate-player, orphan-fixture, orphan-draw-child, duplicate-result, team-draw, and finance-integrity commands.

## Competition and finance

- [ ] Verify event lifecycle illegal-transition tests.
- [ ] Verify draw publication/lock/readiness and score rollback tests.
- [ ] Verify round-robin to playoff progression and team format end-to-end tests.
- [ ] Verify gross inflow, fees, refunds/liabilities, payouts, and balance reconcile with no payment-method double counting.
- [ ] Confirm event overview GET requests do not create expense or payment records.

## Security and integrations

- [ ] Run authorization/policy suite, including cross-event and cross-family isolation.
- [ ] Run public-data leakage and disciplinary privacy tests.
- [ ] Run JTA v1 contract, token scope/expiry, throttle, pagination, `updated_since`, and full-snapshot tests.
- [ ] Review PayFast signature, amount, COMPLETE-state, locking, and idempotency behavior.
- [ ] Review production config for secrets, diagnostics, queue workers, scheduler, and mail rate limits.

## Operations and rollback

- [ ] Run `php artisan platform:preflight --strict` in the release environment.
- [ ] Run `php artisan platform:health-check` after queue/scheduler startup.
- [ ] Verify a recent backup with `php artisan platform:verify-backup`.
- [ ] Record deployment commit, migration list, environment variables, queue/scheduler requirements, and rollback commit.
- [ ] Perform post-deployment smoke checks only after explicit deployment approval.

## Current environment note

At the 21 August 2026 audit, `.env.testing` targeted MySQL `ct_testing`; both WAMP database services were stopped and the current PHP runtime reported no usable MySQL/SQLite PDO modules. Database-dependent checks therefore remain unverified and must not be represented as green.
