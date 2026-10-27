# Individual event category changes

The registering account may change an active paid entry while the event is published, registration is open, and neither category has a draw or lock. The normal player eligibility checks apply. Trials require an exact published destination nomination; Masters require a current available destination invitation. A change does not grant player ownership or create account links.

## Price and settlement

The server compares the destination category fee (falling back to the event entry fee) with the amount still paid toward the entry. Original paid order items and provider receipts remain unchanged.

- An increase creates a separate PayFast-only checkout. The original category remains active until a verified `COMPLETE` notification settles the exact difference.
- An equal amount moves the entry without a payment or credit.
- A decrease moves the entry and credits the full difference to the original registering payer's wallet in one transaction, with no category-change fee.

PayFast requires at least R5.00 for a payment. A smaller increase is rejected with an organiser-contact message, without changing the entry or charging a rounded-up amount. See the provider's [integration documentation](https://developers.payfast.co.za/).

The checkout expires five minutes after creation or the PayFast handoff when present, capped at entries closing. Only one active attempt is allowed per entry. A second handoff is blocked while the first is unresolved. Cancellation, expiry, changed eligibility, or withdrawal leave the original category unchanged. Genuine late or additional provider receipts remain visible in the financial ledger for reconciliation and never apply another move.

Super-users may preview and confirm a paid category decrease with wallet credit as the default. An increase must be completed by the registering account through the self-service payment flow. Existing unpaid administrative movement remains separate.

## Funding and refunds

Each adjustment snapshots the original item link, payer, source and destination, amounts before and after the change, and wallet/provider evidence. Shared orders allocate each item's exact price and its proportional wallet contribution using cumulative cents, rather than splitting differently priced entries equally. An order with an earlier withdrawal/refund must be reconciled before its first adjustment.

Subsequent changes and withdrawal refunds use the remaining funding after earlier credits. Adjusted totals do not expose a single original provider ID for an aggregated refund: wallet refunds remain available, and bank requests require administrator review. Entry paid state and original payment history are preserved. The event ledger records extra payments without another entry or Cape Tennis entry fee, and records downgrade wallet credits as separate completed outflows.

## Local verification and deployment prerequisite

Regression coverage is in `tests/Feature/CategoryAdjustmentTest.php` and `tests/Feature/CategoryAdjustmentInvitationTest.php`. Run them against an isolated test database with all mail transports set to array, then run the full feature suite. Check routes, Blade compilation, syntax, `git diff --check`, and `tests/Unit/DeploymentConfigTest.php --fail-on-skipped`.

The exact required migration is `database/migrations/2026_10_27_120000_create_category_adjustments.php`, included in `deploy.config`'s migration allowlist. Local implementation and allowlisting do not authorize production migration execution or deployment.
