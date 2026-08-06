# Cape Tennis application guidance

## Runtime and architecture

- The application runs on PHP 8.5 and Laravel 13. Keep new code compatible with both.
- Route financial mutations through `PaymentOrchestrator`, `TeamPaymentService`, `RefundRequestService`, `RefundExecutionService`, `FinancialLedgerService`, and `EntryService`. Do not update wallet, payment, refund, or withdrawal state directly in controllers.
- Wallet balances are derived from `wallet_transactions`; never introduce or mutate a cached balance column.
- PayFast ITNs must verify signatures, require `COMPLETE`, validate server-calculated amounts, lock orders, and remain idempotent.
- Applying wallet funds reserves them but does not debit the wallet. Debit only during verified finalization. Cancellation releases or resets reservations through a payment service.

## Registration and withdrawal invariants

- Supported flows include individual, team-player, admin/free, PayFast-only, wallet-only, and hybrid wallet/PayFast registrations.
- Verify ownership and team/player/event relationships server-side. Never trust submitted financial values or identifiers without resolving them against the order.
- Create paid admin entries through `EntryService::addPlayerAsAdmin()`.
- Accept refund requests only after withdrawal. Enforce ownership, withdrawal state, deadlines, payment state, and idempotency.
- Preserve original paid state as an audit record after refunds; refund status records the reversal.
- Withdrawals must remove active draw, fixture, or roster participation. A late team withdrawal with no refund path must free its roster slot immediately.

## Mail, secrets, and dashboards

- Use the configured AMS/managed mail service; do not add batch-send transport logic.
- SMTP dispatch is limited by `outbound-mail` to 14 messages per second (`MAIL_RATE_PER_SECOND=14` by default).
- Restrict super-admin settings and financial tools to `super-user`.
- Never expose PayFast merchant keys, passphrases, SMTP credentials, or other secrets. Production diagnostic endpoints must be unavailable.
- Bound or paginate dashboard datasets. Do not load all historical events, completed refunds, wallets, or wallet transactions in one request.

## Verification

- Add regression coverage for authorization, cross-event isolation, idempotency, exact amounts, and record counts when changing financial flows.
- Run focused tests first and the complete feature suite for cross-cutting registration or payment changes.
- Verify `git diff --check`, route registration, and Blade compilation before committing.
- Do not run all pending production migrations blindly; inspect and run only those required for deployment.
