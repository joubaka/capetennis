# Event finance entry and withdrawal summary

The event finance breakdown and its downloadable/emailed PDF share the
`backend.event.partials.entry-withdrawal-summary` view and the read-only
`EventEntrySummary` service. Existing event-finance permissions still apply.

Counts describe entry records, not unique players or bank receipts. Recorded
entries equal active confirmed plus active unpaid plus withdrawals. The
separate **Entries in payment records** figure retains the canonical financial
ledger's historical meaning; it must not be substituted for active participation.
The measures can differ: payment history is not a participation reconciliation.
Do not invent an adjustment or subtract a withdrawal from original paid history
merely to force the counts to agree.

For individual events, current active entries exclude soft-deleted records and
unfinished checkouts. Confirmed means `payment_status_id = 1`, including free
and administrator-confirmed entries. Withdrawal states or a withdrawal timestamp
retain history even after soft deletion. Unpaid duplicate retirements are excluded
only when their exact canonical player-profile-merge audit matches the entry,
registration and division; free-text reasons cannot suppress a withdrawal.

For team events, counts use the authoritative regular or imported roster without
counting imported mirror rows twice. Paid withdrawn orders retain withdrawal
history; unpaid cancellation/expiry timestamps do not establish participant
withdrawals. A stale roster slot covered only by a withdrawn paid order is omitted;
a new active paid registration can restore current participation. Effective
beneficiary and event boundaries apply when matching coverage.

Withdrawal refund columns partition the withdrawal total into completed, pending,
waived and no refund recorded. These counts do not alter financial totals or
execute refunds. The screen retains empty divisions in the entry table. The PDF
omits divisions with zero recorded entries, and refund tables show only divisions
with withdrawals. Key event totals precede the financial figures in the PDF;
division detail follows the financial figures so long division lists do not push
the financial overview off the first page.

**View withdrawal records** opens the existing event finance route with
`withdrawals=1`. It retains the exact `event-finance.view` gate and private,
non-cacheable response; it does not call the financial screen's expense sync.
The read-only list paginates 25 entry records, includes archived individual
withdrawals, and uses the same merge-retirement exclusion and team paid-withdrawal
rules as the summary. It resolves names for the current page only and exposes
division, withdrawal date, refund status and record provenance without player
contacts, payer details, bank information or refund actions. A team detail names
the effective beneficiary. Query parameters never grant access to another event.

Detailed and summary financial reports batch receipt-referenced order baskets
within a fresh report build. Before individual basket metadata can contribute
entry counts or wallet funding, every item must resolve to the exact event and
the receipt must match its payer and order type. Empty, mixed-event and orphan
baskets remain ineligible. This replaces repeated nested order lookups without
introducing a persistent financial cache. Query-growth and exact-amount checks
live in `tests/Feature/FinancialLedgerReportPerformanceTest.php`.

Regression references: `tests/Feature/EventEntrySummaryTest.php` and
`tests/Feature/EventFinanceReportTest.php`. Local PDF previews and local database
counts do not establish production deployment or live totals.
