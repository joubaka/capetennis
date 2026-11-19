# Pending individual bank refund to wallet

This capability replaces an existing full pending bank refund with one credit to
the original payer's wallet. It does not create another refund entitlement or
rewrite the original paid registration, order, receipt or nomination history.

## Review before applying

Only a super-user may review or confirm the conversion from the bank refund
detail page. Independently verify that the exact PayFast payment has not been
refunded and has no queued provider or bank payout. A failed status query, an
unchanged original payment row or an expired request lock is not that evidence.
Record the exact payment reference and a specific reason in the review.

The service accepts only an original paid, withdrawn single-player, single-item
PayFast-only registration with a matching receipt and an existing full pending
bank refund with zero deduction. Adjusted, hybrid, ambiguous, previously credited
or provider-dispatched funding requires separate reconciliation. The recipient
is resolved from the original order; an operator cannot select a different wallet.

The encrypted review expires after fifteen minutes and binds the actor, event,
entry, payer, original funding and pending refund state. Changed state requires a
fresh review. Confirmation credits the ledger and closes the existing bank claim
as a completed wallet refund atomically, with prior state and verification in the
audit record. Repeating the same successful review must not add another credit.

## Provider dispatch protection

`IndividualProviderRefundGuard` guards the actual PayFast refund POST for the
supported original individual funding. A durable claim is committed before
contacting PayFast. Confirmed, dispatching and uncertain attempts prevent wallet
conversion and further dispatch. A timeout or process crash cannot clear that
evidence. A query failure before a refund POST creates no dispatch claim.
The attempt's `confirmed` status records a successful provider POST response;
it does not independently prove that the payer received the refund.

Historical operator verification covers only earlier uninstrumented activity; it
does not clear a newly recorded unresolved provider attempt. Such an attempt needs
a separately reviewed reconciliation path. Other payment shapes retain their
owning workflows and are not eligible for this conversion.

## Owning sources and verification

- `app/Domain/Refunds/Services/PendingBankWalletConversionService.php`
- `app/Domain/Refunds/Services/IndividualProviderRefundGuard.php`
- `app/Domain/Refunds/Services/RefundExecutionService.php`
- `app/Models/IndividualProviderRefundAttempt.php`
- `app/Services/Payfast.php`
- `tests/Feature/PendingBankWalletConversionTest.php`

The required migration is
`database/migrations/2026_11_19_160000_create_individual_provider_refund_attempts.php`.

Deployment, the exact required migration and a live conversion require their
own authorization. Never run unrelated pending migrations. Application of this
workflow does not authorize changes to another event's registrations or payments.

## Local validation

The eighteen focused conversion cases passed across runs (130 assertions).
A combined run of sixteen conversion cases and seven existing refund/beneficiary
classes passed 93 tests and 415 assertions; the final two ordinary-admin route
cases passed separately (19 assertions). PayFast transport, deployment allowlist
and repair-library unit checks passed 65 tests and 728 assertions. Route
registration, isolated Blade compilation and diff checks passed.

The complete feature-suite attempt stopped at the existing
`AllAgeConnectionDirectoryTest:38` failure (expected 200, received 302; unavailable
saved comparison group), after 134 tests and 1,060 assertions. It is not a passing
full-suite result. These are isolated SQLite checks, not a real concurrent MySQL
or live-provider verification.
