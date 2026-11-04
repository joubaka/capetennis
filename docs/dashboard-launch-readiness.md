# Dashboard and Player Plus launch checks

## Account tools and directory tables

The account directory, role definitions, role assignments and account deletion are Super Admin tools.
Require the `super-user` role in the owning controller even when a route already
has role middleware. Hiding a dashboard tab does not authorize its JSON endpoint.
Legacy GET role mutations must remain retired; role changes use CSRF-protected
POST routes and an atomic, fail-closed audit.

Dashboard Users and Players tables use server-side DataTables pagination,
search and sorting. Keep their sortable column indexes aligned with the owning
endpoint; action and role cells are not sortable. Test page two and a search
whose only match lies outside the first 25 rows. Treat directory names, email
addresses and role names as text when rendering HTML.

Ranking publication on the dashboard is a status and a link to the canonical
series review page. It must not recreate a GET publication toggle or bypass
review, publication authorization or current ranking state.

## Player Plus assurance

New unrelated self-links are blocked by `PlayerRelationshipAccess`; staff
additions require a reason and a successful audit. Existing family links and
legacy owners remain usable but have not been retroactively verified. Neither
matching notification contacts nor sponsoring a registration or membership
proves a relationship. See [player dashboard access](player-dashboard-access.md).

A historical-link review or quarantine policy needs an explicit product decision;
do not silently revoke family access or label historical links verified. Billing
ownership and paid entitlement must remain independent of that decision.

## Required evidence

Regression entry points: `tests/Feature/DashboardLaunchSafetyTest.php`,
`tests/Frontend/dashboard-directories.test.cjs`, `DashboardDatasetBoundingTest`,
`DashboardWorkHubTest` and `AdminAccountBrowsingTest`.

- Run dashboard authorization, directory page-two/search and HTML-rendering
  regressions, plus the relevant existing dashboard and linking suites.
- Run the Player Plus purchase, wallet, subscription, payer isolation and
  management regressions in an isolated test database. SQLite evidence does not
  establish MySQL locking or concurrency behavior.
- Check routes, Blade compilation, diff whitespace and responsive browser flows.
- Before launch, verify the deployed version, exact required migrations,
  environment-specific offer settings, queue/scheduler and managed mail.
- Verify PayFast signed initial and recurring callbacks, duplicate/late payments,
  and confirmed cancellation in the intended provider test environment.

Local tests do not establish deployed behavior, provider acceptance, delivery or
live readiness. This document authorizes no deployment, migration execution,
outbound message, payment, role change or production data operation.
