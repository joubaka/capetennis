# Verification playbooks

Match verification to the changed risk. Never describe an unrun check as passing.

## Baseline

- Record `git status --short`, branch, and HEAD.
- Inspect the scoped diff and run `git diff --check` after edits.
- Run PHP syntax checks on changed PHP files.
- Run `php scripts/check-debug-calls.php app database routes` when checking for executable `dd()`, `dump()`, or `var_dump()` calls.
- Confirm relevant route registration and compile Blade views when routes or views change.

## Focused Laravel tests

- Use the configured `ct_testing` database and run database-refresh suites serially.
- Keep `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=ct_testing`, and `LOG_CHANNEL=null` explicit when the environment is ambiguous.
- Use a writable `VIEW_COMPILED_PATH` if Windows permissions block compiled views.
- Start with the smallest relevant test class or filter. Run the full feature suite for cross-cutting registration or payment changes when the database environment is known safe.
- Financial changes need coverage for authorization, cross-event isolation, idempotency, exact amounts, and record counts.

## Browser QA

For affected journeys, verify a fresh page at narrow phone, wider phone, tablet, and desktop widths. Check:

- loading, empty, validation, error, success, and selected states;
- fixed-header overlap, clipping, horizontal overflow, modal/dropdown stacking, and touch targets;
- the actual deployed or locally served asset version, not only the source file;
- role and privacy boundaries using legitimate accounts and memberships.

Browser QA is separate from Blade compilation and HTTP feature tests. Live payment, mail delivery, and physical-device behavior require their own evidence.

## Release readiness

- Inspect `deploy.config`, the exact proposed commit, pending migrations, and the approved `MIGRATION_PATHS` list.
- Do not add every pending migration automatically.
- Record required asset build, queue worker, scheduler, cache, and mail-rate prerequisites.
- A push or server-side pull is not deployment proof. Record post-deployment route, page, migration, worker, and smoke-test evidence separately.
