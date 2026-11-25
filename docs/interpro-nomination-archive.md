# Archive a withdrawn Interpro nomination

Archiving hides one current Interpro nominee from the public list and current
registration, invitation readiness and reminder audiences. It retains the
nomination ID and all original registration, invitation, payment, withdrawal
and account-link evidence. Other events are outside the operation's query scope.

The public Interpro nomination list also automatically omits nominees whose latest
supported invitation for the exact event/category/nomination/player is withdrawn,
declined or administrator-declined. This is a read-only display filter, applied
before the public list limit on each page request. Unanswered and registered
nominees remain visible; a newer valid invitation supersedes an older decline.
Backend nomination and withdrawal history remains available. No separate archive
action is needed merely to hide a withdrawn or declined public row.

Only a super-user may preview or confirm this operation. The service locks the
event, category and exact nomination, checks matching invitation/order tuples,
requires all of that player's event participation to be withdrawn and rejects
active/unresolved checkouts, current queued invitations and pending or verified
EFT proof. The operation changes only archive metadata and writes an audit.
No payment is transferred, cancelled, refunded or marked unpaid, and no mail
is sent. Archive metadata has no global model scope: historical payment and
withdrawal readers continue to resolve the original nomination.

```text
php artisan interpro:archive-nomination EVENT CATEGORY NOMINATION --actor=SUPER_USER --reason="Remove withdrawn nomination from current list"
```

The default invocation only previews. Review the exact event/category/player
and retained entry/order/invitation IDs. Apply the unchanged invocation with
`--confirmation=TOKEN` within fifteen minutes. Tokens are bound to actor,
action, reason and current nomination/participation state. An unchanged repeated
apply is idempotent. Preview output omits payment and bank details.

To restore current nomination availability, explicitly preview and confirm the
same command with `--restore`. Restoration clears only archive metadata; it never
restores withdrawn participation or reopens a checkout. Withdrawn and declined
nominees remain excluded from the public list after archive restoration until
their current invitation status changes through the normal workflow.
An archived nominee must first
be restored before any new registration or withdrawal cancellation.

The backend nomination register includes current and archived nominees together.
Within each category, sort before pagination by unregistered, registered, declined,
then withdrawn, followed by archived and removed history; retain exact status labels
and stable nomination-ID order within each group. Cancelled checkouts belong to
the unregistered group.
Its server-side status links show whole-event totals across all pages, with
Withdrawn and Cancelled kept separate, and Declined / removed combined in one
filter while each row retains its factual Declined or Removed label. An archived withdrawal
counts once as Withdrawn and carries an Archived nomination badge with its date
and reason. Historical rows retain their history presentation. Profile-backed
removed, declined and archived rows offer a separate reviewed **Re-add nomination**
action for super-users and assigned event administrators; it is not withdrawal
restoration or payment reconciliation.
Readiness and operational audiences still exclude archived nominees.

Removed nominations are reconstructed only from the latest successful exact-event
nomination deletion audit snapshot, validated against its original identity and
an event-owned category. A removed record is not evidence of a decline or a
withdrawal. Missing reasons remain explicitly unrecorded; missing identity has an
honest nomination-ID fallback. The register cannot recover deletions for which no
valid audit snapshot survives. The audit projection is bounded and paginated,
with counts calculated in SQL independently of the selected page or filter.
After adding or removing a nomination, the register reloads its authoritative
counts rather than recalculating them from the visible page.

Generic and dedicated hard-delete
routes cannot remove archived Interpro nomination history. A retained ID also
prevents silently renominating the same player through duplicate insertion.

## Re-adding an available nominee

The nomination register's Re-add nomination control requires a reason, availability
confirmation and the unchanged displayed revision. `NominationReactivationService`
locks the event, category, nomination and invitations, validates the exact tuple,
and rejects locked categories, conflicting nominees and active delivery. It permits
only exact single-item fully cancelled unpaid checkout history: never handed to
PayFast, zero wallet reservation/debit, no settlement/manual receipt/recovery or
pending/verified EFT evidence, and a deleted unpaid pending-checkout entry. Original
and retained handoff evidence both block re-add, including timestamps cleared by
canonical expiry. Historical orders, payers, amounts, entries and ledger records
remain unchanged. Other paid, withdrawn, refunded, active or unresolved history still
blocks re-add. Use Entries for
participation or financial history; this action cannot reverse it.

A deleted row requires the latest validated exact-event deletion snapshot and a
valid `AuditIntegrity` hash. Only the original ID, event, category and verified
existing player identity are restored. Missing/profileless identity cannot be
recovered through this control. Existing archived rows are unarchived. Prior
invitations, declines and deletion evidence remain unchanged; a new unsent
`open_registration` invitation records an unpaid active nomination; the private-entry
exception below uses `paid_confirmed` without a payment date. No entry,
checkout, payment, account link or mail dispatch is created. Repeated/stale
submissions fail without duplicate rows. The activity records the actor and reason.

Existing category publication settings apply: a public category shows the returned
nominee immediately, while a private category stays private. The confirmation
states this explicitly. This workflow changes neither publication flags nor the
registration window. Checkout and entry history is included in the displayed
revision and rechecked under locks; a changed attempt requires a fresh review.
Regression coverage is in `InterproNominationHistoryTest`.

Migration:
`database/migrations/2026_11_19_130000_add_archive_to_event_nominations.php`.
Deployment allowlisting is separate from authority to run that migration or a
live archive. Tests: `tests/Feature/InterproNominationArchiveTest.php`.

Local regression coverage includes unchanged other-event paid records, automatic
public omission after withdrawal/decline, exact invitation tuple and latest-status
selection, profileless nominees, archive restoration, old-link registration/decline
rejection, reminder exclusion and retained withdrawal state. MySQL concurrency
and live execution remain separate checks.
Repair catalogue reference: RK-064.

Register regression coverage: `tests/Feature/InterproNominationHistoryTest.php`
(global counts, archived withdrawals, separate terminal statuses, removed-row
deduplication, event/snapshot validation, historical rows, pagination and drafts).


An exact existing active private-admin entry can instead be reconnected to the
restored nomination. It must be the only event entry and the only category entry
for its registration, with one exact player, current private collection note
`paid`, canonical offline administrative creation audit, and one matching zero-money
`Admin Entry` receipt tied to the original event/category/player/registration and
creator. Shared/mismatched identity, checkout references, reconciled provider or
wallet evidence, withdrawal/refund state and conflicting invitations are rejected.
The existing entry, actor, private note, Cape Tennis fee snapshot and zero-money
receipt remain unchanged. A new `paid_confirmed` marker points to the existing
registration; `paid_at` remains null because no payment date has been established.
The register shows Paid privately and Private collection; not reconciled. This
restores nomination visibility, creates no participation or financial record, and
never converts an operational private note into a reconciled receipt. Both
confirmation and stale-state review cover this existing-entry path.


On legacy schemas without `transactions_pf.registration_id`, the review records an
explicit `legacy_tuple` receipt identity mode. It examines the entire numeric
event/player receipt set and requires exactly one zero-money administrative
receipt, one event entry, one single-player registration and one canonical offline
creation audit. Event, category, player and original actor IDs must agree in every
source. Receipt creation must follow entry creation and precede its audit within
120 seconds, allowing two seconds of timestamp precision tolerance; this is extra
source consistency, never identity matching by name or date. Missing/invalid
timestamps, duplicate receipts/audits or conflicting records fail closed. Modern
schemas always require the explicit registration link and never fall back from a
mismatched ID. Schema mode and all candidate receipts are included in the displayed
revision and locked apply review. No migration or financial mutation is required.

## Unanswered nominees on the Entries page

The Entries page has a separate follow-up workflow for current published
nominees with no response. Super-users and assigned event administrators can
email one nominee through the existing recipient review and approval flow.
Removal and category moves are restricted to Interprovincial Trials; other
selection workflows keep their own nomination administration.

`NomineeFollowUpService` rechecks the exact nomination and its revision under
event/category locks. It rejects recorded registration or checkout history,
responses, active invitation delivery and locked categories. Moves require a
different published, unlocked category in the same event and reject duplicate
nominations. These actions do not register players or send invitations.

With invitation history, removal archives the source nomination; moving also
creates a destination nomination, retaining the original invitation identity.
Without invitation history, removal deletes the history-free nomination and a
move updates its category. Both actions record an audit. This workflow does not
replace the reviewed withdrawn-participation command described above.

Tests: `tests/Feature/EventEntryNomineeFollowUpActionsTest.php`.

## Correct a mistaken private-admin entry

Restoring an existing private-admin entry means the player is already registered.
When the operator confirms that this entry was a mistake and no private funds
were collected, use the separate reviewed correction rather than changing its
payment note or recording a player withdrawal.

`interpro:correct-private-admin-entry EVENT CATEGORY PLAYER NOMINATION ENTRY RECEIPT`
requires an existing super-user actor, an explicit reason and
`--confirm-no-funds-collected`. The first run previews without writes; applying
requires `--apply --confirmation=TOKEN` from that exact actor-bound, fifteen-minute
preview. Review exact numeric IDs and current evidence before any live apply.

The canonical EntryService correction requires a current published nomination,
open registration, sole exact single-player private-admin entry and zero-money
receipt with canonical creation provenance. It rejects settlement, checkout,
refund, competition, adjustment, selection and payout dependencies. Original
entry payment/private-note fields and receipt amounts/fee snapshots stay intact.
The erroneous entry is soft-deleted with `cancelled_admin_error`; the ledger
archives its exact zero-money receipt to remove the erroneous fee liability.
It creates no withdrawal, refund, wallet transaction, ownership link or email.

A fresh `open_registration` lifecycle marker restores normal sponsored checkout.
Only the exact audited retired administrative entitlement is excluded from old
paid-marker selection; genuine paid and pending checkout protections remain.
Old signed links stay superseded, and cancelling/restarting a new checkout must
not reactivate the retired admin entitlement. Registration and payment still
have to be completed by the payer.

No migration is needed for this correction. Deployment and live application are
separate authorized actions. The preview is not proof of a completed correction.