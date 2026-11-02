# Player Plus management

Open **Super Admin → Player Plus → Manage Player Plus**, or `/superadmin/player-plus/manage`. Only `super-user` accounts can use these routes. The dashboard searches player names/profile numbers, original payer names/emails and order references such as `P123`. All lists are paginated. The payment review history filter includes already reviewed cases; open the review to inspect its receipt-specific resolution status.

## Access support

- Select **All players · add access** to find a player with no purchase.
- **Activate or edit dated admin access** records a separate, explicit grant. Enter start/end dates, a reason and confirmation. It creates no payment, membership receipt or account link. It adds access alongside any verified paid term; shortening a grant does not shorten a paid term.
- **Deactivate all Player Plus access** blocks the player's linked-account access even when a subsequent verified payment renews their paid term. This does not cancel billing or refund money.
- **Restore underlying paid / admin access** lifts deactivation and uses the existing dated grant or paid entitlement. It does not extend expired terms.
- **Remove admin grant · use paid access only** clears the grant and deactivation. Verified paid access then determines eligibility.

Dates use the configured application timezone and the end time is exclusive. A reason and confirmation are required. Changes lock the player, reject stale versions and retain append-only before/after audit evidence. Retried identical submissions do not duplicate a change. Grants and suspension do not make sponsors player owners; existing account/player links remain required for private access. Players with Plus history or admin overrides remain blocked from automatic duplicate merging.

## Checkout and subscription support

The player detail lists orders, payer, stored amount, verified payment reference and subscription billing/cancellation state. **Resolve this order** allows a super user to close a consistent unpaid checkout through the canonical payment cancellation service. It rejects active payment windows, receipts, settlement or activation evidence. The payer can then start a new purchase. Original checkout/handoff evidence is retained.

**Stop automatic renewal / retry cancellation** invokes the existing PayFast subscription cancellation service only on explicit confirmed submission. Every admin attempt and successful confirmation records the actual actor/reason. A failed confirmation remains pending; automatic charges may continue until PayFast confirms. Stopping billing preserves already paid access. No provider tokens are displayed.

**Review receipts and record resolution** uses the existing reconciliation workflow. Resolution notes do not execute refunds, transfer purchases or change payment evidence. Refunds and other financial remedies still require the established separately authorised financial process. Do not mark an unpaid order paid or manufacture a receipt to fix access; use an audited admin grant when warranted.

## Local installation and verification

Required migration: `database/migrations/2026_11_02_120000_create_player_plus_access_overrides.php`. Its exact path is allowlisted in `deploy.config`; that does not authorise production execution. Local work does not include commit, push, deployment, live gateway verification or outbound communication.

Focused coverage: `tests/Feature/PlayerPlusManagementTest.php`, existing Player Plus Membership/Launch/Monthly/PricingDisplay suites and `tests/Unit/DeploymentConfigTest.php`. Setting `CT_PLUS_MANAGEMENT_QA_HTML=1` during the management feature tests writes HTTP-rendered browser fixtures for `tests/Browser/player-plus-management.cjs` under `storage/framework/testing/`.
