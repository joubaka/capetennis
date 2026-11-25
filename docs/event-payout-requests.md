# Event payout requests

An assigned event administrator with the admin role, or a Super User, can open
**Request payment / complete event** from the shared event workspace header.
This requests review of event registration funds; it does not instruct a bank
transfer, record a payout, debit a wallet or send mail.

Interim requests accept a rand amount strictly below the current positive
registration balance, with no fixed percentage. Final requests use the entire
remaining registration balance calculated server-side and require an explicit,
audited completion attestation. Historical status text, dates or published results
alone do not qualify. Completion cannot precede the scheduled end date (start date
if no end date); changed dates or a reopened status invalidate the attestation
until completion is confirmed again. The operational status becomes completed,
without changing registration settings or publishing results.

The canonical financial ledger supplies the provisional registration balance,
after recorded payouts. Clothing is excluded. Pending registration refunds block
requests. This balance is held event funds, not an individual director's profit
share or entitlement. Super User must separately reconcile expenses,
reimbursements, recipient identity, profit shares and refunds through the existing
finance workflow before recording an actual payment.

The Super Admin financial dashboard links to the paginated review queue. Pending
and reviewed requests prevent another request for the same event. Mark reviewed
means review only. Reject or close administratively requires a note and releases
the request for a fresh submission; neither action proves payment. Record an
actual payout separately through the canonical finance service before a later
request, so the ledger reflects the reduced remaining balance. No requester is
automatically selected as the recipient.

Event locks serialize request creation and completion; actor-bound submission
tokens make retries safe, and event-bound signed balance previews reject stale
submissions. Original request details cannot be edited or deleted. Canonical
audit failure rolls back completion, creation and review. Completion and request
rows are operational records, never settlement evidence.

Implementation: `EventPayoutRequestService`, `EventPayoutRequestController`,
`EventPayoutRequest`. Regression: `tests/Feature/EventPayoutRequestTest.php`.
Required migration: `database/migrations/2026_11_24_130000_create_event_payout_requests.php`.
Allowlisting this path in `deploy.config` does not authorize production execution.
