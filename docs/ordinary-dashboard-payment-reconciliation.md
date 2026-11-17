# On-time ordinary PayFast baskets with missing receipts

`OrdinaryDashboardPayfastReconciliationService` is an explicit super-user review
for a full-PayFast ordinary individual checkout that automatically expired even
though the provider payment completed within its original recorded window. It
supports the original basket, including multiple players. Interpro, Masters,
wallet/hybrid funding and existing late receipts retain their separate paths.

Fresh authenticated PayFast evidence must identify the original order, payer,
event and one exact player/category anchor matching the retained handoff. Actual
gross, fee, net and paid time are provider evidence; gross must equal the entire
unchanged original basket. Every original item is separately bound to its
integrity-verified pre-handoff creation audit and original registration/entry.

Read-only preview:

```text
php artisan payments:reconcile-ordinary-dashboard <order-id> --actor=<super-user-id> --evidence=<local-json-path> --reason=<reviewed-reason>
```

Evidence uses the fresh-dashboard fields documented in
`docs/payfast-registration-confirmation.md`; it must be observed within thirty
minutes. Re-reading a cached export does not refresh the provider observation.
Apply repeats the same arguments with `--confirmation=<preview-token>`. The
actor-bound token lasts ten minutes and fingerprints the whole basket, provider
evidence, lifecycle history, current eligibility and reviewed platform fee.

All original categories, players, registrations and entries are locked and checked.
Require current publication, registration, eligibility, applicable nomination and aggregate
capacity for every player, with no draw, withdrawal, refund, manual/EFT, wallet,
receipt or conflicting paid/active participation. Use the expiry audit's original
five- or thirty-minute window; a policy change never reopens historical attempts.

Earlier attempts may be preserved only through the explicit prior-attempt checks:
same payer and exact player/category, one item, unchanged unpaid cancelled order,
original soft-deleted entry, canonical expiry before the target's creation, and
no settlement, wallet, refund, manual/EFT, recovery or competition evidence.
Never-handed-off attempts require verified absence of historical handoff evidence.
An earlier handed-off attempt additionally needs a distinct fresh authenticated
provider search observation, bound to its complete identity, amount and searched
interval, reporting `no_matching_settlement_observed`. This is an observation as
of that time, not provider cancellation or a guarantee against later settlement.
Its evidence belongs in `prior_attempt_observations`; preview and audit disclose
preserved attempts, and their rows remain unchanged. Any later real payment for
an earlier attempt remains receipt-only reconciliation without activating it.

Apply restores every original entry and completes the original order atomically
through the canonical entry/payment services. It creates exactly one actual
provider receipt for the whole basket, preserves actual provider amounts/date,
and separately records the reviewed current Cape Tennis fee per original item.
It creates no replacement orders, registrations, wallet debits or mail. Repeated
apply and later matching signed ITNs must be harmless; a write failure must roll
back the entire basket.

See `OrdinaryDashboardPayfastReconciliationTest`. A guide is not release or live
mutation authorization. Do not fabricate an ITN, use manual-paid replacements,
weaken a blocked guard, or treat this recovery as proof of callback delivery.
