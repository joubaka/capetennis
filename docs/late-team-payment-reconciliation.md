# Late team PayFast payments

A verified payment can reach Cape Tennis after its checkout expires. Team
checkouts retain the five-minute window measured from PayFast handoff (or
creation before handoff). Expiry releases the reservation; a delayed receipt
belongs to the original payer and must not automatically activate registration
or debit released wallet funds.

## Recognition and operator workflow

The public roster shows **Payment being checked** when an unpaid team order has
`collection_status = reconciliation_required`. This means a receipt needs review,
not that the payer should pay again. Provider delays can still occur; checkout
warnings and a visible review workflow cannot guarantee immediate confirmation.

Super Admin can open **Review late team payments** in the **Reconciliation** tab
of the event's Finances page.
The event-scoped list is paginated and includes cases that cannot be recovered
through the standard reconciliation procedure. Enter a reason and preview the
payment. Preview validates the original receipt, amount, payer, team, player,
expiry history, current roster and invitation, and competing checkouts through
`TeamLatePaymentReconciliationService`.

Only confirm a successful current preview. The POST confirmation rechecks the
canonical safeguards under transaction locks. A changed or expired preview
requires a fresh review. A rejected case stays pending for separate investigation;
never work around a rejection by setting paid flags directly.

Successful reconciliation uses the existing provider receipt, confirms the
registration and records the operator and reason. It makes no new charge, wallet
debit or confirmation email. Repeat application must remain idempotent.

## Sources and regression coverage

- `app/Domain/Payments/Services/ExpiredPayfastHandoffService.php`
- `app/Domain/Payments/Services/TeamLatePaymentReconciliationService.php`
- `app/Domain/Payments/Services/TeamPaymentService.php`
- `app/Http/Controllers/Backend/TeamLatePaymentController.php`
- `tests/Feature/TeamLatePaymentReconciliationTest.php`

Preview serialization must preserve decimal types (`JSON_PRESERVE_ZERO_FRACTION`)
because confirmation compares the current canonical result with the reviewed
result strictly. The successful HTTP preview/apply/retry regression covers this
boundary as well as exact amounts and record counts.

This procedure supplies no standing production authorization. Installation,
production reconciliation and outbound communication retain their separate
authorization requirements. A warning or local test is not deployment or live
verification evidence.
