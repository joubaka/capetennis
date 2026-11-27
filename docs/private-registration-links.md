# Private registration

An assigned event admin with the admin role or a Super User can enable one
selected player's registration in an exact category of a published ordinary
individual event while public registration stays closed.

## Operator and player workflow

1. In Entries, use the destination category's **Category access → Enable private
   registration**.
2. Select the existing player. The page shows the normalized primary player
   email that will receive the invitation and all normalized notification-only copy recipients. A missing canonical account link or
   invalid primary email must be corrected before enabling registration; this
   feature never creates account associations or promotes an extra contact into the actionable invitation recipient.
3. Choose **Enable and email registration**. This records the admission and one
   managed invitation together. The success page reports the invitation status
   and provides an optional copyable link. Queued does not establish delivery.
4. When an account already linked to this player signs in and opens the closed
   event page, it sees **Register and pay** for that exact player and category.
   Other accounts and guests see no private option. The email opens the same
   account-restricted registration page directly.
5. The linked account reviews the fixed player/category and current fee, passes
   normal agreement/profile gates, accepts registration/refund terms, and uses
   the existing checkout/payment flow.

The canonical linkage for this private admission is an existing user_players
row. Email equality, additional notification addresses, account creation,
player search results and possession of the URL never establish that link.
This restriction applies only to private admissions; normal open registration
remains payer-sponsored without player ownership/linkage.

Access expires after 48 hours or at the beginning of the event's start date in
the application timezone, whichever is sooner. This never opens public entries
or bypasses the global registration switch, publication, discipline, category
lock/cancellation, duplicates, capacity or required consent. Masters, Trials,
camps, teams and special Cavaliers nominations retain their own admission paths.

## Checkout, mail and retries

The first successful submission claims the grant for one order and payer.
Another linked account still cannot see or resume that payer's checkout.
Repeated submissions resume the original payer's order. Competing unresolved
checkouts for the exact player/category block a new checkout. Removed account
links and lost issuer authority are rechecked at redemption, including under
transaction locks.

A cancelled or expired claimed checkout requires the organiser to enable a new
admission after canonical checkout closure; the grant never silently reopens
or replaces it. Existing checkout expiry and settlement rules remain unchanged.
Admission expiry does not revoke a previously created order's own payment window.
A previous Orange withdrawal, paid record and refund remain separate from a new
Green checkout. Wallet funds use the normal reservation and verified-debit flow.

Invitation delivery uses BulkMailDispatcher and SendBulkEmailJob through the
managed database outbox and outbound-mail limiter. Grant, managed log and job
commit atomically; the worker can deliver only after commit. Only the player's
valid primary email is actionable, normalized and checked against
TeamSelectionContactService::emails(). Additional canonical contacts, including
valid email_2/email_3, receive notification-only copies without registration,
invitation, checkout, clothing or decision URLs. Addresses are normalized and
deduplicated so the primary is never also a copy. The admin reviews the entire
recipient set, and any changed contact set requires another review before enabling.
No notification address ever authorizes access.

Repeated enabling reuses an unchanged, unclaimed active grant without another
invitation. When its reviewed contact, issuer or content is stale, the unused
grant expires and one fresh grant/invitation replaces it. Old queued messages
then fail current-source validation. Existing failed, skipped or uncertain mail
is shown explicitly and is not blindly resent by enabling again.

The invitation service rechecks source, current issuer, linkage, the complete reviewed
primary/copy recipient set, lifecycle, capacity and exact per-kind stored content
before transport. Adding, removing or changing a contact invalidates every stale
message in that invitation.
RequireEventEmailReview also requires one exact To recipient, no extra copies
or attachments, and unchanged body/subject/sender. The URL contains only a grant
ID; every GET and POST independently checks existing linkage. No bearer token
or player email grants access.

## Sources and verification

- PrivateRegistrationLinkService and PrivateRegistrationLink own issuance,
  bounded account-specific discovery, lifecycle and exact-tuple grant state.
- PrivateRegistrationLinkController supplies admin recipient review and the
  authenticated fixed-player/category landing; expired links render an
  unavailable page without a GET redirect loop.
- RegisterController::payNowPayfast rechecks grant, association, event/category,
  eligibility and unresolved attempts under locks, then claims the grant in
  the same transaction as normal order creation. Financial finalization remains
  in the existing canonical payment services.
- Frontend EventController and event/show.blade.php expose only current grants
  for the signed-in linked account. SQL filters account/event/issuer/lifecycle
  and order scope before grouping the latest grant per tuple and limiting to 20.
- EnsureAgreementAccepted preserves the private return URL; the profile gate
  already preserves its intended URL.
- PrivateRegistrationInvitationService, BulkMailDispatcher, SendBulkEmailJob and
  RequireEventEmailReview own the managed primary invitation and notification-copy pipeline.
- tests/Feature/PrivateRegistrationLinkTest.php covers scoped admin access,
  login/agreement return, exact cart/fees, expiry, issuer/category/global gates,
  preserved withdrawal, account-specific event visibility, removed associations,
  unchanged normal sponsorship, payer-only resume, signed hybrid settlement
  replay, primary-actionable and link-free-copy idempotent mail, stale recipient/issuer, recipient review,
  failed outbox rollback, and exact transport authorization.

Required migration: database/migrations/2026_11_27_120000_create_private_registration_links.php,
explicitly listed in deploy.config MIGRATION_PATHS and checked by
DeploymentConfigTest. Only the specifically required migration may run during
an authorized deployment. The existing managed database-queue worker must process these jobs. No new
settings or payment/mail credentials are required. Local tests and synthetic browser fixtures do not prove production
schema, deployment, real payment or inbox delivery.
