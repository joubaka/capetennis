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
