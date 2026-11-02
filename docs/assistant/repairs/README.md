# Repair knowledge library

This library captures verified fixes for reuse without another AI call. It is a maintainer lookup, available locally with:

```powershell
php artisan assistant:repair-guide
php artisan assistant:repair-guide "round robin"
php artisan assistant:repair-guide RK-001
```

Search is deterministic, case-insensitive and requires every search word. It reads only the curated, versioned catalogue. It does not read chats, contact an AI, train a model, execute repairs, or add a repair action to the event assistant UI. No match means no verified guide was found, not that the request is impossible.

## Record a newly verified fix

1. Reproduce the problem using fictional regression data and identify the owning code. Search this library and the relevant domain library first.
2. Fix the smallest boundary, preserve authorization and event isolation, and run meaningful regression checks.
3. Copy the fields from [the template](template.md) into `catalogue.json`, or update an existing matching record. Describe recognition, cause, scope, repair, protected state, source paths, tests, and evidence limits. Catalogue entries contain no personal data, credentials, raw chats or transient production values.
4. Run `php vendor/bin/phpunit tests/Unit/Assistant/AssistantRepairLibraryTest.php` and the referenced regression. Record exactly what passed and what remains unverified. A reference alone is not passing evidence.
5. Report local, committed, pushed, deployed and live-verified states separately. A recorded historical repair never authorizes repeating a production mutation.

The catalogue allows up to 100 records and is read with a 256 KiB size limit. Keep entries concise. Domain-specific detail stays in the existing domain library, with references here instead of duplicated repair scripts.

## Promote a guide to an assistant action

`guide_only` deliberately means there is no executable repair attached. To support an action, implement a separately scoped, named application capability through canonical services. Require server-side authorization and event validation, a current-state preview, appropriate confirmation, stale-state rejection, transactions, audit history and safe retries. Add regression tests for its risks and wire it into the assistant's existing action allowlist and proposal/apply workflow. Review it independently before deployment.

Never execute catalogue prose, generated code, SQL or shell commands as an assistant action. Adding a catalogue record must not grant execution authority. Unknown problems can be investigated with AI during development; only reviewed code and evidence belong in a reusable capability. Deployment and production operations retain the permissions in `AGENTS.md`.

## Future remote assistant integration

The intended next layer is: remote assistant reports a missing capability to Capester with explicitly scoped context; Capester implements and tests a local fix; an approved deployment moves the reviewed Git version to the remote application. Verify local and remote Git versions and environment-specific prerequisites after deployment. This bridge is not implemented by the repair library. It grants no implicit access to Codex chats, account history, credentials or other data, and never deploys automatically.
