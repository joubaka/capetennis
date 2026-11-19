# Cape Tennis knowledge carried forward from Codex chats

This is a curated map of reusable decisions and investigation lessons, not a
release inventory. Read `AGENTS.md` first, then verify the relevant owning code
and tests. A chat's local test result does not establish current production
behavior. Do not turn an audit recommendation or an unfinished chat into policy.

## Ratings, evidence and pathway

- Standing user decision (2026-11-16): Red, Orange and normal CTR (Green/Yellow)
  must always remain separate across evidence, scores and all consumers.
  "Overall" means all ages in Green/Yellow CTR, never all ball levels. Never
  borrow another pool's score when a rating is absent. This supersedes earlier
  combined-model product guidance. See `AGENTS.md` and
  `docs/performance/ball-level-classification.md`. The compatibility `all_age`
  key means the Green/Yellow graph, never a fourth pool; old mixed artifacts
  must be withheld under the current policy until rebuilt. Verify current
  implementation and saved-artifact policy before claiming compliance.

- Single-age U9 divisions (including A, B and combined A/B) and exact U10 B
  divisions default to Orange across all tournaments, including generic standard
  courts. Explicit ball conditions take precedence; multiple-age labels do not
  establish this fallback, and combined U10 A/B does not establish U10 B.
  Consult `docs/performance/ball-level-classification.md` and verify
  `BallRatingModel` and `BallRatingContext`; classification changes invalidate
  saved calculations.
- The Super Admin directory choices are Red CTR, Orange CTR, CTR and Overall
  (all ages). CTR and ordinary Overall read the same saved Green/Yellow
  (`standard`) graph; Overall clears age restrictions and uses one all-age list.
  Red CTR and Orange CTR remain independent, age-independent lists. Filters
  change membership, never strength. Historical combined graphs are legacy
  artifacts, not an authorized rating pool or fallback. See
  `docs/performance/ball-level-classification.md` and
  `PlayerRatingProfileDirectory` before changing directory/detail links.
- The shared solver fits one explicit ball pool at a time; its default is
  Green/Yellow. Red and Orange callers must pass their own pool. Legacy combined
  behavior is superseded, not an authorized fallback. Verify `AllAgeRatingModel`,
  `BallRatingModel`, snapshot validation and their tests; all-age comparisons
  must stay within one ball pool.
- Missing, disconnected, invalidated, unpublished or non-comparable evidence must
  remain unavailable or explicitly qualified. Never invent calibration, replace
  a missing shared score with a local component score, or call confidence a
  guarantee of accuracy. Changes in comparison groups are not measured progress.
- Check attached event documents when structured evidence is absent, as required
  by `AGENTS.md`. A selection list or finishing order is not proof of played
  matches. Check the actual document and preserve identity/publication gates.
- Pathway assessments must use the relevant ball-level score and current
  comparability/freshness policy; do not infer strength from age or badge color.
  Find the current pathway service and focused tests before adjusting thresholds.
- By explicit product decision, Plus tournament options map a comparable,
  nonlocal Green/Yellow CTR to Wilson B at 50 or below, Wilson A above 50
  through 75, and TSA above 75, using configured cutoffs. These are Cape Tennis
  guidance bands, not official qualification or externally calibrated readiness.
  Saved-stage positioning may survive a pending refresh with explicit saved
  wording; fresh recommendations retain assessment freshness checks. Preserve
  Red/Orange targets, player/publication gates and pending-move handling. Verify
  `PlayerPathwayService`, `PlayerPathwayTest` and RK-013 before changing this map.
- Tournament options use one named presentation state across the hero, map and
  event suggestions, including Red/Orange, update-pending and recorded moves.
  Upcoming suggestions use explicit published-only discovery (administrator
  `visibleTo()` includes drafts), exact category/type evidence and canonical
  event-scoped ball context; generic standard conditions never prove Wilson/TSA.
  Discovery is bounded and read-only, and a stage match never establishes entry
  eligibility. See `PlayerPathwayEvents`, `PlayerPathwayEventsTest` and
  `docs/performance/all-age-rating.md` for matching limits and empty states.
- The separate match-influence tool is an offline sensitivity estimate on saved Overall CTR:
  baseline minus a canonical refit omitting one exact eligible match at the same
  date. Preserve component, reference, backbone and connection comparability;
  withhold invalid refits. These values are non-additive, are not points awarded
  per match or historical monthly gains, and must not substitute for ball-level
  badges. Page reads only project a current private artifact behind the existing
  rating-access gate. See `docs/performance/match-influence.md`.
- Before/after match CTR in Plus is a retrospective scheduled-order
  reconstruction, not an archived actual rating. Use each target day's full
  historical evidence window and strict chronological before/after pools;
  withhold ambiguous order and changed comparison scales. Simultaneous unrelated
  matches on other courts are excluded from both cutoff fits; a simultaneous
  match sharing either target participant still makes the pair unavailable.
  Never substitute
  today's leave-one-out rating or fixture-ID order. Keep the proxy basis visible
  and calculations offline. Interactive match explanations must project saved
  accepted-evidence aggregates, distinguish network counts from the player's
  own evidence, and never refit on click. Individual evidence tables must use
  exact accepted own-match membership and target-day validated metadata; apply
  restricted-source access rules to each nested result, and label missing or
  capped details honestly. Opponent CTR must also pass the canonical public
  match-comparison gate and use the selected comparison's saved fitted values;
  a current-score permission lookup must never replace historical reconstruction
  values. See
  `docs/performance/match-ctr-timeline.md`.
- The authorized selected Plus Profile prominently shows the saved last-five
  match before/after CTR graph directly below its shared header, before season
  reports. Reuse `PlayerMatchProgress::forPlayer()`; keep the full explanations
  on Rating and retain scheduled-order, fixed-scale and missing-artifact states.
  This extra Profile projection never authorizes a build or refresh on GET.
  Verify `MyTennisOverviewTest` and `tests/Browser/profile-match-progress.cjs`.
- Automatic Plus materialization uses one ordered overnight pipeline: current
  ability, monthly history, then each active Plus player's match and event
  reconstructions. Keep its saved cursor, context guards, bounded continuations
  and prerequisite retries; do not restore daytime pending fits or calculate on
  GET. Base completion and partial/failed player reconstructions are distinct
  outcomes. Verify current host ticks before changing the 03:02 SAST start;
  local schedule tests never establish production cron execution. See
  `docs/player-plus-nightly-refresh.md` and `PlayerPlusDerivedRefreshTest`.

## Player Plus, identity and offers

- Sponsoring registration or buying Plus does not establish player ownership,
  create a family relationship or authorize private history. Trace account/player
  link creation, legacy links, revocation and every private-access caller.
- Historical access audits identified unsafe assumptions around existing links.
  Verified-manager invitations and legacy-link review were proposals, not a
  completed verification system. Do not trust all legacy links as verified or
  globally replace link checks without an explicitly scoped implementation.
- When changing linking, check existing families, second guardians, unrelated
  sponsors, new/duplicate profiles, team claims, Trials declines, My Tennis,
  unlinking, notification contacts and support tools. Preserve paid entitlements,
  receipts and payer control independently of family access.
- Non-Super rating views must preserve their intended bounded audience and search
  projections. A hidden table is insufficient: inspect response payloads and
  endpoints. Verify current rules in `PlayerRatingTopTen` and its feature tests.
- Use configured offer prices and availability consistently across marketing,
  signup, previews and checkout. Showing monthly/annual options does not authorize
  enabling sales. Preserve stored prices and agreed renewal terms on old orders;
  do not encode an old chat's price or enabled state as permanent truth.
- Admin grants, access suspension, billing cancellation and financial reversal
  are separate operations. Do not manufacture a receipt or paid order to repair
  access. Consult `docs/player-plus-management.md`, `docs/player-plus-monthly.md`
  and their owning services before support changes.

## Product and browser preferences

- Registration player confirmation must end with an explicit saved button state,
  not hidden loading markup. Keep the served standalone checkout script and its
  resource counterpart aligned; ambiguous saves require read-only confirmation
  before retrying. See `docs/registration-checkout-feedback.md` and the checkout
  player-save state and rendered-browser regressions.

- Use Cape Tennis navy, blue and orange for Player Plus branding. Reserve green
  for meaningful success or ball-level states. Keep rating/age badges legible and
  compact, and keep scheduling focused on one primary workflow.
- Individual draws share Setup, Players, Matches, Schedule, Review & publish and
  Results navigation. Saved player placement is independent of fixture generation;
  navigation and progress refresh must remain read-only. Preserve scoped day/draw
  returns and separate draw visibility from published times. See
  `docs/draw-workflow.md`, DK-14 and `DrawWorkflow`; team and advanced operations
  retain their canonical services and safeguards.
- Draw workflow completion/readiness and stale-edit contracts are detailed in
  `docs/draw-workflow.md`, DK-14/DK-33 and repair guides RK-054/RK-055. Consult
  `DrawPhaseOneReadinessTest` and `DrawScoreRevisionTest` before changing these
  boundaries; score-content, participant, assignment and rebuild-state revisions
  have different scopes and must not be substituted for each other.
  MySQL current-read and real-contention regression details are in the same guide;
  parent locks alone do not refresh an earlier REPEATABLE READ relationship snapshot.
- Public draw cards, headers and fixture empty states share filtered published
  schedule availability, dates and venues. Configured fallback venues are planned;
  the earliest visible match is not necessarily the historical draw start.
  Return fragments must resolve the visible mobile/desktop draw section, and
  selected public tabs retain their URL. See `docs/draw-knowledge/public-workflow.md`,
  `PublicDrawPresentation` and `PublicTournamentWorkflowTest`.
- New two-stage draws may explicitly select crossed top-two championship
  playoffs plus one lower round robin before later-stage structures exist.
  `PendingHybridRoundRobinService` prepares stable sources, then normal reviewed
  progression qualifies players without replacing fixtures or bookings. Count
  phase-one canonical outcomes only; warn on unresolved ties and suppress prompts
  after confirmation. Carry mutual lower-player results through read-only source
  references, never duplicated fixtures or rating evidence. Historical completed
  draws retain a separate explicit `HybridRoundRobinService` conversion; prompts
  must never call it automatically. Ordinary equal-position groups start with
  fresh scores. See `docs/draw-knowledge/hybrid-round-robin.md`, DK-04, DK-10,
  DK-31 and RK-049; recheck current state and operational authority for live work.
- Detailed RR standings use canonical completed outcomes, precise percentages
  and per-opponent evidence; a saved set alone is not a match win. Blank historical
  scoring settings retain their legacy fallback until an explicit scoped
  one-set correction is reviewed. See DK-32 and
  `docs/draw-knowledge/detailed-standings.md`; do not replay scores or duplicate
  carried fixture evidence to refresh a table.
- Reuse established loading helpers and consistent blue modal/loading feedback
  for slow requests. Handle success, failure and session expiry so a spinner
  cannot remain indefinitely. A loading indicator is not evidence of performance.
- Set-score completion uses the selected format's per-set types, including a
  deciding match tiebreak where configured. Preserve loaded and manually entered
  scores; only generated suggestions may follow source edits. Ambiguous, timed,
  custom and retirement scores stay manual. See `public/js/set-score-grid.js`,
  `TennisScoreFormat` and `tests/Frontend/set-score-grid.test.cjs`. Legacy Flexible
  Monrad full best-of-three uses a third-set match tiebreak, as enforced by
  `FlexibleMonradScoreValidator`; do not infer three full sets from the set count.
- Show the latest saved rating update when relevant; distinguish saved evidence
  from a refresh in progress. Verify timestamps against the application timezone.
- The shared My Tennis tab header shows the selected player's authorized saved
  rating summary, including on Results and Opponents. Keep this personal badge
  separate from the published, comparable Overall CTR used in match comparisons.
- Results leads with saved season totals, form and match activity. Month links
  filter only the match register; totals remain season-wide, and All seasons
  totals may exceed the chart's latest 12 saved months. Keep recently posted or
  updated seven-day scores distinct from saved aggregates. The native recent
  results disclosure must retain open state and summary focus during polling.
  Verify `PlayerPlusRecordTest` and `tests/Browser/results-overview.cjs` before
  changing this presentation; it never recalculates ratings or results.
- The plain My Tennis landing opens Profile, including for players with rating
  access. Explicit player and tab links retain their destinations. The full
  Tournament options level map is always visible. Revalidate remembered players against
  the current account relationship before displaying personal details.
  Activity and profile editing are standard features; Plus tabs and private
  ratings follow the selected player's entitlement.
- Profile's performance overview uses the selected related player's existing
  rating-access gate and saved playing record. Monthly reports use the SAST
  calendar month and show the source cutoff; missing coverage is not zero
  activity. Previous-month totals include a preceding calendar year when needed
  and are independent of result pagination. Non-Plus samples remain explicitly
  fictional and never read private evidence. See `docs/player-plus-launch.md`
  and `MyTennisOverviewTest`.
- My Tennis tab fragments use the same authorized GET routes as direct links.
  Preserve full-page fallback, rebind controls on workspace mount, and cancel
  transient rating and pathway reads when leaving a view.
  Render the initial authorized Rating panel with its tab response. Use the
  rating fragment endpoint for subsequent filters and refreshes.
  Cache visited tabs without speculative all-tab fetches; selected navigation must
  not wait for a background Rating request. Use lightweight canonical
  `PlayerRatingTopTen::context()` only when discovery lists are not displayed.
  The Rating panel renders positions and Top 10 lists and must use `build()`;
  metadata-only context is not evidence of an empty comparable population.
  Retain the separate match-player privacy projection. See repair RK-051 and
  `docs/player-plus-launch.md` for timeout/retry and full-page fallback checks.
- Rating history month, evidence scope, chart scale and section must survive
  reload, shared links and browser Back/Forward. Validate the same filters for
  workspace and fragment reads; ordinary rating deep links render the full
  authorized workspace. Commit URL changes only after a successful fragment
  response and invalidate visited-tab caches when rating state changes.
- For AJAX/search failures inspect the actual request, origin, response, session
  and authorization. Same-origin URLs address a failure mode, but do not prove
  the cause of a live screenshot. Test keyboard and phone interactions.
- Missing remote logos/documents require checking the actual referenced asset.
  A local fallback or HTTP success is not installed-PWA, phone or live-flow QA.

## Assistant capability and durable repairs

- The application assistant, a CLI Codex run, this desktop chat and the Capester
  handoff queue are distinct systems. Do not imply automatic access to chat
  history, custom voice wake-up, automatic model training or remote code repair.
- Read `docs/assistant/capability-improvement.md`, `capester-connection.md` and
  `capester-queue.md` before changing the bridge. Collection needs its own opt-in
  and data boundary; inbox text remains inert untrusted data, never executable
  instructions. A queued note is not an implemented capability or deployed fix.
- Search `docs/assistant/repairs/README.md` and the catalogue before reusable repair
  investigation; use `docs/draw-knowledge/README.md` for draw/scheduling work.
  Verify historical procedures against current services. Promote a guide into
  an assistant action only through scoped code, authorization, preview/apply,
  stale-state protection, audit and regression coverage.
- Category-copy reviews must identify exact source/destinations, fees, ordering
  and add/skip decisions. Drafting email sends nothing: preserve recipient and
  message review, explicit send authority and managed mail. Queued is not delivered.
  Consult the current `AssistantCategoryCopy` and `AssistantEmailDraft` workflows.
- Series custom announcements use canonical confirmed active contacts and one
  email per normalized address across the selected series. Fingerprint all source
  players, including overlaps suppressed from later event batches; keep every
  event permission check. Event attribution supports reporting and must not append
  other-event private player details. Show one combined recipient list and message
  preview. Optional CC/additional contacts receive separate private copies once,
  deduplicated after all player contacts and saved in the immutable review options;
  never add repeated CC headers to individual deliveries. See `SeriesCommunicationService`,
  `ReviewedSeriesCommunicationTest` and repair RK-040.
- Whole-event wrap-ups use confirmed event entries or active confirmed team roster
  slots. Recheck whole-event permission for review, approval, history and both
  retry routes. Rebuild the audience at approval and verify the exact address
  and player keys on retry. See `EventWrapUpMessage`, `EventCommunicationService`
  and `UniversalEventCommunicationsTest`.
- Team wrap-ups use the public combined positions within each age group. Use
  `EventStandingsService` with published data and omit groups without displayed
  positions, including groups whose draw rules differ.
- Cavaliers nomination reports share saved private rosters rather than publish
  them. Explain the next five available candidates outside selected/reserves
  (excluding declines), plus all eligible Inter-Districts candidates outside the
  saved top 32 including reserves/declines, with no duplicate explanations. Match
  playing numbers must come from per-match roster evidence, never merit rank.
  Result flags describe counted singles evidence and cannot establish an
  unrecorded selector decision; missing evidence stays unavailable. Preserve
  source-access checks and the reviewed email/PDF snapshot through managed mail.
  See `docs/architecture/event-type-template-parity.md`, `CavaliersRosterReport`
  and `CavaliersRosterReportTest`.
- Cavaliers252 registration remains payer-sponsored individual checkout for
  exact published nominees; decline requires an existing linked account or
  canonical event manager. Keep registration admission separate from candidate
  preparation and payment finalization. Confirmed public decline synchronizes
  the private draft with revision/audit safeguards and blocks active entries or
  unresolved/payment-evidenced checkouts without changing financial state.
  See `docs/architecture/event-type-template-parity.md`,
  `CavaliersNominationWorkflowTest` and `PublicCavaliersNominationProvenanceTest`.

## Operational evidence

- Individual PayFast browser returns must report saved order state; reaching a
  success URL is not settlement. Keep the entire callback settlement, receipt,
  wallet debit and entry/invitation confirmation in one retried transaction,
  with after-commit side effects. Verify the callback and return regressions
  before claiming these safeguards are deployed. When a paid registration is
  missing, distinguish provider evidence, web-server delivery, application
  processing and saved state; a missing callback log is not proof of deadlock.
  See `docs/payfast-registration-confirmation.md`.

- Callback URL construction uses `PayfastCallbackUrl` and deployment
  configuration before signing, never the checkout request origin. The first
  global `PayfastCallbackTelemetry` records bounded unverified file ingress and
  observed/inferred outcomes before session/audit failures without weakening
  canonical financial auditing. Neither that telemetry nor recovery proves
  delivery outside Laravel. Verify `PayfastCallbackTransportTest` and successful
  multi-player cases in `RegistrationPayfastConfirmationTest`; see the same
  confirmation guide. Configured ITN fee estimates remain distinct from actual
  authenticated-dashboard recovery amounts.

- The explicit individual PayFast reconciliation command is limited to an
  original zero-wallet, single-player Interpro checkout with independently
  operator-verified on-time settlement followed by canonical automatic expiry.
  It requires fresh dashboard attestation, an actor-bound preview and current
  lifecycle guards; it restores the original soft-deleted entry and invitation
  rather than creating a manual-paid replacement. Preserve actual provider
  amounts/date, separately disclose the reviewed current platform fee and keep
  recovery free of outbound mail. It neither proves callback delivery nor
  authorizes deployment/live application. Verify the owning service and
  `IndividualPayfastReconciliationTest`; see the same confirmation guide.

- On-time ordinary PayFast baskets with missing receipts use the separate
  `OrdinaryDashboardPayfastReconciliationService`, not the Interpro or existing
  late-receipt command. Fresh provider evidence binds one original anchor and
  the complete unchanged original basket, with per-item creation provenance and
  eligibility. Restore all original entries atomically and record one actual
  provider receipt. Strict prior-attempt preservation is distinct from reopening;
  a negative provider observation is time-bound and does not prevent future
  settlement. See `docs/ordinary-dashboard-payment-reconciliation.md` and verify
  its owning service/tests before use. This guidance does not authorize release
  or production application.

- Frontend dependency overrides do not patch an embedded copy inside a vendor
  bundle. Verify the actual emitted execution path and checked-in public assets;
  deployment does not run npm. See `docs/frontend-dependency-security.md` for
  Shepherd's narrowly checked merge remediation and its regressions.

- Mistaken paid individual Interpro beneficiaries use the reviewed single-entry
  correction mapping, never a second paid entry or a rewrite of the original
  checkout/receipt identity. Preserve the payer, one refund entitlement and
  safely cancelled attempts; require exact preview state and replay validation.
  Category changes on corrected entries require separate review before charging.
  See `docs/individual-beneficiary-correction.md`, RK-063 and
  `IndividualBeneficiaryCorrectionTest`; local validation does not authorize
  deployment, migration or live application.

- Free individual administrative entries capture zero Cape Tennis fees using
  effective category pricing; an explicit paid category override remains paid.
  Zero payment amounts have zero PayFast estimates. Preserve explicit historical
  fee snapshots after repricing; existing erroneous snapshots need a separate
  reviewed correction. See `docs/free-registration-fees.md` and the canonical
  entry and ledger services. Withdrawal preserves paid history; frontend
  duplicate checks must distinguish that history from active participation.
  See repair RK-041 and `FreeRegistrationFeesTest`.

- Refund-funded standard team replacements create a distinct original-payer
  checkout; they do not transfer the original payment history. Partial funding
  remains an expiring reservation and requires a usable payer checkout. Active
  managed selection campaigns retain their invitation workflow. Consult
  `docs/refund-funded-team-replacements.md`, `TeamPaymentService` and the focused
  `AdminTeamWithdrawalTest` regressions before changing this boundary.

- Individual division caps include unresolved checkout and category-move holds,
  including orders without an active entry row. Release places through canonical
  cancellation/expiry; elapsed time alone is insufficient. Admission needs current
  reads under the division lock. See `docs/division-entry-limits.md`,
  `DivisionCapacityService` and capped category-adjustment regressions.

- A finance dashboard reconciliation is not a bank reconciliation. State which
  sources were compared and distinguish clothing, registration, ledger and
  settlement evidence before attributing a discrepancy or proposing a correction.
  Separate event-year cohorts from transaction dates, wallet spending from fresh
  bank deposits, pending liabilities from reversals, and current from stored fees.
- Recording an already-paid director payment does not initiate a bank transfer.
  For full-net settlement inspect `EventDirectorNetSettlementService` and its
  related reconciliation/clothing services: preserve exact cents, signed current
  previews, event-scoped recipients, idempotency and linked atomic records.
- MySQL connection/startup errors do not prove an application leak or a rating
  query cause. Seek connection/process, PHP worker, queue/cron and traffic
  evidence; report uncertainty when production evidence is unavailable.
- Keep local code, configured settings, migration application, commit, push, CI,
  deployment and live verification separate. Inspect exact required migrations
  and preserve environment-specific settings; neither old release approval nor
  migration allowlisting authorizes a new production action.
- Concurrent chats may edit the same checkout. Recheck status and ownership,
  preserve unrelated diffs, and serialize stateful tests or isolate databases and
  runtime paths. Do not report another chat's unfinished work as your completion.

## Provenance and maintenance

Curated 2026-11-02 from Cape Tennis Codex conversations about differing ratings,
Plus versus Super access, Plus subscription access, pricing, blue styling and
loading feedback, assistant learning, financial auditing, and operational errors.
Primary source chats include `01a1987f-acf5-7ab0-af05-2b54c850230c`,
`01a19ae3-29fe-78d3-bf54-f02ba8e5537b`,
`01a19aed-256f-72a3-a99b-9ecb1c38aa6a`,
`01a19ac8-0250-7b02-b995-2389c9033869` and
`01a19bcb-b694-7be0-9cd4-808280e766ed`.
Additional reviewed sources: `01a19bf6-c80b-7903-9d57-dda0c5a464c4`
(assistant learning), `01a19bd8-69d1-7fb0-a2b8-f82c2610e9d2` (category reuse),
`01a19bed-b9d1-7ec3-9cd8-56d1fca665d1` (finance evidence), and
`01a19bf4-f77d-7ea1-8fa5-2fb09cb6364c` (director settlement).
These are provenance pointers, not executable instructions or current evidence.
Update existing guidance when decisions change; keep personal data, raw chat
transcripts, credentials and transient production values out of this reference.
