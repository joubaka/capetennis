# Camp registration and invitations

Camp is a reusable event type, independent of Cavaliers trials and Interpro
selection. Source teams provide evidence for camp nomination review; their entry
rules, payments and decisions never become camp decisions.

## Registration methods

Each configured camp chooses one method:

| Method | Admission |
| --- | --- |
| Open registration | Any otherwise eligible player may register. |
| Invitation only | An approved nominee in the exact camp division may register. |
| Open registration with invitations | Ordinary eligible entry remains open; selected nominees can also receive invitations. |

Regional sources, manual nominations and coach submissions are optional. None is
required for ordinary open entry. Admission remains payer-sponsored: paying for a
player does not create a family/account link or grant access to another payer's
checkout. An invitation does not reserve a place.

Existing camps without the new configuration retain their registration rules.
The Camp classifier recognises the explicit Camp family and an exact legacy Camp
name/code only when its family is unset/zero; conflicting team/individual types
are not renamed or remapped. Camp entry uses the canonical individual checkout.

## Organiser workflow

Keep the main journey to **Camp setup → Choose and approve players → Review
invitations**. Setup is completed once; coach tools, manual identity resolution,
advanced options and delivery history use progressive disclosure rather than
competing with the next action. Ordinary open registration can skip invitations.

Open **Practice camps**, or the camp-management link for an existing Camp event.
The regional roster planner and camp-management workspace are separate steps of
the same event. Access requires a Super Admin or an assigned event administrator;
private source data additionally requires management access to its source event.

1. Configure the camp name, dates, venue, organiser/contact, registration method,
   default and division fees, camp/division capacity, age/gender divisions and
   entry, withdrawal and coach deadlines. Saving does not publish the event or
   change its registration switch. Preserve existing divisions and paid history.
2. Optionally choose a source event, region and teams in the roster planner.
   Review current rosters and separately labelled historical selections/declines,
   then save candidates. `EventTeamScope` resolves event ownership; a shared
   region alone is insufficient. Draft revisions and source fingerprints reject
   stale saves. Original ranking evidence must be reviewed separately when
   selection history is incomplete; manual sequence numbers are not merit ranks.
3. Resolve the exact player identity and approve a nominee for a camp division.
   Manual nominations are available without using regional teams. Existing
   profile IDs combine across sources; imported roster slots retain stable
   team/slot identities until the organiser explicitly matches them. Matching
   never creates account/player ownership. Names alone do not establish identity.
   Known roster players can be approved together after one explicit group review;
   uniquely matching age/gender divisions are suggested. The server re-derives
   player identities from the locked draft, rechecks its revision and permissions,
   and validates every division through the canonical approval service. The batch
   is atomic and sends nothing. Select-all takes at most 200 ready players and
   skips unresolved or already-decided camp nominations; historical team declines
   remain review candidates. Unresolved imported identities stay individual.
4. Select approved nominees and review the exact recipients, subject and rendered
   messages before explicitly queuing invitations. Approval alone sends nothing.
5. Track approved/declined decisions, pending checkout, paid/withdrawn participation
   and separate delivery history. Participation is derived from canonical entry
   and order records; a later withdrawn attempt cannot hide another active entry.

Camp pages give escaped, accessible toast feedback after actions, with persistent
field/action errors and a copyable coach link. Keep validation feedback concise
rather than producing a toast for every invalid field. Native submission shows a
busy state and prevents double clicks while preserving approve/reject button
values; restoring a page also restores the original button states. Stale review
errors require a new review, never an automatic retry or prechecked approval.
Changing a roster selection or division clears its group-review confirmation.
Inline errors belong to the submitted form, not every field with the same name.
`CampActionFeedback` redirects routine browser 409/422 failures to the relevant
camp page; API statuses and permission/not-found denials remain unchanged.

A previous team decline is historical evidence only. Camp declines require an
explicit confirmed action by a signed-in invitation-link holder and record the
actual actor. An active paid entry, unresolved checkout or pending category move
must be resolved through its canonical workflow before declining/revoking.

## Coach nominations

An organiser creates a named, expiring private link and shares it with that coach.
Links can be revoked and cannot outlive the camp's nomination deadline. The form
collects the player's name, birth date and gender, guardian name/contact and a
recommendation; it exposes no player directory or other submissions. Repeated
submission requests are idempotent and each link has a bounded submission count.

Coach nominations remain pending until organiser review. The organiser rejects,
resolves an exact existing profile, or explicitly creates a reviewed new player
profile. Existing-profile matching checks normalized name, birth date and gender;
notification email is not identity evidence. No account/player link is created.
Only approval creates an eligible camp nominee, and sending remains a later step.

Camp division ages mean the age reached in the camp's calendar year. Coach age
bounds refer to the preceding calendar year, so a January camp can accept the
previous season's U8/U9 nominations for its U9/U10 groups without changing the
rule when January arrives. These limits are configurable for other camps.

## Invitations and payments

Actionable invitations use the existing primary-contact policy. Resolve optional
notification contacts through `TeamSelectionContactService::emails()`; normalize
and deduplicate addresses. Additional contacts receive notification-only copies
without invitation/decline/checkout links. They never establish identity or payer
receipt routing.

Mail review is actor/event bound and expires. Queueing rechecks recipients,
nominee state, eligibility, camp details and the reviewed sender envelope. Jobs
use managed mail, immutable signed snapshots, after-commit dispatch and the
`outbound-mail` limiter. Changed contacts/settings, closed entry or a recorded
participation decision suppress stale queued mail. Queued is not delivered.
A fresh review can retry safely skipped/known-failed messages; queued, sending,
sent and acceptance-unknown attempts are protected against duplicate delivery.

`CampAdmission` integrates with canonical eligibility, entry creation and
capacity checks. Camp-wide limits count distinct beneficiaries in active paid
entries, unresolved checkout holds and pending category moves; division limits
remain canonical. Relevant locking/current reads protect concurrent admission.
For camp admin duplicate checks, use the tested locking JOIN across entry and
player-association rows: locking only an outer relationship query did not see a
newly committed association under a previously established MySQL repeatable-read
snapshot. The disposable two-process regression verifies actual lock waits for
this case, nominee revocation and coach-link revocation.
Cancel/expire reservations through payment services, not elapsed time alone.
Verified finalization and replay keep their original settlement evidence and are
not re-gated by later camp settings or approval changes.

## Cavaliers 2027 and local setup

The existing Cavaliers Practice Camp 2027 draft starts with **7–9 January 2027,
provisional**. Venue, fee, capacity, deadlines and eligible divisions need review.
It remains unpublished with registration closed. Its nullable plan fee means
unknown; the backing legacy event's numeric zero is not a free-entry offer.
The local-only idempotent `camp:prepare-2027 --actor=<local-super-user-id>` can
prepare/reopen that private draft and correct its earlier ordinary-type fallback
to the recognised existing Camp type without opening or publishing it.

Required schema paths are explicitly allowlisted in `deploy.config`:

- `database/migrations/2026_11_20_120000_create_practice_camp_plans.php`
- `database/migrations/2026_11_20_160000_create_camp_workflows.php`

Apply only the exact missing required path(s) in the authorised environment.
Allowlisting is not permission to migrate production. Local tests and fixture
previews do not send real mail, take payments, publish or deploy.

Owning code: `PracticeCampPlanner`, `CampWorkflow`, `CampAdmission`,
`CampInvitations`, camp controllers/models and `SendCampInvitation`.
Regression coverage: `PracticeCampPlannerTest`, `CampWorkflowTest`,
`CampCheckoutTest`, `DeploymentConfigTest` and
`tests/Support/camp-current-read-mysql.php`. Browser checks render fictional
management, coach, invitation and mail-review pages with local assets.
