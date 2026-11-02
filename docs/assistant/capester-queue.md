# Reviewed Capester support handoff

The event assistant can link to **Send a task to Capester** when `CAPESTER_ENABLED=true`. A Super User with current event-management access types and reviews a description (maximum 2,000 characters), confirms transfer, and sends it. Manual submission contains this explicit text, its event reference and operational delivery metadata. It does not automatically copy conversations, player records, attachments or the current desktop chat. Users must keep credentials out of manual descriptions.

The separate, off-by-default [automatic capability-improvement handoff](capability-improvement.md) requires an explicit actor opt-in allowlist. It can create a background note containing one linked failed instruction excerpt and fixed reason/event/message metadata, or metadata only when the instruction looks credential-like. It never copies full transcripts, email drafts, feedback comments or provider output. This standing opt-in is distinct from manually reviewing a support form and does not opt in other Super Users.

The event history shows only the signed-in actor's requests for that event, 20 per page. Each actor can have at most 20 pending/leased requests. Submission UUIDs make identical retries safe; a different payload cannot replace an existing submission. `received` means delivered to the private local inbox for review, **not fixed**. Support text remains untrusted data and is never sent to AI or executed by this receiver.

## Local receiver

The local machine polls explicitly, once per invocation:

```powershell
php artisan assistant:capester-receive
```

There is no listener on the local computer, scheduled polling, automatic code change or deployment. Configure `CAPESTER_BASE_URL` and `CAPESTER_RECEIVER_TOKEN` only on the local receiver after explicit authorization. Defaults are empty; this implementation creates no token and changes no actual `.env` files. The receiver uses its configured URL, never an address from the response. HTTPS verifies TLS and redirects are disabled. Literal loopback HTTP is allowed only because the receiver runs in local/testing environments; production receiver execution is refused. Claim and acknowledgement responses use bounded 16 KiB stream reads with finite network timeouts.

Support is atomically written as inert JSON to `storage/app/private/capester/inbox/{sha256-origin}/{request-uuid}.json` before acknowledgement. Lease credentials are excluded. Directories/files request owner-only Unix permissions; Windows operators must also verify private NTFS ACLs. Keep this directory out of web roots, public storage links, shared exports and unprotected backups. Exact duplicate artifacts are safe; a different existing artifact fails without overwrite. The receiver does not print descriptions, bearer tokens, lease tokens or raw provider/HTTP diagnostics.

## API authority and retries

`POST /api/v1/capester/claim` and `POST /api/v1/capester/{uuid}/ack` require a real Sanctum bearer personal access token containing the **explicit** `capester:receive` ability, whose current owner is a Super User. A wildcard-only token or browser session is insufficient. HTTPS is mandatory on the production server. Every claim is restricted to the token owner's own submissions and rechecks current event-management access. Revoking a token, role or event access blocks the corresponding future operations.

A row-locked claim grants a five-minute lease with a random credential stored only as a hash. Expired unacknowledged leases can be reclaimed. An acknowledgement must match the owner, request UUID, token record, credential and current event access. An active lease must remain unexpired. After a terminal acknowledgement, an identical acknowledgement with the original credential/token remains safe even after expiry; a different result cannot replace it. Old credentials fail after a reclaim. Support accepts only `received`; synthetic hello accepts only `hello_passed` or `hello_failed`.

Event access follows the existing `event.manage` Gate, including its global Super User override; this feature adds no new event-admin membership restriction. Actor ownership still isolates queue claims, acknowledgements and history across Super Users. Descriptions and lease hashes are audit-redacted. The support model uses no free-text title/name/description fields that could bypass redaction through audit subject labels. Do not add those fields without reviewing the audit path.

## Synthetic connection test

Only local/testing UI exposes a synthetic connection-test control, and production refuses creation, claim and acknowledgement of hello requests. Hello runs [the existing fixed-prompt Codex handshake](capester-connection.md) and acknowledges its fixed result; the queued description is never used as a prompt. No dedicated production diagnostics endpoint is introduced. A successful hello proves the queue-to-local-to-Codex path for a fixed response; it does not prove arbitrary repairs, desktop-chat access or production deployment.

## Verification and rollout prerequisites

Run the isolated suite serially with `php vendor/bin/phpunit -c phpunit.capester.xml`. It pins MySQL `ct_testing`, loopback host, uncached configuration/routes and an isolated compiled-view directory. Tests use fictional data, fake HTTP and mocked Codex. Actual Codex, browser QA and live-server access are separate evidence.

Local verification on 2026-11-02:

- Independent isolated suite: 57 tests, 340 assertions. The required standalone `DeploymentConfigTest.php --fail-on-skipped` check passed 40 tests, 188 assertions.
- `php scripts/qa/capester-handoff.php --live-codex` passed queue → receiver → real fixed Codex ping → acknowledgement. It uses synthetic fixtures inside a rolled-back `ct_testing` transaction and intercepts application HTTP through the actual API kernel. It does not test remote DNS, TLS or network reachability, and it performs no migrations. Run the isolated suite first to prepare the test schema. This opt-in check consumes Codex account usage and must run serially with database tests.
- The smoke check exports fictional rendered Blade to `storage/framework/testing/capester-queue.html`. `tests/Browser/capester-queue.ps1` passed native form validation, explicit consent and horizontal-overflow checks at actual CSS viewport widths of 320, 390 and 1280 pixels. Screenshots were inspected at phone and desktop widths. The fixture uses repository CSS and removes application scripts; it submits no forms and does not verify an authenticated live browser session.
- Four routes registered, Blade compilation passed using an isolated compiled-view path, and `git diff --check` passed. This work ran the new migration only in `ct_testing` and made no normal-local or production schema/configuration changes.

For an explicitly approved rollout, deploy the reviewed Git version and run **only** `database/migrations/2026_11_02_150000_create_capester_support_requests.php` if this queue is the sole new schema requirement. Its exact path is included in `deploy.config` `MIGRATION_PATHS`; allowlisting is not execution permission. Review any other intended feature prerequisites separately. Keep the queue disabled until its schema exists. Confirm HTTPS/proxy handling and private inbox storage/ACLs; authorize and configure server feature enablement, a narrowly scoped Sanctum token and receiver URL/token separately. Do not place the receiver token on the remote server or in browser JavaScript. Verify matching Git versions, route registration and Blade compilation before enabling, then use a fictional support request for authorized delivery verification. Production never runs synthetic hello or the local receiver.

Actual fixes still require bounded implementation, regression tests, independent review and separate deployment authorization. Add verified reusable knowledge to [the repair library](repairs/README.md); delivery alone never grants repair execution or production authority.
