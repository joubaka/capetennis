# Manual GitHub production deployment

Cape Tennis production deployments are manual. The `Deploy Production` workflow
has no `push`, `pull_request`, or post-CI trigger. An operator supplies the exact
40-character `main` commit SHA. The workflow names the `production` environment,
but GitHub does not protect it automatically: repository administrators must
configure and verify the protection described below before this workflow is used.

## One-time GitHub setup

Create a GitHub environment named `production`, add required reviewers, prevent
self-review where the repository plan supports it, and restrict deployment to
the `main` branch. Store these secrets on that environment, not as plaintext in
the repository:

- `SERVER_HOST`
- `SERVER_USER`
- `SERVER_PORT`
- `SERVER_APP_PATH` (the absolute Cape Tennis Git checkout path)
- `SERVER_SSH_KEY` (a least-privilege deployment key)
- `SERVER_KNOWN_HOSTS` (the verified server host-key line)

Do not use the workflow until required reviewers, self-review prevention (where
supported), the `main` deployment-branch restriction, every environment secret,
the pinned server host key, and the restricted SSH key have all been verified.
The server user needs access only to the Cape Tennis checkout and commands used
by `deploy.sh`; do not grant broader shell or filesystem access.

## Deploy

1. Confirm the exact commit is the current `origin/main` tip and its
   `CI — Full Test Suite` push run completed successfully.
2. In GitHub Actions, open **Deploy Production** and choose **Run workflow**.
3. Paste the full lowercase 40-character commit SHA into `deploy_sha`.
4. Review the exact target commit's `MIGRATION_PATHS` and obtain fresh approval
   for the production migrations. Paste the exact pending paths into
   `approved_migrations` as a comma-separated list with no spaces. Enter `none`
   only when the read-only preflight is expected to find no pending migrations.
   Do not copy the whole release allowlist: already-applied paths are rejected.
5. A configured production reviewer approves the environment gate. If GitHub
   does not request this approval, stop and correct the environment protection.
6. Review the job output and record the preflight result and final
   `Deployment complete: <SHA>` line.

The workflow rechecks the branch tip and successful CI run before SSH. For the
first rollout, it does not depend on an older installed `deploy-ct` command
understanding new options. It verifies the server's `origin/main`, extracts
`deploy.sh` from that exact commit, and runs that script against
`SERVER_APP_PATH`. The script rechecks the tip before taking the site down,
uses the exact target commit's preflight helper to read the production migration
table before downtime or merge, and fails unless the submitted list matches the
pending allowlisted paths exactly. It then fast-forwards only to the pinned
commit, runs only that locked list, verifies no target migrations remain pending,
and verifies `HEAD` afterward.

The deployment workflow itself does not invoke Masters payment reconciliation.
The application scheduler separately runs `masters:reconcile-payments --apply`
every five minutes; that existing production behavior is not disabled or gated
by this workflow. The deploy script's `--reconcile-masters-payments` flag is
intentionally never passed by GitHub.

`MIGRATION_PATHS` is a release allowlist, not authorization to execute everything
in it. Every GitHub workflow run requires a visible `approved_migrations` value. The read-only
preflight fails when a pending target migration is absent from the release
allowlist, an approved path is not pending, or the approved and pending sets
differ. The Wilson Masters incident reconciliation migration is never inserted
or approved automatically: if it is pending, its full path must be deliberately
included in that run's input after reviewing its production impact. The GitHub
environment approval is not, by itself, migration authorization.

For a direct server deployment, run `deploy-ct main` for a full maintenance update,
or `deploy-ct main --live` for an online update without Composer changes.
No migration prompt appears when there are no pending migrations. The `DEPLOY`
prompt is a database approval checkpoint, not an error; it happens before the
checkout changes or maintenance mode begins. Git fast-forward compatibility,
live-mode Composer changes, and Composer availability are checked before that
checkpoint.

For a reviewed direct deployment without an interactive pause, supply the exact
pending paths in plain text and pin the target commit:

```bash
deploy-ct main --expected-sha <40-character-SHA> --approved-migrations database/migrations/<first>.php,database/migrations/<second>.php --live
```

Use `--approved-migrations none` when the target has no pending migrations.
The command prints a ready-to-copy pinned command when it encounters a normal
migration approval prompt. Review the listed migrations before using it.
Plain-text approval uses the same exact-set validation as encoded approval;
supplying either option is explicit authorization for that run. Supply approval
only once. Existing GitHub automation can continue using the encoded option.
The Wilson Masters repair continues to require the deliberate encoded option.

When migration input is omitted and pending release-allowlisted migrations exist,
a direct interactive terminal prints the exact pending set and continues only when
the operator types `DEPLOY`. Piped, scheduled, CI, and other non-interactive runs
with pending migrations must supply `--approved-migrations` or
`--approved-migrations-b64`; the release
allowlist never authorizes execution by itself. The script rejects pending migrations
outside the allowlist and approval values that do not exactly match the pending set.
The Wilson Masters incident payment repair always requires deliberate
`--approved-migrations-b64` input after reviewing its impact, even in an interactive
terminal. GitHub deployments continue to require that input and their environment
approval.

The normal command takes the site offline after preflight, updates the full checkout
and locked Composer dependencies, runs the selected migrations, rebuilds Laravel
caches, syncs committed public assets and `mix-manifest.json` to the web root,
restarts queues, and brings the site online after success.
Mix assets must be built with `npm run production` and included in the release;
the server publishes those built assets rather than running Node tooling.
If a step fails after downtime begins, the site stays in maintenance mode.
Resolve the failure and complete deployment before manually running `php artisan up`.
