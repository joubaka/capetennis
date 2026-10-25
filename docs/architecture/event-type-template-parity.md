# Event type template parity

## Purpose

Use a shared event page structure without changing each event workflow's eligibility,
authorization, registration, financial, competition or publication rules. Introduce
shared components incrementally, beginning with Interpro. This document describes
the preservation contract; it is not evidence that every journey has been tested.

Event type database IDs, base families, workflow identities and public templates
are separate concepts. In particular, legacy Interpro (ID 13) and Interprovincial
Trials (`interprovincial-trials`) must not become interchangeable. A Trials event
can have a team base family and still requires its dedicated policy.

## Preservation matrix

| Source-supported type | Preserve during template changes | Existing regression anchors |
| --- | --- | --- |
| Individual (including legacy IDs 1/6) | Category selection, sponsored registration, payer-controlled checkout/withdrawal, published individual draws and results | `RegistrationTest`, `RegistrationWithdrawTest`, `EventPagePerformanceTest` |
| Team (including legacy IDs 3/7) | Regions, roster modes, selection/reserves, clothing, team ties, results and public schedule snapshots | `SelectedTeamReserveRegistrationTest`, `TeamEventAcceptanceWorkflowTest`, `PublicEventStandingsTest` |
| Interpro (legacy ID 13) | Region/team picker, mobile/desktop draws, venue and draw scoring, clothing orders, event documents, published fixture times | `EventPagePerformanceTest`, `InterproEventScoringControlsTest`, `InterproDrawGenerationPreservationTest` |
| Interprovincial Trials | Exact published nomination scope, sponsorship without player linking, payer ownership, protected checkout restart, linked-account decline, programme, final squad participation and private EFT proof | `InterprovincialTrialInvitationWorkflowTest`, `InterprovincialTrialPaymentLifecycleTest`, `TrialParticipationPaymentsTest`, `InterprovincialTrialBackendParityTest`, `InterprovincialTrialDrawBackendSecurityTest` |
| Masters | Qualification, ranking order, invitations/reserves, deadlines, payment ownership and results publication | `MastersQualificationReviewTest`, `MastersRegistrationBoundaryTest`, `MastersPaymentReconciliationTest`, `MastersEntriesAlignmentTest` |
| Cavaliers Trials (legacy ID 5) | Published category nominations and existing registration/draw behavior | `PublicEventPresentationTest`; extend rendered journey coverage before migrating its specialized sections |
| Parent–Child Doubles (legacy ID 9) | Existing pairing/registration behavior, information, documents and results | `PublicEventPresentationTest`; extend paired registration coverage before migrating its specialized sections |
| Camp / unknown type | Existing generic information, documents and published results; do not invent registration capabilities | `PublicEventPresentationTest`; explicit resolver matrix |
| Overberg Trials view | Existing delegation to the default public template; do not assume a persisted workflow identity from the filename | Explicit template resolution/delegation coverage |

### Verified local inventory

A read-only check of the loopback MySQL `ct` database on 2026-10-25 found:

| ID | Stored name | Code | Base family value | Effective public template |
| --- | --- | --- | --- | --- |
| 1 | Individual | individual | 1 | individual |
| 2 | camp | null | 0 | default |
| 3 | team event | team | 2 | team |
| 4 | High School Team | team | 2 | team |
| 5 | Cavaliers Trials | individual | 1 | cavaliers_trials |
| 6 | Individual Pdf | individual | 1 | individual |
| 7 | Platteland Primary Schools | team | 2 | team |
| 8 | Platteland High Schools | team | 2 | team |
| 9 | ParentChildDoubles | individual | 1 | parentChildDoubles |
| 12 | Interoro Trials | individual | 1 | default |
| 13 | Interpro Trials | interprovincial-trials | 1 | interprovincial-trials (legacy presentation accessor remains interpro) |
| 14 | Masters | masters | 1 | masters |

An existing event also references type 0 without a matching type record. Retain
the generic fallback. The repeated `individual` and `team` codes are existing
family labels, not unique workflow identifiers. Do not infer a Trials policy from
the name of type 12, repair the camp family, or remap existing events as part of
presentation work. This local snapshot is not a production inventory. No rename,
backfill or conversion is implied by extracting a presentation component.

## Shared template methodology

1. Resolve event family, workflow and presentation in one place, retaining legacy
   precedence and explicit compatibility mappings. Never infer permissions or
   payment routing from a template filename.
2. Keep the existing public page shell and dedicated workflow sections. Share
   information, announcements, document presentation, headers and navigation where
   their contracts match. Preserve section anchors, responsive ordering, form
   fields, route names and JavaScript hooks.
3. Match both document-route requirements (`auth` and `super-user|admin`) and the
   server-side `event.manage` ability when displaying document mutations.
   `User::is_admin()` returns a collection and is not a boolean check. A roleless
   event administrator/convenor must not receive controls that the route rejects.
   Upload/delete endpoints remain authoritative and event-scoped.
4. Retain canonical registration, payment, refund and withdrawal services. Do not
   move state mutations into templates or a presentation resolver.
5. Migrate one module at a time and verify both its enabled behavior and its hidden,
   forbidden, empty, unpublished and cross-event states.

## Verification gates

- Characterize all legacy template mappings, code-based special workflows,
  family precedence, missing type records and nonlegacy IDs before replacing
  identification branches.
- Check Interpro fixture preparation with published and draft draws, schedule
  snapshots, private venue information and scoring permissions.
- Render document controls as guest, ordinary user, assigned event manager and
  manager of another event. Preserve download URLs, upload formats and deletion
  hooks; verify rejected mutations leave records/files intact.
- Run focused tests serially in isolated test storage/database paths. For shared
  registration/payment changes, run the full feature suite after focused tests.
- Compile Blade, verify route registration, check PHP syntax and `git diff --check`.
- Keep browser evidence separate from HTTP/render tests. Check phone, tablet and
  desktop layout before claiming responsive parity.
- Preserve unrelated worktree changes. No commit, publication, mail dispatch,
  production migration or deployment is part of a local template migration.

## Subsequent bounded work

The Interpro-first implementation centralizes public view/venue-fixture resolution
in `App\Support\EventTypePresentation` while keeping registration and authorization
in their existing services. Individual, Team, Interpro, Masters, Cavaliers Trials,
Parent–Child Doubles and default views now share `event-documents`. Masters/default
stay read-only; Individual retains its upload modal. Interpro uses the existing
page-level delete handler instead of registering a second handler. Trials retains
its dedicated nominations/programme template and legacy fixture compatibility.

Continue other matching presentation modules with per-type rendered coverage.
Review established-event type conversion
rules separately: changing the type must not silently orphan nominations,
registrations, orders, rosters or draws. A shared layout does not by itself make
those workflows interchangeable.
