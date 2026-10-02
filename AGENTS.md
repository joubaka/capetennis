# Cape Tennis application guidance

## Runtime and architecture

- The application runs on PHP 8.5 and Laravel 13. Keep new code compatible with both.
- Route financial mutations through `PaymentOrchestrator`, `TeamPaymentService`, `RefundRequestService`, `RefundExecutionService`, `FinancialLedgerService`, and `EntryService`. Do not update wallet, payment, refund, or withdrawal state directly in controllers.
- Wallet balances are derived from `wallet_transactions`; never introduce or mutate a cached balance column.
- PayFast ITNs must verify signatures, require `COMPLETE`, validate server-calculated amounts, lock orders, and remain idempotent.
- Applying wallet funds reserves them but does not debit the wallet. Debit only during verified finalization. Cancellation releases or resets reservations through a payment service.

## Registration and withdrawal invariants

- Open event registration is payer-sponsored: any otherwise eligible authenticated user may register an eligible player without owning or linking to that player. Preserve event-specific nomination, invitation, agreement, profile, publication and eligibility gates; sponsorship does not grant player ownership or access to another payer's checkout.
- Supported flows include individual, team-player, admin/free, PayFast-only, wallet-only, and hybrid wallet/PayFast registrations.
- Verify ownership and team/player/event relationships server-side. Never trust submitted financial values or identifiers without resolving them against the order.
- Create paid admin entries through `EntryService::addPlayerAsAdmin()`.
- Accept refund requests only after withdrawal. Enforce ownership, withdrawal state, deadlines, payment state, and idempotency.
- Preserve original paid state as an audit record after refunds; refund status records the reversal.
- Withdrawals must remove active draw, fixture, or roster participation. A late team withdrawal with no refund path must free its roster slot immediately.
- Interprovincial Trials registration is payer-sponsored: sponsorship authorization must never depend on owning or linking to the nominee. While registration is open, any otherwise eligible authenticated user, including the normal agreement and profile gates, may register an exact currently published nominee without an email invitation; that actor becomes the payer without gaining player ownership or creating a `user_players` link. Any otherwise eligible authenticated user may restart an unpaid checkout before payment handoff through the canonical cancellation and reservation-release services, becoming the new payer without gaining player ownership. Only the current payer may access or cancel their order; paid or in-flight checkouts cannot be taken over; declining remains restricted to an account already linked to the nominated player.

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

## Default local implementation authority

- A direct request to implement, fix, change, build, refactor, or continue authorizes the bounded local development loop needed to complete that request. This includes inspecting the relevant code, editing application code and tests, running focused tests, formatters, builds, route and Blade checks, local browser QA, and fixing failures caused by the change.
- Do not pause for repeated approval between normal local steps within the requested scope. Make reasonable implementation decisions, preserve unrelated worktree changes, and report material assumptions in the handoff.
- Requests to audit, investigate, diagnose, review, explain, or plan remain read-only unless the user also asks for implementation.
- Local implementation authority does not include unrelated cleanup, broad refactors, dependency changes, commits, pushes, pull requests, release preparation, deployment, production access or mutation, publication, outbound communication, or payment, credential, security, role, or production feature configuration.
- Ask before a broad or unusually expensive local check only when it is likely to disrupt the user's normal local work; otherwise run proportionate verification without another gate.

## Cape Tennis caretaker authority

- For recurring repository care, use the project skill at `.codex/skills/cape-tennis-engineering/SKILL.md` and the specification in `docs/caretaker/AGENT_SPEC.md`.
- A caretaker run may inspect the repository and run read-only diagnostics automatically. It does not make repairs unless the user separately asks to implement or fix them; that request authorizes bounded local edits and verification under the default local implementation authority above.
- Committing, pushing, opening pull requests, changing dependencies, and preparing a release require explicit approval.
- Deployment, production migrations or data changes, publication, outbound communication, and payment or security configuration always require a fresh explicit authorization.
- A daily check reports evidence and risks; it does not repair findings, mutate data, send mail, or deploy.

## Agent delegation

Capester is the user's single Cape Tennis engineering front door. Capester owns scope, coordination, verification, and the final user-facing result. For substantial work, Capester may coordinate bounded specialists from `.agents/skills/capester/references/agent-roster.md`; every specialist inherits the original task's authority limits.

Reuse live specialists within the same task. Across tasks, recreate roles from the repository skills rather than relying on hidden chat state. Only one agent may own edits to a file or feature boundary at a time; parallel work should normally be read-only, and stateful test or browser runs must use isolated databases and runtime paths or run serially.

- The primary agent remains accountable for scope, coordination, final verification, and the user-facing result.
- Use `ct_caretaker` for recurring read-only repository health checks and `ct_lead` for bounded investigation and work planning.
- Use `ct_developer` for one user-requested implementation scope at a time. The implementation request itself is sufficient approval for bounded local edits and verification. Do not run parallel write-heavy agents against overlapping files.
- After implementation, use `ct_quality` for independent verification. Add `ct_financial_security` whenever payment, wallet, refund, withdrawal, identity, authorization, secrets, or other sensitive state is involved.
- Use `ct_release_guardian` only after the user explicitly requests release preparation, commit, push, pull request, or deployment work.
- Parallel delegation is preferred for independent read-heavy exploration, test analysis, and review. Keep code ownership non-overlapping and preserve all unrelated worktree changes.

## Durable agent learning

Capester and its specialists should improve as work establishes new project knowledge. Record a learning only when it is verified, reusable beyond the current task, precisely scoped, and safe for a future agent to apply.

- Put broad product, authorization, and safety rules in `AGENTS.md`.
- Put agent workflow in the relevant skill and conditional technical procedures in skill references.
- Put executable product invariants in regression tests.
- Prefer updating an existing rule over duplicating it in another file.
- Never retain secrets, personal data, transient production values, guesses, incident-only workarounds, or exceptions that weaken a safeguard.
