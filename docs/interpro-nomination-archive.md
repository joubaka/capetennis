# Archive a withdrawn Interpro nomination

Archiving hides one current Interpro nominee from the public list and current
registration, invitation readiness and reminder audiences. It retains the
nomination ID and all original registration, invitation, payment, withdrawal
and account-link evidence. Other events are outside the operation's query scope.

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

To restore visibility, explicitly preview and confirm the same command with
`--restore`. Restoration changes only nomination visibility; it never restores
withdrawn participation or reopens a checkout. An archived nominee must first
be restored before any new registration or withdrawal cancellation.

Backend current nomination counts exclude archived nominees. A bounded archived
section identifies retained archived records and the reason; operational use
and public registration remain disabled. Generic and dedicated hard-delete
routes cannot remove archived Interpro nomination history. A retained ID also
prevents silently renominating the same player through duplicate insertion.

Migration:
`database/migrations/2026_11_19_130000_add_archive_to_event_nominations.php`.
Deployment allowlisting is separate from authority to run that migration or a
live archive. Tests: `tests/Feature/InterproNominationArchiveTest.php`.

Local verification: isolated in-memory SQLite passed eight tests and 54
assertions, including unchanged other-event paid records, public visibility,
old-link registration/decline rejection, reminder exclusion and retained
withdrawal state. MySQL concurrency and live execution remain separate checks.
Repair catalogue reference: RK-064.
