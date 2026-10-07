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

## Automatic match result notifications

Event Settings includes two independent switches: **Result emails** defaults off, and **Automatic page refresh** defaults on. The email switch controls participant notifications; the page refresh switch controls automatic updates while public results or standings pages are open. Neither switch changes saved scores, standings calculations or publication requirements. With page refresh off, parents can still refresh the page manually.

Enabling result emails affects later score entries and corrections, without emailing historical results. Disabling it permanently invalidates outstanding queued result messages. Re-enabling does not revive those old messages.

While enabled, completed match results and corrections automatically queue a separate message for each actual participant contact and linked parent. Partial results, unpublished events/draws/ties, invalid participants and stale results are excluded. Imported participants use their imported contact until linked to a profile, then use the profile's canonical contacts. Score re-saves do not queue duplicate messages; corrections create an immutable new revision and supersede queued older revisions. Match result emails are a narrowly verified system exception to manual communications review. Other event email retains the review workflow above.

Each message identifies the event, draw, players on each side and score, links to the public result, and asks the recipient to check accuracy and contact the court convener about an issue. Valid addresses for this event's administrators appear in Reply-To. Recipients should verify that their mail app includes every listed administrator before sending a reply; this does not use or claim a fan-out email alias. When no administrator has a valid address, the message directs the recipient to the court convener without promising email replies.

The required migrations are `database/migrations/2026_10_07_120000_create_match_result_notifications.php` and `database/migrations/2026_10_07_120100_add_event_result_controls.php`. The notifications use the **database** queue after the score transaction commits, even when the web application's default queue is synchronous. A worker processing the database connection/default queue must run for delivery; the existing managed transport and `outbound-mail` limiter apply. No database worker or transport configuration is changed by this implementation.

Notification rows retain pending/sending/sent/superseded state. A transport exception leaves the delivery claim in sending state and fails the job for manual reconciliation. An uncertain delivery is never automatically retried, avoiding duplicate emails. A sent row records successful transport handoff, not proof of inbox delivery or user confirmation. A later correction that returns to an earlier score is a new revision. Score removal suppresses outstanding notifications; removal itself does not send an email.

`CT_TEST_SQLITE_FAST=1` disables synchronous disk writes for disposable SQLite test databases. It is an optional local test-speed setting and must not be used to assess crash durability.
