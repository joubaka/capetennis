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
remain unchanged. Paid, withdrawn, refunded, active or unresolved history still
blocks re-add. Use Entries for
participation or financial history; this action cannot reverse it.

A deleted row requires the latest validated exact-event deletion snapshot and a
valid `AuditIntegrity` hash. Only the original ID, event, category and verified
existing player identity are restored. Missing/profileless identity cannot be
recovered through this control. Existing archived rows are unarchived. Prior
invitations, declines and deletion evidence remain unchanged; a new unsent
`open_registration` invitation records the current active nomination. No entry,
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
