# Individual PayFast confirmation and missing callbacks

A browser return from PayFast is navigation, not settlement evidence. The payer-only
return handler must read saved order and entry state: report registration success
only for a paid order with active paid entries. Retained paid history after
withdrawal, refund or a category move must not claim current registration.
Pending and closed orders must
say registration is unconfirmed and advise checking an existing charge before
paying again. The GET must not change financial or entry state.

## Callback transaction boundary

After the existing merchant/signature and COMPLETE checks, individual settlement
must run as one outer transaction, including the order lock, canonical payment
finalization, wallet ledger debit, immutable provider receipt, entry updates and
invitation confirmation. Retry the complete transaction up to three times for
database concurrency errors. Exhaustion returns HTTP 503; permanent validation
failures remain rejected. Confirmation events and mail remain after commit.

Validate supplied payer, event, category and player metadata against the original
order items, and validate numeric server-calculated amounts before settlement.
An existing provider reference cannot be reassigned. A paid replay must match
the original reference and amount and must not add another receipt or debit.

## Distinguish delivery from processing

Trace one exact provider reference and original order through:

1. Independently authenticated PayFast transaction details, including amount,
   payment time and custom payer/order/event/player/category fields.
2. Web-server callback request and HTTP response, where available.
3. Application callback-received, signature and payment failure logs.
4. Saved order, receipt, entry and invitation state.

No callback-received log is not proof of a database failure. Missing requests may
require provider delivery or hosting/firewall investigation. Do not change
security configuration, fabricate an ITN, replay a lost mutation, or infer
settlement from the browser success URL. Callback retry hardening does not repair
a request that never reaches the application.

PayFast treats HTTP 200 as acknowledgement and retries other responses. An
upstream challenge or error page returning 200 can therefore mask failed
delivery; verify the response body and application evidence as well as status.
This is a diagnostic possibility, not a finding that a specific firewall caused
an incident. See the [PayFast ITN documentation](https://developers.payfast.co.za/docs/itn-instant-transaction-notification/).

## Expired original orders

Keep the shared five-minute expiry policy and immutable handoff audit. A verified
late callback records the original payer's receipt for reconciliation; it must
not automatically reopen an expired checkout or debit released reservations.
The active-handoff recovery screen does not list expired orders whose current
handoff marker was cleared.

Do not use a new manual-paid entry, the legacy import controller, or direct paid
flags to represent recovery of the original PayFast order. The explicit recovery
below is limited to on-time, full-PayFast Interpro payments. Other individual
expired-checkout cases still require separate review. Verified provider evidence
alone does not bypass lifecycle guards.

## Explicit on-time Interpro reconciliation

`IndividualPayfastReconciliationService` supports one original single-player,
zero-wallet Interpro checkout that automatically expired after an on-time payment
was not recorded. It restores the original soft-deleted entry and original
invitation/order linkage; it does not create a replacement checkout or charge.

For the fresh-dashboard source, the operator must freshly verify the successful transaction inside the
authenticated PayFast dashboard, including its reference, payer/order/player/
category/event custom IDs, timestamp, gross, provider fee and net. This is an
explicit super-user attestation, not a cryptographically verified ITN. Keep that
evidence source distinct in audit and support reports.

The command takes a local JSON evidence file (up to 16 KiB), a reviewing
super-user and reason. Omit confirmation for a read-only preview:

```text
php artisan payments:reconcile-individual <original-order-id> --actor=<reviewer-id> --evidence=<local-json-path> --reason=<reviewed-reason>
```

Evidence fields are `source` (`authenticated_payfast_dashboard`), `attested`,
`verified_by`, `verified_at`, `provider_status` (`successful`), `currency` (`ZAR`),
`provider_id`, `order_id`, `payer_id`, `player_id`, `category_event_id`, `event_id`,
`paid_at`, `gross`, `fee` and `net`. Timestamps include explicit UTC offsets;
amounts are exact two-decimal strings and fee is a positive deduction, with
gross minus fee equal to net. Evidence must be freshly verified within 30 minutes.
Never put actual evidence, identities or confirmation tokens in the repair library.

An explicit manual accounting review may instead use source
`same_day_authenticated_dashboard_operator_reconfirmed` when the operator already
observed the authenticated transaction that same day and the owner explicitly
confirms that the payment is unchanged. This is not a fresh provider recheck.
Keep `attested`, `verified_by` and all original payment fields, omit `verified_at`,
and require `observed_on` (application date), `operator_reviewed_at` (offset ISO
timestamp), `unchanged_payment_confirmed` and `owner_confirmation_reference`.
The reason must include that confirmation reference. Observation, payment and
review must share the current application date; review must follow payment and
be within ten minutes. Fresh-dashboard and historical-source fields cannot be
mixed, even with null values. Preview and audit disclose that PayFast was NOT
freshly rechecked and retain observation date separately from operator review
time. All canonical financial and lifecycle guards remain unchanged.

Preview locks and checks canonical handoff/automatic-expiry provenance, original
soft-deleted entry, complete invitation history, published nomination, current
eligibility/capacity and absence of draw, refund, manual/EFT, conflicting checkout
or any existing receipt or conflicting entry history on first apply. A matching
receipt already retained by a late ITN also requires separate review; this
command does not cover that different starting state. It returns an actor-bound confirmation
token expiring in ten minutes. An apply call uses the same evidence and reason
with `--confirmation=<preview-token>` and rechecks current state and fingerprint.
Stop if any guard blocks; do not weaken it to fit an incident.

A later same-payer attempt may be preserved only when it was created after the
original automatic expiry, has one identical player/category item and a distinct
registration, and itself automatically expired without any payment handoff.
Require exactly one canonical expiry activity, unchanged cancelled order and
soft-deleted pending-entry timestamps, and no current or historical handoff,
release, settlement, wallet, refund, manual/EFT/recovery or competition evidence.
Other payers, ambiguous history, multiple items and active attempts still block.
Lock and fingerprint the preserved attempts and their provenance; disclose their
IDs in preview and audit, recheck on apply, and leave every preserved row unchanged.

The apply operation goes through scoped entry, invitation, payment and receipt
services in one transaction. It retains the original provider paid time, actual
provider fee and net, and separate reconciliation audit time. Because an unpaid
checkout has no historical Cape Tennis fee snapshot, the reviewed current event
fee is separately exposed and fingerprinted; it is not historical provider
evidence. Existing finance projections may still use configured fee estimates.
No new wallet debit, order, manual receipt or outbound confirmation is requested.
Exact replay must be harmless; changed evidence or state must fail.

Receipt identity uses the existing five custom IDs and the locked original order
item's registration link. Do not require a `transactions_pf.registration_id`
column or a receipt `payment_status` column: legacy live tables omit these
test-fixture fields. Completed settlement is retained on the canonical order and
in reconciliation audit. Retain the original
registration ID explicitly in the reconciliation audit and result.

Deployment and a live apply each retain their applicable explicit authorization.
The command is not an automatic ITN repair, does not cover genuinely late payments
or hybrid/wallet orders, and does not fix upstream delivery.

Focused regression: `tests/Feature/RegistrationPayfastConfirmationTest.php`.
Recovery regression: `tests/Feature/IndividualPayfastReconciliationTest.php`.
Related expiry/replay coverage: `UnpaidCheckoutExpiryTest`,
`RegistrationCartPayfastRestartTest` and `PayfastCheckoutRecoveryTest`.
Local test evidence, deployment, provider delivery and live recovery are distinct.
