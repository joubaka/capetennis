# Player Plus publication notifications

Local implementation; delivery is disabled by default. No live activation or historical backfill is implied.

## Behaviour

An explicit draw or schedule publication records a durable per-draw public-content generation. Standard draws, Flexible Monrad, bulk publication and scoped schedule publication use the same notification service. The latest meaningful public change starts a **five-minute** quiet period. A further meaningful change restarts all five minutes; identical republishing and working schedule edits do not restart it. A minute scheduler prepares due messages, so transport is no earlier than five minutes and can be later under queue load.

Draw and schedule changes within the wait combine into one notice per affected player/contact. A shared family address can receive one notice for each affected player; addresses are normalized and deduplicated within each player/generation. Initial draw notices include all current active participants, including doubles partners. Schedule-only notices include participants of the changed, still-public fixtures. Team notices cover the current relevant team rosters (including competition substitutions and imported identities linked to a player profile), not a promise of individual rubber assignment. Cross-event sources and withdrawn individual entries cannot qualify.

If a new revision arrives while older notices are waiting for a worker, pending recipient scopes carry into the replacement generation. An unaffected player must not lose their unsent initial draw notice. Already-sent or transport-uncertain messages do not carry; those contacts receive only newly applicable changes. Every carried notice still requires current participation, Plus eligibility and a current contact relationship.

Public schedule fingerprints exclude publication timestamps, actor and draft values. `SchedulePublicationService` replaces timestamps even on identical republication, so timestamps and general schedule revisions are unsuitable deduplication keys. Event/draw hiding invalidates pending generations; hiding schedule slots removes those slots from the audience, and hiding every slot cancels a schedule-only notice. An otherwise pending public draw notice remains eligible. Previously tracked content becoming public again starts a fresh wait. Private previews do not start a timer. Event publication changes are observed only for already tracked draws, so ordinary old publications are never scanned into campaigns.

Changes to first-match/full-schedule visibility are treated as changes to the public projection. At due processing and before a worker claims delivery, a changed current public snapshot is reconciled into a fresh generation/wait rather than sending stale content. This also safely handles bracket participant progression during the wait or queue backlog. A stale queued delivery is suppressed; no stale generation is revived.

## Audience and links

Plus belongs to the player. Eligibility uses `PlayerPlusEntitlement::activePlayers()` at preparation and immediately before transport: verified current paid membership or current manual grant, excluding suspension and expiry. An unrelated Plus payer/sponsor is not implicitly a recipient. Contacts come from `TeamSelectionContactService::emails()`, including the player's valid primary/additional contacts and current linked/legacy account emails. Removed contacts, unlinked accounts without another current notification-contact basis, withdrawn players and revoked Plus access are suppressed at send time.

The primary button uses the ordinary authenticated `/my-tennis?player=<id>` route. Normal and two-factor login preserve this destination. Existing authorization still determines access to that player. Additional notification contacts may have no linked account and can use the public draw/schedule link instead. Email contains no invitation, checkout, clothing, automatic-login or access-granting token. Existing account links are not represented as verified guardianship.

## Operations and safeguards

- `config/publication_notifications.php` defaults `PLUS_PUBLICATION_MAIL_ENABLED` to false. No environment file is changed by this implementation. Explicit delivery activation is a separate authorized operation; it can process due changes already tracked since installation.
- `player-plus:publication-notifications --limit=50` prepares bounded due draws; it is scheduled every minute. It does not invoke transport directly. Delivery remains disabled until explicitly enabled.
- The database queue must share the application database. Delivery reservations and queued jobs are inserted atomically; rollback or queue submission failure leaves the campaign available for a later retry.
- `SendPlusPublicationNotification` uses managed mail and `outbound-mail` throttling. Recipient, current generation, content, sender and reply address are checked by the existing mail-review listener before transport. This narrow system-mail allowance does not authorize arbitrary event mail.
- A delivery is claimed once. Ambiguous transport exceptions become `acceptance_unknown`; a repeated worker cannot resend them automatically. Operator reconciliation is required before any manual resend. `sent` records transport acceptance, not inbox delivery.
- Migration: `database/migrations/2026_11_03_150000_create_plus_publication_notifications.php`, explicitly listed in `deploy.config`. The allowlist does not authorize production execution.

## Verification

`tests/Feature/Draw/PlusPublicationNotificationTest.php` covers timing boundaries, reset/no-op behaviour, scoped audiences, preview/publication/hiding, event isolation, grants/suspensions/paid membership, stale contacts, rollback/queue failure, managed array transport, strict mail authorization, Monrad/bulk publication and ordinary deep links. `tests/Feature/EventAdminLoginRedirectTest.php` covers the normal/2FA player destination. Tests use fictional records and in-memory mail transports; live queue health and real delivery require separate authorization and evidence.
