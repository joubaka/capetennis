# Player Plus local implementation and launch checklist

Player Plus covers one named player, shared with that player's currently linked accounts. New annual and monthly PayFast purchases automatically renew until the original payer cancels, with separate explicit recurring-payment consent. The annual price retains its current saved setting (default R699.00); monthly defaults to R69.00. Existing once-off annual checkouts and paid terms remain once-off and are never converted into subscriptions. A super-user can configure each price independently under Super Admin settings. Monthly offers default off until explicitly enabled; general sales availability also applies to all new purchases. Existing pending orders, subscriptions and verified purchases retain their immutable server-saved price and plan; price changes never rewrite them. The minimum configurable price is R5.00, reflecting [PayFast's documented minimum transaction amount](https://developers.payfast.co.za/). The maximum is the application's R99,999.99 cap, with at most two decimal places; it is not a claim about the provider's maximum.

Annual wallet checkout pays for 12 months in one debit, with no automatic renewal. Plan selection and review show what is due now and when another charge is scheduled. The payer's order page leads with the payment status and the action available for that order.

An older, unhanded checkout below R5.00 cannot start a new PayFast handoff. Its amount, status, expiry and handoff evidence remain unchanged; the payer must explicitly close it and start a new checkout at the current displayed price. A verified callback for an already handed-off historical checkout still validates its immutable stored amount and original payment window. New price bounds are never applied retrospectively to verified receipts.

## Customer package

- Saved Cape Tennis Rating (CTR), with provisional and unavailable states preserved.
- Monthly tennis reports, recorded form and up to twelve months of rating history, with the recorded evidence behind eligible estimates.
- Eligible published-series comparisons and CTR Top 10 lists, including available comparable ratings for eligible opponents from published fixtures or saved meetings. This does not grant access to the opponent's private history or guarantee a match outcome.
- Tennis pathway guidance for junior and beginner players, from Red through Orange, Green and Yellow ball to exploring TSA events: ball-level progress, Red/Orange benchmarks and published event suggestions where available. A comparable, nonlocal Green/Yellow CTR maps to the configured Cape Tennis guide bands (default Wilson B at 50 or below, Wilson A above 50 through 75, TSA above 75). These are Cape Tennis guidance bands, not official qualification or externally calibrated readiness. Fresh suggestions retain evidence/freshness checks; saved stages awaiting an update stay labelled. Guidance does not promote players or guarantee selection or entry. See `PlayerPathwayService`, `PlayerPathwayEvents` and their focused tests.

Marketing leads with “Know your game. Find your next step.” and four short questions: “What could I play next?”, “How am I progressing?”, “What is my rating?” and “How do I compare with my opponent?”. Tournament options help choose a suitable type or level of event, not a guaranteed next dated fixture. Event dates, availability and entry requirements must still be checked with the organiser. These are offer-positioning decisions; they do not expand rating access or eligibility.

Essential tournament information remains free. Draw alerts remain a historical design concept and are excluded; customer copy must not promise a delivery date. The public customer offer is `/ctr`; `/player-plus` is the authenticated player-selection step. The private backend membership preview is labelled historical.

## Marketing readiness

The local offer uses the same benefits on the public landing, home promotion,
signup introduction and My Tennis previews. Signup leads with a free account and
a compact optional Plus introduction; the full offer stays on the landing page.
Expanded explanations retain rating evidence, comparison and privacy limits.
Samples remain explicitly fictional. Shared pricing advertises a monthly amount
only when monthly purchases are enabled, distinguishes PayFast automatic renewal
from once-off annual wallet payment, and hides prices while sales are paused.
Purchase consent and payment terms retain their existing meaning and version.
Coming-soon previews link to the public offer from nested feature cards as well
as the hero and footer. Verify that state with an explicit My Tennis `tab=rating`
request; the default Profile tab does not exercise the full rating preview.

Local copy and responsive checks do not establish production launch readiness.
Before enabling live sales, independently verify the exact deployed offer,
configured prices/plans, linked-account access, representative saved data,
payment-provider recurring capability, migrations, scheduler, and managed mail
against the operational checklist below. This marketing work does not enable
sales, deploy code, take payments or send announcements.

The player purchase page shows a linked account only an availability summary (available, awaiting update, or no eligible evidence). It does not reveal a pre-purchase rating or private match history. Sponsors receive the general disclosures and no private availability summary. Page reads do not calculate ratings or request a refresh. A missing Plus schema disables purchases safely, without breaking the player-selection or order-list page.

The named-player page leads with membership and purchase availability. It shows
checkout steps only when a plan can be chosen. Authorized viewers open their
rating in My Tennis, and payers manage renewals through My Plus orders.

Before a new checkout, the payer confirms the versioned purchase details, named player and displayed price. An outdated quote is rejected, while an existing checkout keeps its original price. Acceptance version, actual order price and actor are recorded in the canonical append-only audit. Payer order history is private and paginated; player links do not grant access to another payer's orders. Membership expiry is visible, including after purchases are paused.

## Sales pause

`player_plus_coming_soon_enabled` defaults to on when unset. With sales paused,
it exposes the public Player Plus marketing and an orange **Coming Soon** badge,
while purchase actions remain closed. Super users can turn this marketing off
or on under Site Settings → Player Plus. Enabling **Accept new purchases**
launches purchasing and removes the coming-soon badges automatically; the
marketing toggle never grants private ratings, player links or paid access.
Public marketing hides monetary plan amounts until sales are enabled, including super-user previews; historical order amounts remain visible to their authorised payer.

`player_rating_membership_enabled` is the existing setting key, now presented as **Player Plus · accept new purchases**. It controls new orders and new PayFast handoffs. Turning sales off preserves current paid benefits until their exact expiry. Already handed-off verified payments still use their original immutable order amount and payment window. The sale setting is not an entitlement or player-link override.

No setting values are changed by the implementation itself. Only test fixtures exercise settings writes.

## Managed messages

Confirmations go only to the original payer. They describe the verified purchased term and provide the payer's order link; they do not grant private player access. An existing once-off annual term may receive one manual-renewal reminder within fourteen days of expiry while sales are available. The reminder is suppressed after expiry, replacement of the current term or a sales pause. Neither annual nor monthly subscriptions receive manual-renewal reminders.

Reconciliation alerts use the configured `admin_notification_email` only if valid. The dashboard remains the operational source of truth. Missing or invalid contacts never cause a guessed recipient. Queued messages recheck the current payer/contact, purchased term, membership relationship where applicable, and exact content before transport. The transport listener also verifies the recipient and entire envelope. Reservations use `BulkMailDispatcher`; queue submission happens after the outer commit, with durable deduplication and the existing managed-mail account/rate limiter. Ambiguous or failed SMTP outcomes require reviewed manual handling, never blind resubmission.

`php artisan player-plus:notifications` is a dry run. `--apply` reserves eligible managed messages; running that option can send mail when a worker runs and requires the relevant environment's communication authorisation. `--limit=200` bounds work per message kind (valid range 1–500). Each kind has a keyset cursor, stored in file cache in normal runtime and isolated array cache in tests. Passes advance past invalid contacts and wrap after the end, so missing reconciliation alert reservations and corrected undeliverable renewal/operator contacts are eventually reconsidered without repeatedly scanning only the oldest rows. A dry run never advances the cursor. A batch-limit warning means further passes are needed. An activation message already recorded as skipped requires explicit order repair after its contact is corrected.

`--order=NUMERIC_ID` selects a single existing order for reviewed notification repair; supply the numeric ID without the `P` prefix. Check managed-mail evidence before any repair. A corrected invalid contact may be repaired. An explicit order repair can also regenerate changed content only when every prior attempt for that term/evidence set is marked skipped before transport, with no sent or accepted evidence. Sent, actively sending, queued, failed and ambiguous attempts are never blindly recreated. Failed or ambiguous transport outcomes require the existing reviewed mail-log procedure and provider evidence. New receipt sets remain separate alert campaigns.

The schedule declares a daily `08:32 Africa/Johannesburg` notifications pass, aligned with the documented host cron cadence at `:02, :07, ...`. Source configuration is not evidence that production cron or queue workers are running. Do not run the general scheduler locally against real application data as a test of this change.

## Payment reconciliation procedure

Super-users can open **Review Plus payment issues** from the Plus section of Super Admin settings, or `/superadmin/player-plus`. The paginated queue includes late and additional verified payments, with each order's paginated provider receipts and append-only resolution history.

1. Verify the original payer and named player from the order; verify every provider reference and amount against retained payment-provider evidence. A browser return, customer screenshot or payment claim alone is not settlement proof.
2. Distinguish the original settled payment from additional money. Keep the original membership term, payer, handoff and receipt records intact. Late money must not activate an expired/cancelled checkout; additional money must not extend the original membership.
3. Review previous resolution notes. Agree and authorise any remedy separately through Cape Tennis's established financial process. This interface provides no refund, transfer or membership-activation action. It does not establish a refund entitlement or authorise an external financial operation.
4. Complete an explicitly authorised external remedy through the appropriate financial process and retain its evidence. Existing refund/ledger services remain the boundary for any application financial mutation; do not edit wallet balances, PayFast receipts or order payment fields manually. If no canonical Plus refund operation supports the approved remedy, treat that as a separate implementation/operations task before executing it.
5. Record the external evidence reference and resolution note, confirming all listed receipts were reviewed. Do not enter credentials, bank account numbers, card data or unnecessary personal data. The action records a critical canonical audit event and changes no money or membership state.
6. The note applies only to the immutable receipt-set fingerprint reviewed. A newly received provider receipt reopens review. Replayed callbacks for existing receipts do not reopen the same evidence set or send a second alert. An alert queued before its evidence was reviewed is suppressed at delivery.

The customer support address is `support@capetennis.co.za`. Support requests must include the `P` order reference. Wrong-player purchases, unavailable data and refund enquiries require payer verification and a reviewed decision; no automatic refund or transfer policy is invented here. Legal/commercial approval of launch copy and the remedy policy remains a launch-owner responsibility.

## My Tennis Rating navigation

Cache tabs only after a successful visit; do not launch speculative requests for
all other tabs or make selected navigation wait for them. Keep the previous panel
until a successful response, retain URL/history filters, cancel superseded reads
and stop the spinner after the bounded foreground timeout. Failure offers an
explicit retry and an ordinary full-page link through the same authorised route.

The Rating panel displays positions and the age-group Top 10, so it uses
`PlayerRatingTopTen::build()` to project the saved comparable public population.
`context()` returns access/filter metadata only and must not be rendered as an
empty leaderboard. Keep that lightweight path for consumers that do not display
comparisons; Profile and Activity must not load the full leaderboard. Actual match
comparison data retains its existing public/entitlement projection. Page reads
never fit ratings or request a derived-data refresh.

For ordinary members, narrow comparable candidate IDs using the existing
age/gender scope before public-participation discovery. Keep the original global
candidate cap and run all canonical publication and roster checks on the remaining
IDs on every read; demographics never grant access. Super-user browsing and
match-player projections retain their separate scopes. This reduces discovery
work, not the exact saved-history fingerprint or publication validation.
For a selected super-user preview, discover candidates only from the requested
ball pool and Green/Yellow for the existing age/overall summaries. Other ball
pools remain separate and are loaded only when requested; the unselected
cross-pool directory still projects all three independent lists.

Public individual participants are resolved as one set from published finishing
results and both sides of published fixtures, then fetched from the player
directory. Each branch retains its exact event, division and registration
membership, including the soft-deleted membership exclusion. Published historical
participation is independent of current payment or withdrawal state; do not add
active/paid gates to this path. Doubles members retain their existing visibility.
The separate active-paid first-member entry path and canonical public team-lineup
fallback are unchanged. This avoids repeating the same correlated result/fixture
subqueries separately for every candidate player. The set is still bounded by
the comparable candidate IDs and checked again on each request.

Results opens with a saved Results overview: season totals, win rate, latest-five
form and a wins/losses activity chart. The selected year controls both totals and
chart; All seasons totals cover all saved seasons while the chart covers the
latest 12 saved months. Selecting a month narrows the match register only. Saved
date/update status stays visible, and detailed coverage notes are expandable.
Recently posted or updated seven-day scores follow separately, with three rows
initially and a native disclosure for the rest. Polling preserves the disclosure's
open state and summary focus. Unavailable saved data never implies zero played
matches; an empty selected season shows zero counts and no win-rate estimate.
This is presentation of existing scoped records, not a new rating
calculation. See `PlayerPlusRecordTest` and `tests/Browser/results-overview.cjs`.

`MyTennisRatingNavigationTest`, `PlayerRatingTopTenTest` and
`tests/Browser/my-tennis-navigation.cjs` cover the boundary. A screenshot showing a
spinner or retry message does not establish a production request's status or
cause; inspect the actual target environment only with current authority.

### Avoid repeated work while rendering a tab

The My Tennis controller opens a private HTTP-GET read scope for one response.
Within it, the exact global source fingerprint and the validated history file
are read once and reused by the header, graph and record projections. Identical
publication manifests, the exact external-evidence fingerprint and historical
match contexts for the same date and reader limits also share their validated
result inside that one response. Different manifests or historical cutoffs get
independent checks. The full fingerprint calculation, manifest checks and history
validation are unchanged; actor-specific entitlement and opponent projections
are not memoized. The scope is removed in `finally`; the next request checks
current sources and publication again. POSTs
and unrouted command/builder contexts retain fresh reads, including before/after
builder checks. There is no cross-request TTL or persisted availability cache.
`PlayerRatingReadScopeTest` verifies the query reduction and next-request
publication revocation, while `PlayerRatingTopTenTest` preserves pool, summary
and public-participant boundaries.

Tab navigation pauses outgoing Rating fragments/status reads, stage-info GETs
and Results polling before requesting the selected workspace. The old panel
remains until success. On failure or cancellation it resumes read controls,
including an interrupted rating filter; late responses from aborted reads cannot
replace the retained content. Early pause does not abort or replay POST actions.

A Profile page containing a transient last-five pending placeholder is not
stored as a successfully visited tab for the normal 60-second cache lifetime.
Returning to it therefore checks the server again. This does not create a graph
or replace it with monthly history: Profile and Rating still share the same
saved last-five reader, while Rating's monthly-history chart is separate data.
Missing, stale or inaccessible match artifacts remain unavailable.

Verify `PlayerRatingReadScopeTest`, the focused My Tennis frontend tests and
the read-navigation browser regression. These tests establish bounded duplicate
work and request lifecycle behavior, not measured production latency or that a
particular player's saved artifact is available.

## Player overview and monthly report

The default Profile tab keeps player details and editing available beneath the
performance overview. For a selected related player with rating access, it reads
the existing saved playing record once: current SAST calendar-year totals, recent
form and a current-calendar-month report. The monthly summary uses all eligible
saved rows, independently of the paginated results register; the previous
calendar month can belong to the preceding year. Links retain the selected player,
season and report month when opening Results.

Report counts describe available saved singles records, not complete career
coverage or a measure of improved playing strength. Show the saved cutoff and
pending status. If the source cutoff predates the report month, show awaiting
coverage rather than zero activity. Unavailable records remain unavailable.
Keep disconnected, historical-connection and finishing-only rating qualifications
beside the score. Overview requests never fit ratings or request a refresh.

Non-Plus profiles and feature previews show readable, explicitly fictional
examples without reading private rating or match sources. Membership links retain
the existing configured offer/availability flow; examples never establish that a
particular player has sufficient evidence. Purchasing remains separate from
relationship access.

`MyTennisOverviewTest` covers the aggregation/access and presentation boundaries.
Export fictional HTTP-rendered pages with `CT_OVERVIEW_EXPORT_DIR` and run
`tests/Browser/my-tennis-overview.cjs` for responsive overview/preview checks.

## Verification and launch gates

Saved ability and twelve-month history readers establish a 512 MiB request/process-local memory floor before fetching or decoding their private payloads. Larger and unlimited budgets are preserved. This does not alter `php.ini`, environment configuration or the global server limit. If PHP cannot provide that budget, readers withhold the data before loading it; the history file's existing 20 MiB cap and all policy/publication validation remain in place. Page reads still do not calculate ratings, enqueue a refresh or change source data. Verify normal web-process reads against representative saved data, as a successful CLI builder alone does not establish reader availability.

Local implementation does not authorise deployment, production migration execution, activating sales, contacting customers, or live payments. Keep the following evidence separate:

- [ ] Focused payment/access/launch regression tests pass, including exact configurable prices, stale quotes, actor isolation, duplicate callbacks, expiry, per-receipt review and managed-mail tampering.
- [ ] Complete feature suite reviewed after shared payment/mail/settings checks.
- [ ] PHP syntax, `git diff --check`, route registration and Blade compilation pass.
- [ ] Synthetic browser journey verified at narrow phone, wide phone, tablet and desktop widths, including empty, unavailable-data, paused-sales, covered and pending-payment states.
- [ ] Exact Plus migrations `database/migrations/2026_11_01_140000_create_player_plus_memberships.php` and `database/migrations/2026_11_01_180000_add_player_plus_monthly_subscriptions.php` are allowlisted and their target-environment states are independently verified before exposing the applicable plans. Monthly offers remain off until the subscription migration and PayFast recurring-account capability are verified. Optional pathway/data features use their separately reviewed migration requirements.
- [ ] Target environment has a usable current rating snapshot and saved twelve-month history, with representative data coverage reviewed. A purchase must not be offered as guaranteeing a rating.
- [ ] Production cron and the ordered `03:02 SAST` Plus calculation pipeline, overnight continuation/completion, `08:32 SAST` membership notifications, queue health and refresh failures are independently verified. See `docs/player-plus-nightly-refresh.md`; the nightly pipeline replaces independent midnight/daytime automatic ability fits.
- [ ] PayFast merchant/callback configuration and a controlled end-to-end payment/expiry/reconciliation smoke test are separately authorised and verified. Tests use synthetic callbacks and establish no live-provider evidence.
- [ ] Valid monitored operator contact, reconciliation ownership, managed-mail review/retry procedure and financial remedy process are confirmed by the launch owner.
- [ ] Customer purchase disclosures and support response process are approved; annual price and sales availability are consciously selected by a super-user.

Focused local command (serial database use):

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='ct_testing'
$env:CACHE_STORE='array'
$env:LOG_CHANNEL='null'
$env:CT_TEST_ARRAY_TRANSPORTS='1'
php vendor/bin/phpunit tests/Feature/PlayerPlusMembershipTest.php tests/Feature/PlayerPlusLaunchTest.php tests/Feature/PlayerRatingHistoryTest.php tests/Feature/PlayerPathwayTest.php tests/Unit/DeploymentConfigTest.php --fail-on-skipped
```

For synthetic HTML browser fixtures, set `CT_PLUS_QA_HTML=1` while running `PlayerPlusLaunchTest`. The test exports actual HTTP-rendered pages to `storage/framework/testing/player-plus-*.html`, containing synthetic test data only. Use the bundled Node runtime, not the older system Node, to run the browser harness:

```powershell
$env:NODE_PATH='C:/Users/pajou/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
& 'C:/Users/pajou/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe' tests/Browser/player-plus-launch.cjs
```

The harness uses Edge and allows local static assets only; it must never submit a real checkout, notification or financial review. Test fixtures do not establish production mail delivery or live payment confirmation.
