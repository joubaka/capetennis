# TeamSheet tennis results into Cape Tennis

## Scope

Cape Tennis imports native TeamSheet tennis singles results as match evidence for its own player ratings. It does not import TeamSheet's calculated rating, overall team scores, tournament finishing positions or Cape Tennis results previously imported by TeamSheet.

The sharing boundary is **all verified native tennis results through a restricted server connection**, including internal results. The integration does not publish private TeamSheet records or change school settings.

- League: completed, verified singles rubbers with identified participants.
- Tournament: completed, verified singles rubbers with participants resolved to canonical player identities.
- Ladder: completed singles challenges with confirmed results or the canonical confirmation deadline elapsed, without an active dispute.

Cancelled, disputed, walkover, incomplete and placeholder records are excluded. Tournament fixture copies and mirrored school fixtures must not be counted twice. Doubles do not contribute to the current singles model.

## Source contract

TeamSheet provides `GET /api/v1/integrations/cape-tennis/results?page=1&per_page=100`, protected by a server-only bearer token. Each row identifies the native source match and revision, source competition, ball type, two TeamSheet player IDs, winner, set scores and date precision. It contains no contact information, credentials or invitation links. A missing server credential fails closed; ordinary school users cannot configure or retrieve it.

The response includes a complete-snapshot hash, total and page metadata. Cape Tennis accepts a snapshot only after every page has been validated against the same snapshot. A failed or changing download cannot partially replace staged results. Missing records are retained as retracted audit records and cannot contribute to ratings.

The first implementation bounds a snapshot and retained import records at 10,000. Capacity errors fail the sync instead of silently importing a partial history or deleting old audit records. Larger histories need a reviewed capacity or retention change.

## Local operations

Implementation does not configure a connection or import real results automatically. Before a separately authorised connection is enabled, deploy the reviewed source and consumer changes and apply only their required migrations.

- `TEAMSHEET_RESULTS_URL`: the trusted TeamSheet base URL, including a hosting subdirectory if applicable. HTTPS is required by default.
- `TEAMSHEET_RESULTS_TOKEN`: Cape Tennis's server-only bearer credential; it must match TeamSheet's configured `CAPE_TENNIS_EXPORT_TOKEN`. No credential is generated or enabled by this implementation.
- `TEAMSHEET_ALLOW_LOCAL_HTTP`: optional local-environment loopback exception, disabled by default.
- TeamSheet's `CAPE_TENNIS_LINK_CALLBACK_URL` defaults to `https://www.capetennis.co.za/my-tennis/teamsheet/callback`; it must exactly match Cape Tennis's generated callback. It is not a browser-supplied return URL.
- Super User management: `/backend/integrations/teamsheet/tennis`.
- Explicit sync command: `php artisan teamsheet:sync-tennis`.

The self-service flow additionally requires Cape Tennis migration `database/migrations/2026_11_01_180000_create_teamsheet_profile_link_requests.php` and TeamSheet migration `database/migrations/2026_11_01_180000_create_cape_tennis_profile_link_codes.php`. These are separate from the existing import migration and must be reviewed and applied by exact path during their authorised releases.

Players and linked parents can choose **Link TeamSheet** for their selected player in My Tennis. They sign in on both sites, select the same existing player on TeamSheet and explicitly confirm the connection. Cape Tennis requires its current canonical player ownership; TeamSheet requires the actual linked student account or its established verified parent relationship. Staff assignments and family-following permissions are insufficient. No cross-site email or name matching is used.

The browser carries a ten-minute signed request and a short-lived one-use confirmation code, never the server bearer credential. The callback is fixed to the configured Cape Tennis HTTPS address. The request is bound to the initiating Cape Tennis user, player and browser session. Both sites recheck the current player relationship when confirming, and conflicting existing mappings are never overwritten. Self-service can connect a real player before their first imported match exists.

Only the actor who created a self-service connection, while still owning the Cape Tennis player, can disconnect it through My Tennis. Existing administrator-reviewed connections and connections created by another linked parent require support review. A completed connection records a verified identity binding; it is not an ongoing TeamSheet account-access grant. A later TeamSheet account relationship change does not silently reassign the player identity. Users or support can remove an incorrect binding. Linking never creates account ownership, a player profile or a `user_players` relationship.

Super Users can still review explicit ID mappings and playing dates. The source contract uses player IDs only. Both participants need a verified identity link before a match contributes.

Dates labelled as scheduled or score-entry times are held for actual-playing-date review. A source revision invalidates its previous date review. Reviewing a date must be based on match evidence, not an assumption that the scheduled or score-entry date is the playing date.

The application scheduler attempts an hourly sync only after a valid connection is configured and the import tables exist. Eligible results contribute during the existing background rating refresh; ordinary player-page reads do not contact TeamSheet or calculate a new rating. Imported evidence stays labelled as TeamSheet and retains its native match identity. It must not be represented as a Cape Tennis event or fixture. Management and raw TeamSheet match evidence are restricted to Super Users. Other authorised rating viewers receive derived ratings and aggregate contribution counts without TeamSheet scorecard details.

## Independent ball ratings

Badge ratings have three independent match pools: Red, Orange and Normal / Green-Yellow. Red results affect only Red, Orange results affect only Orange, and every other or unstated ball type belongs to Normal. A badge without eligible results in its own pool stays unrated instead of borrowing the overall CTR or another level's score. The overall all-age analytical projection remains a separately labelled measure.

Validated, complete published Cape Tennis singles finishing fields may also provide weaker evidence within their own ball level. Finishing-only estimates stay local and low confidence, are labelled “final placings only”, and never add played matches, wins or played-event completion. Incomplete, ambiguous or unpublished fields are withheld. TeamSheet contributes verified match evidence only; its tournament placings are not imported. Overall CTR continues to use played singles only.

Canonical match/court ball metadata takes precedence. Otherwise, an event or category label containing `Red` or `Orange` identifies that level, without requiring the word `ball`; matching is case-insensitive. A specific category takes precedence over its parent event title. Unlabelled Wilson U/9B is an Orange alias, including a U/9 B category within a Wilson event; explicit category colours take precedence over that fallback, and other age groups alone do not imply a ball level. A label containing both Red and Orange needs a more specific classification. TeamSheet transmits only the derived ball-type value, never the original competition label or player names.

Each pathway badge uses its own level's rating and level-specific event count. Unstated native ball types count towards Normal, and skipped levels are not completed automatically. The progression minimum continues to count distinct published Cape Tennis events; TeamSheet match contributions are not relabelled as Cape Tennis events to satisfy that minimum.

## Retraction and freshness

Corrections, disappearance from the verified snapshot, unlinking and expired source freshness invalidate affected saved rating evidence. The feed is usable for at most 24 hours after a successful sync; a failed download does not extend that period. Source removal or loss of verification becomes known at the next successful sync, or imported evidence expires when the freshness limit is reached. This is a polling boundary, not an immediate remote revocation guarantee.

Cross-source candidate duplicates are held conservatively for review. Legitimate rematches must retain distinct native IDs. Do not bypass a hold by creating a new Cape Tennis fixture for the same imported match.

## Verification and release

Use isolated databases and fake HTTP responses for import tests. Verify server authentication and source eligibility, full-snapshot consistency, idempotency, corrections/retractions, explicit identity links, reviewed dates, duplicate suppression and saved-rating/history invalidation.

Local tests and route/Blade checks do not establish deployment or live connectivity. No commit, push, production migration, publication or real import is implied by this document.
