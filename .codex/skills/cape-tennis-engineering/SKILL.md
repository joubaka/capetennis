---
name: cape-tennis-engineering
description: Audit, maintain, test, and prepare scoped Laravel changes for the Cape Tennis application while protecting financial, registration, ranking, publication, communication, and deployment boundaries.
---

# Cape Tennis Engineering

Use this skill for development, audits, QA, release preparation, or recurring repository care in the Cape Tennis checkout.

## Operating mode

1. Read the root `AGENTS.md`, inspect `git status`, and preserve unrelated changes.
2. Classify the request as audit-only, supervised implementation, release preparation, or production operation.
3. Read only the reference relevant to the work:
   - For permissions and consequential actions, read [authority-and-safety.md](references/authority-and-safety.md).
   - For tests, browser QA, or readiness claims, read [verification.md](references/verification.md).
   - For daily caretaker runs and handoffs, read [reporting.md](references/reporting.md).
4. Prefer current code, configuration, tests, and `AGENTS.md` over older documentation. Report conflicts instead of silently choosing an old instruction.
5. Keep evidence states distinct: inspected, changed locally, tested locally, committed, pushed, deployed, and verified live.

## Domain invariants

- Use the canonical services named in `AGENTS.md` for financial, entry, withdrawal, and refund mutations.
- Treat `wallet_transactions` as the wallet ledger; never create a cached balance mutation.
- Preserve idempotency, exact server-calculated amounts, transaction locking, ownership, event isolation, and immutable audit history.
- Normal/open event player selection is not the same as payer/order ownership. Preserve current eligibility and invitation rules without inventing an account-link gate.
- Rankings and results remain private until their canonical publication workflow says otherwise.
- Use the configured mail system and rate limit; never expose secrets or create production diagnostics.
- Inspect `deploy.config` migration paths for release work. Never execute all pending production migrations by default.

## Stop conditions

Stop and request authorization immediately before any action requiring a higher authority level. Stop a release readiness claim when database, browser, queue, mail, payment-provider, deployment, or live evidence is unavailable; report the exact unverified boundary.
