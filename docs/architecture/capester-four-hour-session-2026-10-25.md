# Capester four-hour event parity session

## Authority and time box

The user asked Capester to plan and lead the next four hours while away,
continuing the approved event-type template work and preserving existing changes.
Session starts approximately 08:34 and ends 12:34 Africa/Johannesburg on
2026-10-25 (06:34–10:34 UTC). Do not start implementation after the deadline.
Finish a safe checkpoint and provide one consolidated handoff.

Thread heartbeat: `capester-four-hour-event-parity-session`, every 20 minutes
during the work window. Check current time on every wake. Pause it when work
finishes or the time box ends. Its prompt is bounded to this existing chat and
repository; it is not permission to start unrelated projects or contact others.
On a wake with 20 minutes or less remaining, finish verification and the final
handoff within that turn rather than relying on a later wake. The recurrence is
not extended beyond the authorized cutoff.

No commits, pushes, PRs, dependencies, release preparation, production access or
mutation, production migrations, publication, outbound communications, or
security/payment/role configuration changes are authorized. Preserve all existing
tracked and untracked work. Decisions that materially change eligibility, access,
money, publication, communication or established-event type conversion remain
decisions for the user rather than unattended implementation choices.

## Baseline at session start

- Branch `main`, previously inspected HEAD `997680d08b85f1b54652946e96439a67c9c3e1d3`;
  recheck actual state before any later release-related claim.
- The worktree contains substantial user-owned changes to payment, scheduling,
  contact/mail, player and event administration code. Do not restore/reset/stage
  them or treat them as part of this task's implementation.
- The preceding batch added `EventTypePresentation`, delegated the public model
  accessor, aligned venue fixture preparation, and shared the document card among
  seven public layouts. It preserves the Trials override and specialized business
  workflows. See `event-type-template-parity.md` for the local type inventory and
  preservation matrix.
- Prior verification: 37 presentation/scoring PHP tests passed (189 assertions),
  3 JavaScript deletion tests passed. Independent feature batch: 44 tests,
  289 assertions, 43 passed and one existing mail greeting mismatch failed.
- The existing failure is `InterprovincialTrialSafeguardsTest` initial-review
  greeting: it expects `Hello First Nominee`, while the unchanged invitation
  template, including HEAD, renders `For First Nominee`. The failing path bypasses
  changed event presentation. Do not silently alter unrelated mail wording.
- Synthetic shared-document browser checks at 320/390/768/1440 CSS pixels passed
  long-name, empty and read-only layout checks. This is not full event-journey
  browser evidence. Temporary preview server is stopped and viewport reset.
- Main local `ct` access was read-only type inventory on verified loopback MySQL.
  Database-mutating tests must use `ct_testing` with explicit testing environment,
  null logging, array mail transports and isolated runtime paths, serially.

## Work plan

1. **First hour: characterize complete presentation journeys.** Lead and quality
   specialists inspect remaining gaps in public Interpro/Trials and other stored
   type render paths. Choose a concrete bounded test-first scope. Capture role,
   publication, registration, fixture, scoring and document behavior using
   synthetic fixtures without main-database mutation.
2. **Second hour: implement the next safe common module.** One developer owns the
   application boundary. Prefer extracting existing matching presentation/data
   contracts after characterization, keeping invitations, nominations, pairing,
   roster, clothing, qualification and payment workflows dedicated. If no safe
   extraction is justified, strengthen rendered parity coverage instead.
3. **Third hour: independent review and browser verification.** Quality verifies
   finalized changes and role/publication/empty/error states. Financial/security
   review checks any authorization or private-data boundary. Run stateful tests
   serially and keep real journey evidence distinct from component previews.
4. **Fourth hour: close gaps and consolidate.** Fix scoped regressions, rerun only
   justified checks, verify routes/Blade/syntax/diff, record outstanding product
   decisions and produce a concrete file/test handoff. Stop early if the bounded
   goal is complete; do not invent work merely to consume four hours.

## Coordination and ownership

- Capester `/root`: scope, this document, coordination, acceptance and handoff.
- Existing `event_audit` (`ct_lead`): read-only next-scope investigation.
- Existing `quality_event_parity` (`ct_quality`): read-only evidence planning now;
  independent verification after implementation. No database lane yet.
- `implement_interpro` (`ct_developer`): preceding scope complete; reuse for the
  next explicit bounded writing assignment after the lead reports.
- `review_event_boundaries` (`ct_financial_security`): preceding review complete;
  reuse for any new sensitive/publication boundary review.
- Never run parallel writers on a feature boundary. Stateful tests/browser
  fixtures must have isolated databases/runtime paths or run serially. Every
  specialist inherits the user's original authority limits.

## Progress and next action

2026-10-25 08:34 SAST: Session plan and bounded thread heartbeat created. Lead and
quality specialists completed read-only investigation. The next developer scope
is full rendered HTTP parity coverage, followed by extracting only the matching
Interpro/Team region-directory wrapper after characterization passes. Lead scope3
(Interpro published draw-link unit extraction) is optional after scopes1–2 pass
independent review. No new policy, conversion, mail, player or scheduling changes.

Developer ownership: new `tests/Feature/EventTypeRenderedJourneyTest.php`, existing
`tests/Feature/InterproTemplateParityTest.php` as needed, Interpro and Team public
type views, and a new `partials/event-region-directory.blade.php`. Parent owns this
session document and the parity document. QA must wait for developer test-lane
handoff before stateful tests.

Acceptance: effective template and actual public HTTP render for every stored
identity and missing type0; dedicated Trials with both base families; legacy
Interpro separately; role and nomination publication boundaries; correct shared
directory links/empty state; no foreign/private/draft content; unchanged row counts
after GET. Export synthetic response HTML to ignored isolated artifacts for
browser checks with real assets and mutations/network destinations blocked.

Current owner: `implement_interpro` is actively assigned and owns the database
test lane until explicit handoff; no reviewer may run stateful tests concurrently.
Parent prepared the ignored allowlisted snapshot router at
`storage/framework/testing/capester-event-journeys-20261025/browser-router.php`.
It serves no Laravel routes, blocks form/connect requests and nonfragment links,
and serves only synthetic snapshots plus supported static public assets. It is
not running yet. Developer exports snapshots into its `snapshots/` subdirectory.

Next: developer writes characterization tests, runs them serially on `ct_testing`,
then extracts only existing matching region markup and verifies. QA/security
review finalized scope independently. Update this section with exact files,
tests, lane ownership, unresolved risks and next action at each checkpoint.

### Directory source boundary discovered during characterization

Legacy Interpro reads `event->region_in_events`, but Event defines only `regions()`;
Team uses the controller-prepared `regions` collection. Do not silently swap those
sources during extraction. `EventPageDataService::prepareRegions()` applies
`EventTeamScope` only to base-team, non-Trials events; legacy individual-family
Interpro can otherwise include uncategorized teams from shared regions. Restoring
the broken directory requires a separately bounded canonical-scope fix and tests
for foreign categorized teams and ambiguous uncategorized shared-region teams,
plus independent boundary review. Characterize/preserve current behavior first.

### User-authorized completion scope (08:42 SAST)

The user returned and explicitly requested finishing the Interpro directory fix
and full event-type testing, preserving existing changes and functionality.
This authorizes the separately bounded directory repair described above.

`implement_interpro` remains sole application/test writer and database lane owner.
Ownership now also includes `app/Services/EventPageDataService.php`. Restore the
legacy Interpro directory from controller-prepared regions only with the existing
`EventTeamScope` applied, retain unambiguous unique-region legacy teams, exclude
foreign categorized and ambiguous shared-region uncategorized teams, and leave
Trials' nomination/registration/programme policies intact. Shared directory markup
must preserve existing Team styling, region tabs, roster modes, payer controls,
clothing, document and scoring behavior. No unrelated cleanup or mail changes.

`review_event_boundaries` is reviewing this restoration's role, payer, roster and
endpoint boundaries read-only. `quality_event_parity` is planning independent
full-feature verification but may not run stateful tests until developer handoff.
Current new full-page characterization batch is still running; no pass claim yet.
After focused tests pass, run the full feature suite serially in the isolated
testing environment, classify any pre-existing unrelated failures, perform actual
browser layout/tab/modal checks on exported synthetic full-page snapshots, and
produce the final evidence-based handoff. Parent retains documentation ownership.

### Completion-scope boundary decisions and verification

- Use a directory-only domain predicate on Event rather than authorizing by a
  presentation filename: non-Trials events that are base-team, or legacy type13
  without Masters/Camp precedence. Region preparation consumes it and applies
  existing `EventTeamScope`. Do not change generic order/financial membership
  checks in this batch; independent review identified recovery regressions from
  naively applying the same stricter rule to historical orders (details below).
- Do not extend `ClothingOrderService` acceptance. It currently requires a
  base-team event and an exact category/event team association. Hide unsupported
  new clothing-order controls and profile-before-clothing prompts in restored
  individual-family Interpro; keep existing paid-order history/details visible.
  Existing supported Team ordering/modal behavior must retain regression coverage.
- Independent DB-free frontend run: 245 tests, 192 passed, 53 failed, no skipped.
  Artifact: `storage/framework/testing/event-type-full-qa/frontend.tap`.
  Failures are in twelve other draw/head-office/scheduling/team-workspace test
  files; classify against their owning source before calling them pre-existing.
  Task-owned document deletion tests passed. No unrelated harness repairs made.
- New full-page baseline initially failed on a synthetic nonexistent
  `events.entry_deadline` fixture attribute; developer removed that attribute.
  Corrected characterization run is active; no final pass claim yet.
- The full feature run will include its designed isolated SQLite HTTP clothing
  review fixture after QA verified UUID workspace containment, loopback-only server,
  disposable database/session paths and no refund/provider execution or mail send.
  Main suite still runs serially on explicit local `ct_testing` with array mailers.

### Financial compatibility finding: strict public scope is not order authority

Independent financial review found that unconditionally tightening
`ExternalTeamRosterService::teamBelongsToEvent` for legacy individual-family
Interpro would block existing paid withdrawal/refund choices, pending checkout
handoff and verified original-order ITNs for uncategorized teams whose regions
are now shared. Those paths check generic membership before or inside canonical
order handling. That would introduce a functionality loss and is not accepted.

The task-introduced generic service guard is therefore restored to its exact prior
condition. No payment-controller, ITN or order-recovery changes are part of this
directory fix. Public directory membership remains strict and event-isolated.

Separate pre-existing security attention: generic direct requests for legacy
Interpro can identify uncategorized teams via a shared event/region link alone.
Closing that older ambiguity requires distinguishing new admission from existing
persisted-order authority. A future bounded fix must retain exact event/team/
beneficiary and payer checks for authenticated order operations, verified provider
signature/COMPLETE/amount/locking/idempotency for callbacks, reject foreign
categorized teams, and never grant new registration/profile/public roster access
merely because a historical order exists. Do not silently implement a broad
order exception or report this direct-endpoint issue as fixed by directory scope.
