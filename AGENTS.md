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

## Cape Tennis caretaker authority

- For recurring repository care, use the project skill at `.codex/skills/cape-tennis-engineering/SKILL.md` and the specification in `docs/caretaker/AGENT_SPEC.md`.
- The caretaker may inspect the repository and run read-only diagnostics automatically. Code and test edits must remain local and supervised.
- Committing, pushing, opening pull requests, changing dependencies, and preparing a release require explicit approval.
- Deployment, production migrations or data changes, publication, outbound communication, and payment or security configuration always require a fresh explicit authorization.
- A daily check reports evidence and risks; it does not repair findings, mutate data, send mail, or deploy.

## Agent delegation

- The primary agent remains accountable for scope, coordination, final verification, and the user-facing result.
- Use `ct_caretaker` for recurring read-only repository health checks and `ct_lead` for bounded investigation and work planning.
- Use `ct_developer` for one approved implementation scope at a time. Do not run parallel write-heavy agents against overlapping files.
- After implementation, use `ct_quality` for independent verification. Add `ct_financial_security` whenever payment, wallet, refund, withdrawal, identity, authorization, secrets, or other sensitive state is involved.
- Use `ct_release_guardian` only after the user explicitly requests release preparation, commit, push, pull request, or deployment work.
- Parallel delegation is preferred for independent read-heavy exploration, test analysis, and review. Keep code ownership non-overlapping and preserve all unrelated worktree changes.
