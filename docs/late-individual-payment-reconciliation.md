# Reviewed late individual PayFast reconciliation

An expired checkout remains closed when a verified late ITN arrives. The receipt
belongs to the original payer and is retained for explicit review. A later change
to the checkout window never reopens that checkout automatically.

`LateIndividualPayfastReconciliationService` restores one original ordinary
single-player, full-PayFast registration using an existing verified late receipt.
It is separate from the on-time Interpro missing-receipt recovery and from team
late-payment recovery. It creates no replacement order, registration or receipt,
does not debit a wallet, and sends no mail.

The exact original order, payer, player, category, event and registration must
match the sole receipt's five custom IDs. Require unique canonical retained
handoff, automatic-expiry and late-receipt activities; exact cents; a real receipt;
an unchanged cancelled unpaid order; the exact soft-deleted ordinary active/unpaid
entry at its expiry timestamp; and no wallet, refund, withdrawal, manual/EFT,
recovery, draw, fixture, category adjustment or competing participation evidence.
Current publication, registration window, category lock, nomination, disciplinary
eligibility and capacity checks remain mandatory.

Only a strictly verified unused expired attempt for the same payer/player/category
may be preserved unchanged. It must be outside the original attempt's lifetime,
have one canonical expiry and integrity-verified history, never have reached
PayFast, and contain no financial or competition evidence. Preview and audit
disclose preserved IDs; they are locked and bound into the state fingerprint.
Ambiguous, active, handed-off or paid competing attempts block recovery.

Preview is read-only and requires a current super-user:

```text
php artisan payments:reconcile-late-individual <original-order-id> --actor=<reviewer-id> --provider=<exact-reference> --reason=<reviewed-reason>
```

Apply repeats the same arguments with `--confirmation=<preview-token>`. The
actor-bound token expires after ten minutes and covers the current original
records, receipt, provenance, reason and reviewed platform fee. Apply rechecks
under locks and runs canonical entry restoration and payment completion in one
transaction. Exact repeat and later verified ITN replay are harmless.

The existing receipt's provider reference, date, gross, stored fee/net and five
IDs remain unchanged. Stored fee/net can be configured estimates; do not present
them as independently verified provider charges. The only receipt classification
change is the Cape Tennis fee, taken from the locked current event configuration
and separately disclosed in preview and audit. This records a platform fee; it
does not charge the payer again. Legacy receipt tables need neither a separate
`registration_id` nor `payment_status` column.

The shared individual/team checkout window is now thirty minutes, measured from
PayFast handoff when present and otherwise checkout creation. Historical recovery
checks use the original expiry audit's recorded window, including older five-minute
attempts. Longer expiry reduces short checkout delays; much later settlements
still require review. It does not repair missing provider delivery.

See `LateIndividualPayfastReconciliationTest`, `IndividualPayfastReconciliationTest`
and `UnpaidCheckoutExpiryTest`. Local verification, release, live application and
provider verification are distinct; a guide never grants production authority.
