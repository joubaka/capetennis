# Daily Cape Tennis caretaker workflow

This workflow is read-only. It reports meaningful changes and risks; it does not repair them.

## Checks

1. Capture branch, HEAD, upstream divergence, worktree status, and recent commits.
2. Review changed files and classify affected domains: identity, registration, finance, withdrawal/refund, draws/fixtures, rankings/publication, mail, or deployment.
3. Check whether `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, routes, migrations, `deploy.config`, or CI configuration changed.
4. Inspect recent available CI status without triggering workflows.
5. Compare new migrations with `deploy.config` only to flag mismatches; never edit the allowlist automatically.
6. Look for conflict markers and obvious secret-bearing files entering Git status. Run `php scripts/check-debug-calls.php app database routes` for executable debug calls; do not use a text search that flags comments or legitimate methods.
7. Read existing test results only when available. Do not run the complete suite as part of the daily check.
8. Rank findings as critical, high, normal, or informational and name the evidence.

## Notification policy

Stay quiet if there is no meaningful change. Notify when any of these occurs:

- a new failure, regression signal, or merge conflict;
- an unreviewed financial, registration, publication, mail, security, or migration change;
- dependency or deployment configuration drift;
- a dirty worktree or unpushed work that creates a material handoff risk;
- an approval, missing prerequisite, or owner decision is required.

## Report shape

- Current state: branch, HEAD, cleanliness, and what changed.
- Priority findings: evidence, impact, and recommended next step.
- Verification: checks observed or run, with failures and gaps.
- Approval queue: consequential next actions awaiting the owner.
- Readiness: `ready for supervised work`, `needs review`, or `blocked`; never call production ready without release and live evidence.
