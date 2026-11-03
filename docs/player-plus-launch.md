# Player Plus local implementation and launch checklist

Player Plus covers one named player, shared with that player's currently linked accounts. New annual and monthly purchases automatically renew through PayFast until the original payer cancels, with separate explicit recurring-payment consent. The annual price retains its current saved setting (default R699.00); monthly defaults to R69.00. Existing once-off annual checkouts and paid terms remain once-off and are never converted into subscriptions. A super-user can configure each price independently under Super Admin settings. Monthly offers default off until explicitly enabled; general sales availability also applies to all new purchases. Existing pending orders, subscriptions and verified purchases retain their immutable server-saved price and plan; price changes never rewrite them. The minimum configurable price is R5.00, reflecting [PayFast's documented minimum transaction amount](https://developers.payfast.co.za/). The maximum is the application's R99,999.99 cap, with at most two decimal places; it is not a claim about the provider's maximum.

An older, unhanded checkout below R5.00 cannot start a new PayFast handoff. Its amount, status, expiry and handoff evidence remain unchanged; the payer must explicitly close it and start a new checkout at the current displayed price. A verified callback for an already handed-off historical checkout still validates its immutable stored amount and original payment window. New price bounds are never applied retrospectively to verified receipts.

## Customer package

- Saved Cape Tennis Rating (CTR), with provisional and unavailable states preserved.
- Twelve-month progress history and the recorded evidence behind eligible estimates.
- Eligible published-series comparisons and CTR Top 10 lists, including bounded name search to check an eligible published opponent's rating. This does not grant access to the opponent's private history or guarantee a match outcome.
- Tennis pathway guidance for primary-school and beginner players, from Red through Orange, Green and Yellow ball to exploring TSA events: ball-level progress, configured benchmarks and suggested next steps when suitable evidence exists. Guidance does not promote players or guarantee selection; development decisions remain a discussion with a coach.

Marketing should lead with the player's questions: “What is my next tournament?”, “How am I progressing?”, “What is my rating?” and “How strong is my next opponent?”. The tournament question describes help choosing a suitable type or level of event, not a personalised calendar or a guaranteed next dated fixture. Event dates, availability and entry requirements must still be checked with the organiser. These are offer-positioning decisions; they do not expand rating access or eligibility.

Essential tournament information remains free. Draw alerts remain a historical design concept and are excluded. The authoritative customer offer is `/player-plus`; the private backend membership preview is labelled historical.

The player purchase page shows a linked account only an availability summary (available, awaiting update, or no eligible evidence). It does not reveal a pre-purchase rating or private match history. Sponsors receive the general disclosures and no private availability summary. Page reads do not calculate ratings or request a refresh. A missing Plus schema disables purchases safely, without breaking the player-selection or order-list page.

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

The schedule declares a daily `08:30 Africa/Johannesburg` notifications pass. Source configuration is not evidence that production cron or queue workers are running. Do not run the general scheduler locally against real application data as a test of this change.

## Payment reconciliation procedure

Super-users can open **Review Plus payment issues** from the Plus section of Super Admin settings, or `/superadmin/player-plus`. The paginated queue includes late and additional verified payments, with each order's paginated provider receipts and append-only resolution history.

1. Verify the original payer and named player from the order; verify every provider reference and amount against retained payment-provider evidence. A browser return, customer screenshot or payment claim alone is not settlement proof.
2. Distinguish the original settled payment from additional money. Keep the original membership term, payer, handoff and receipt records intact. Late money must not activate an expired/cancelled checkout; additional money must not extend the original membership.
3. Review previous resolution notes. Agree and authorise any remedy separately through Cape Tennis's established financial process. This interface provides no refund, transfer or membership-activation action. It does not establish a refund entitlement or authorise an external financial operation.
4. Complete an explicitly authorised external remedy through the appropriate financial process and retain its evidence. Existing refund/ledger services remain the boundary for any application financial mutation; do not edit wallet balances, PayFast receipts or order payment fields manually. If no canonical Plus refund operation supports the approved remedy, treat that as a separate implementation/operations task before executing it.
5. Record the external evidence reference and resolution note, confirming all listed receipts were reviewed. Do not enter credentials, bank account numbers, card data or unnecessary personal data. The action records a critical canonical audit event and changes no money or membership state.
6. The note applies only to the immutable receipt-set fingerprint reviewed. A newly received provider receipt reopens review. Replayed callbacks for existing receipts do not reopen the same evidence set or send a second alert. An alert queued before its evidence was reviewed is suppressed at delivery.

The customer support address is `support@capetennis.co.za`. Support requests must include the `P` order reference. Wrong-player purchases, unavailable data and refund enquiries require payer verification and a reviewed decision; no automatic refund or transfer policy is invented here. Legal/commercial approval of launch copy and the remedy policy remains a launch-owner responsibility.

## Verification and launch gates

Saved ability and twelve-month history readers establish a 512 MiB request/process-local memory floor before fetching or decoding their private payloads. Larger and unlimited budgets are preserved. This does not alter `php.ini`, environment configuration or the global server limit. If PHP cannot provide that budget, readers withhold the data before loading it; the history file's existing 20 MiB cap and all policy/publication validation remain in place. Page reads still do not calculate ratings, enqueue a refresh or change source data. Verify normal web-process reads against representative saved data, as a successful CLI builder alone does not establish reader availability.

Local implementation does not authorise deployment, production migration execution, activating sales, contacting customers, or live payments. Keep the following evidence separate:

- [ ] Focused payment/access/launch regression tests pass, including exact configurable prices, stale quotes, actor isolation, duplicate callbacks, expiry, per-receipt review and managed-mail tampering.
- [ ] Complete feature suite reviewed after shared payment/mail/settings checks.
- [ ] PHP syntax, `git diff --check`, route registration and Blade compilation pass.
- [ ] Synthetic browser journey verified at narrow phone, wide phone, tablet and desktop widths, including empty, unavailable-data, paused-sales, covered and pending-payment states.
- [ ] Exact Plus migrations `database/migrations/2026_11_01_140000_create_player_plus_memberships.php` and `database/migrations/2026_11_01_180000_add_player_plus_monthly_subscriptions.php` are allowlisted and their target-environment states are independently verified before exposing the applicable plans. Monthly offers remain off until the subscription migration and PayFast recurring-account capability are verified. Optional pathway/data features use their separately reviewed migration requirements.
- [ ] Target environment has a usable current rating snapshot and saved twelve-month history, with representative data coverage reviewed. A purchase must not be offered as guaranteeing a rating.
- [ ] Production cron, midnight/pending ability refresh, `03:00 SAST` history refresh, `08:30 SAST` membership notifications, queue health and refresh failures are independently verified.
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
