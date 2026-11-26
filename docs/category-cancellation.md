# Category cancellation

An assigned event administrator with the `admin` role, or a `super-user`, can use
**Entries → Cancel category** for an ordinary individual event before its draw
has been created. The reason, exact affected entries, full original-payer wallet
amounts, notification contacts and email text are reviewed before one confirmation.
**Send cancellation and refund emails** defaults to checked. When players were
already notified, clearing it deliberately suppresses every cancellation notice and
refund receipt. The signed review binds this choice; confirmation never trusts a
changed posted preference. Suppression is retained in the cancellation audit, and
creates no communication batches, logs or jobs. Missing notification-only contacts
or sender settings do not block a no-email refund; original funding and payer
identity checks still apply in full.

Confirmation atomically withdraws active entries through `EntryService`, refunds
verified original funding through `RefundExecutionService`, closes the category,
records the actor and immutable reviewed snapshot, and, when email is selected,
saves managed database mail jobs in the same application database transaction. Already-withdrawn, unrefunded
entries are included. Paid history is retained. A retry of the same approved token
returns the saved result without repeating money or email. Stale or expired reviews
require another preview. A cancellation cannot be reversed with the category unlock
control, and new admissions, draw creation and payment activation reject cancellation.

Player contacts are resolved with `TeamSelectionContactService::emails()`, normalized
and deduplicated for the notification-only category notice. Each original payer gets
a private confirmation of their own exact refund. Additional player contacts never
receive receipts, amounts or checkout links. The category notice replaces the ordinary
additional-contact withdrawal notices for this workflow. Queued mail is not evidence
of transport acceptance or inbox delivery; follow the event's email report.

Unresolved checkouts, category changes, provider refund evidence, ambiguous or pending
refunds, missing contacts when email is selected, privately collected admin fees and unsupported historical
funding block the complete operation before any writes. Original zero-price checkout
items and verified unpaid admin entries have nothing to refund, including after
repricing. Verified multi-player baskets entirely in the cancelled category use exact
original item prices, including differing prices. A basket spanning other categories
is supported only for uniform positive-price, PayFast-only original items in the same
event, with exact original payer/player identities, settled total and linked receipt.
Every sibling's current canonical payment calculation must equal its original item
price with zero wallet contribution. This proves that a later ordinary sibling refund
cannot exceed its entitlement; missing legacy receipts, changes, unresolved claims,
different-price mixed baskets and mixed wallet/hybrid baskets require finance review.
Existing completed basket refunds must match full
item entitlements. Categories with any existing draw need a separately reviewed draw
cancellation workflow, preserving scores and participation history.

Existing single-registration Super Admin wallet refunds retain assigned valid-email
event admins as CC/reply-to contacts. If none are assigned, the authenticated reviewing
Super User's valid email is used without changing event assignments. This fallback
never changes the payer receipt destination; preview tokens bind the reviewing actor.

Migration: `database/migrations/2026_11_26_090000_add_category_cancellation.php`, explicitly
allowlisted in `deploy.config`. The managed database queue must use the application's
database. Deploying the migration, running workers and production cancellation are
separate authorized operations.

Regression evidence: `CategoryCancellationTest`, `AdminWalletRefundConfirmationTest`
and `DeploymentConfigTest`. Local tests do not establish live refunds or mail delivery.
