# Free registration fees

An individual category's explicit entry fee overrides the event entry fee,
including an explicit zero. A genuinely free entry must not attract a Cape
Tennis registration fee. New administrative entries capture zero in their fee
snapshot through `EntryService`; a paid category override retains the configured
Cape Tennis fee.

PayFast fee estimates are zero for nonpositive payment amounts, including the
shared calculation used by finance summaries. Positive payment estimates retain
their configured percentage, flat charge and VAT calculation. This does not
change a fee actually recorded by a payment provider.

Financial reports preserve explicit historical Cape Tennis fee snapshots even
if an event or category is subsequently made free. A legacy administrative row
without a fee snapshot may infer free pricing only from its own event's category.
An already captured erroneous fee requires a separately reviewed, audited
correction; changing current pricing must not silently rewrite historical fees.

Ordinary all-zero individual checkouts already complete through
`RegistrationPaymentService::markFreeOrderPaid`, without a PayFast receipt or
wallet debit. This repair does not change paid mixed-cart fee attribution,
refunds, publication or event authorization gates.

## Re-entering after removal

Administrative removal retains the original entry and payment history. The
frontend eligibility check must therefore exclude withdrawn and cancelled
entries from its active-paid duplicate check, including rows with a withdrawal
timestamp. An active paid entry still blocks a duplicate. Re-entry creates a
distinct registration rather than reactivating or overwriting the old record;
the normal event, category, invitation and eligibility gates still apply.

## Reviewed test-history archive

`entries:archive-free-test` previews one exact withdrawn administrative test
through `EntryService::archiveFreeTestHistory`. It requires an existing
super-user, exact event/category/player/entry/receipt IDs, a reason and evidence
that no money was collected. Apply additionally requires the unchanged preview
fingerprint. This is not an event-wide reset or an order purge.

By default the service rejects other entries in the event, shared registrations, payment,
wallet, refund, selection and competition dependencies, and private collection
evidence. It supports legacy receipts without a registration ID only with exact
event/category/player linkage and the original canonical unpaid-entry audit.

Apply soft-deletes the withdrawn entry and archives its zero-money receipt.
Only untouched generated system-fee projections may be removed; their snapshots
and the exact source records remain in a maintenance audit. The global player,
registration and identity links remain intact. Event entry and finance views
become clear; account statements deliberately retain historical entries. This
is reversible archival, not permanent erasure of every trace.

A newly created entry invalidates the empty-event precondition. The explicit
`--retain-entry=ID` exception can preserve one exact separate active frontend
entry for the same player, category and payer. Its order must be completed and
wholly free, with no provider, wallet, reservation, manual-payment or refund
evidence. The full entry, registration, player links, order and item are locked
and fingerprinted; apply and replay verify they remain unchanged. Other entries
still block cleanup. Obtain a fresh preview instead of removing a newly created
entry implicitly. Execution on production requires explicit authorization and
the reviewed code to be available there.
