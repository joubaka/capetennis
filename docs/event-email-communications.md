# Event email communications

Team events and Interprovincial Trials have a Communications page available to authorised event managers at every event stage. Regional managers remain limited to their assigned region. Closing registration, unpaid entries and unpublished nominations do not disable general communication.

Choose everyone, nominees, a region, a team or an individual, then refine the player status. Interpro teams use category and tier selections. Search the bounded individual selectors by name when needed. Player messages include linked parent contacts; manager messages contain only the selected roster. Missing or invalid contacts appear in the preview.

Review the exact recipients and every message before approving. Contact, roster or message changes invalidate a preview. Approval queues the reviewed snapshot once. Registration, withdrawal and refund messages wait as system drafts for review. Reminder schedules identify when review is due; they never send automatically. Existing managed mail transport and the `outbound-mail` rate limit remain in use.

## Knowing what was sent

Each approved batch has a recipient report. Invitation reports are also available from Communications.

- **Queued / Sending:** awaiting transport or currently processing.
- **Mail server accepted:** the actual SMTP or SES transport returned successfully; the report stores its timestamp and message ID.
- **Sandbox accepted:** the sandbox transport accepted the test message.
- **Acceptance unverified:** an older record, an in-memory/log transport, or successful transport whose receipt could not be stored. This is not proof of server acceptance.
- **Failed / Skipped:** the attempt failed, or eligibility/integrity checks prevented sending.

The batch reports success for every approved recipient only when the expected count matches the server-accepted records. Server acceptance confirms handoff for onward delivery, not arrival in an inbox or reading. Delivery and bounce feedback are not integrated.

Retry only a failed email through its exact preview and fresh approval. Accepted or uncertain attempts cannot be automatically retried. Old invitation logs without an immutable signed snapshot require a fresh message review.

## Database changes

The implementation adds only these migrations:

- `2026_10_02_120000_add_transport_evidence_to_bulk_email_logs.php`
- `2026_10_02_120100_create_event_communication_batches_table.php`

Local implementation does not deploy these migrations or send production email.

For isolated local tests, `CT_TEST_ARRAY_TRANSPORTS=1` maps every named mail transport to an in-memory array transport, including names selected by the managed mail service. Use it with an isolated testing database.

`CT_TEST_SQLITE_FAST=1` disables synchronous disk writes for disposable SQLite test databases. It is an optional local test-speed setting and must not be used to assess crash durability.
