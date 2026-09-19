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
in it. Every run requires a visible `approved_migrations` value. The read-only
preflight fails when a pending target migration is absent from the release
allowlist, an approved path is not pending, or the approved and pending sets
differ. The Wilson Masters incident reconciliation migration is never inserted
or approved automatically: if it is pending, its full path must be deliberately
included in that run's input after reviewing its production impact. The GitHub
environment approval is not, by itself, migration authorization.
