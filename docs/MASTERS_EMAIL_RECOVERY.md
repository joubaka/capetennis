# Masters invitation email delivery and recovery

## Scope and comparison with announcements

Announcement mail is sent by an already-queued `SendBulkEmailJob`; its mailable
does not implement `ShouldQueue`. Masters invitation mail now follows the same
single-queue principle and uses the same `MailAccountManager` and global
`outbound-mail` rate limiter (14 messages/second). No announcement sending code
or policy has changed.

`SendMastersInvitationEmailJob` additionally provides:

- A fixed, serialized 24-hour retry deadline, rather than failing after three
  rate-limit releases. Three genuine exceptions still stop retries.
- `sendNow()` inside the queued job, so there is no unthrottled second mail job.
- Success recorded only after transport acceptance (not a guarantee of inbox delivery).
- Per-log overlap protection, event/payload validation and stale-invitation checks.
- Preservation of the last underlying sending error, and protection against a
  late failure overwriting a sent/skipped log.
- Dispatch after the invitation transaction commits.

Removing `ShouldQueue` from `MastersInvitationMail` also stops old generic
Masters jobs from double-queueing. Old failed-job payloads still contain their
original three-try limit; recover them using fresh Masters jobs below.

## Deployment checks

Deploy the Masters job, eligibility/retry services, recovery command, and the
changes to `MastersInvitationService` and `MastersInvitationMail` together.
No database migration or asset build is required for this change.

Restart existing production queue workers after deploying through the normal
hosting process. Verify that scheduling runs frequently rather than hourly.
Use a persistent/shared supported cache (not the development `array` cache) so
the existing global limiter, overlap locks and exception counters are shared
across worker processes. Keep the configured managed mail transport and verify
that its locked Composer dependencies are installed. Do not disable TLS checks.

Do not start workers against a local production-data import: it contains real
recipients and may contain other pending jobs.

## Preview one event (read-only)

```sh
php artisan masters:retry-failed-emails 254
```

On the September 3 imported snapshot, Wilson Masters 2026 has 54 failed original
invitations: 46 eligible, seven already paid/confirmed, and one awaiting payment.
Counts may change on production; preview there before approving a resend.

## Queue recovery only after approval, on the intended production host

```sh
php artisan masters:retry-failed-emails 254 --queue
```

This reuses existing failed Masters email logs and creates fresh job payloads.
It leaves historical `failed_jobs` records intact, does not reset invitation or
payment state, and never selects ordinary event/announcement emails. It skips
accepted/paid/removed/declined invitations, missing/expired deadlines, and
matching invitation emails already queued or sent. Duplicate failed logs for
the same invitation/kind are not queued twice. Repeating the command does not
requeue emails already scheduled. Eligibility is checked again when sending.

Do not use `queue:retry all` or send the entire original invitation batch again.

## Verification

Focused tests use isolated in-memory SQLite data and in-memory mail transports;
they do not send mail or modify the imported `ct` database.

```sh
php vendor/bin/phpunit tests/Unit/MastersInvitationEmailTest.php tests/Unit/BulkMailableTest.php tests/Unit/SendBulkEmailJobRateLimitTest.php tests/Unit/MailAccountManagerTest.php
```
