# TeamSheet tennis results into Cape Tennis

## Scope

Cape Tennis imports native TeamSheet tennis singles results as match evidence for its own all-age player rating. It does not import TeamSheet's calculated rating, overall team scores, tournament finishing positions or Cape Tennis results previously imported by TeamSheet.

The sharing boundary is **all verified native tennis results through a restricted server connection**, including internal results. The integration does not publish private TeamSheet records or change school settings.

- League: completed, verified singles rubbers with identified participants.
- Tournament: completed, verified singles rubbers with participants resolved to canonical player identities.
- Ladder: completed singles challenges with confirmed results or the canonical confirmation deadline elapsed, without an active dispute.

Cancelled, disputed, walkover, incomplete and placeholder records are excluded. Tournament fixture copies and mirrored school fixtures must not be counted twice. Doubles do not contribute to the current singles model.

## Source contract

TeamSheet provides `GET /api/v1/integrations/cape-tennis/results?page=1&per_page=100`, protected by a server-only bearer token. Each row identifies the native source match and revision, source competition, two TeamSheet player IDs, winner, set scores and date precision. It contains no contact information, credentials or invitation links. A missing server credential fails closed; ordinary school users cannot configure or retrieve it.

The response includes a complete-snapshot hash, total and page metadata. Cape Tennis accepts a snapshot only after every page has been validated against the same snapshot. A failed or changing download cannot partially replace staged results. Missing records are retained as retracted audit records and cannot contribute to ratings.

The first implementation bounds a snapshot and retained import records at 10,000. Capacity errors fail the sync instead of silently importing a partial history or deleting old audit records. Larger histories need a reviewed capacity or retention change.

## Local operations

Implementation does not configure a connection or import real results automatically. Before a separately authorised connection is enabled, deploy the reviewed source and consumer changes and apply only their required migrations.

- `TEAMSHEET_RESULTS_URL`: the trusted TeamSheet base URL, including a hosting subdirectory if applicable. HTTPS is required by default.
- `TEAMSHEET_RESULTS_TOKEN`: Cape Tennis's server-only bearer credential; it must match TeamSheet's configured `CAPE_TENNIS_EXPORT_TOKEN`. No credential is generated or enabled by this implementation.
- `TEAMSHEET_ALLOW_LOCAL_HTTP`: optional local-environment loopback exception, disabled by default.
- Super User management: `/backend/integrations/teamsheet/tennis`.
- Explicit sync command: `php artisan teamsheet:sync-tennis`.

Only a Super User can link TeamSheet IDs to existing Cape Tennis players or review playing dates. The source contract uses player IDs only; names are never used to create automatic identity links. Both players need a reviewed link before a match contributes. Linking does not create ownership or account relationships.

Dates labelled as scheduled or score-entry times are held for actual-playing-date review. A source revision invalidates its previous date review. Reviewing a date must be based on match evidence, not an assumption that the scheduled or score-entry date is the playing date.

The application scheduler attempts an hourly sync only after a valid connection is configured and the import tables exist. Eligible results contribute during the existing background rating refresh; ordinary player-page reads do not contact TeamSheet or calculate a new rating. Imported evidence stays labelled as TeamSheet and retains its native match identity. It must not be represented as a Cape Tennis event or fixture. Management and raw TeamSheet match evidence are restricted to Super Users. Other authorised rating viewers receive derived ratings and aggregate contribution counts without TeamSheet scorecard details.

## Retraction and freshness

Corrections, disappearance from the verified snapshot, unlinking and expired source freshness invalidate affected saved rating evidence. The feed is usable for at most 24 hours after a successful sync; a failed download does not extend that period. Source removal or loss of verification becomes known at the next successful sync, or imported evidence expires when the freshness limit is reached. This is a polling boundary, not an immediate remote revocation guarantee.

Cross-source candidate duplicates are held conservatively for review. Legitimate rematches must retain distinct native IDs. Do not bypass a hold by creating a new Cape Tennis fixture for the same imported match.

## Verification and release

Use isolated databases and fake HTTP responses for import tests. Verify server authentication and source eligibility, full-snapshot consistency, idempotency, corrections/retractions, explicit identity links, reviewed dates, duplicate suppression and saved-rating/history invalidation.

Local tests and route/Blade checks do not establish deployment or live connectivity. No commit, push, production migration, publication or real import is implied by this document.
