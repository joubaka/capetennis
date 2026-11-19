# Mistaken individual Interpro registration

`entries:correct-individual-beneficiary` corrects current participation in a
single-player, fully settled PayFast-only Interpro registration. It preserves
the original payer, order, item, handoff and receipt, including the original
mistaken player identity and provider fee/net. It changes the existing
registration's sole current participant and transfers the current invitation
link to the exact published target nominee. There remains one paid entry and
one refundable payment, never a second receipt or an administrative payment.

The immutable `individual_beneficiary_corrections` record stores the original
payment tuple, before invitation snapshots, actor, reason and review fingerprint.
Historical payment details retain the original player; this correction record
explains the current participant. A correction does not establish that the
original player paid personally. The original payer remains responsible for
checkout access and receives any future policy refund after withdrawal.

Preview, using actual reviewed IDs and a specific reason:

```text
php artisan entries:correct-individual-beneficiary ORDER TARGET_PLAYER --actor=SUPER_USER --reason="Correct mistakenly selected nominee"
```

Review the returned event, category, payer, original and target player, exact
amount, provider reference, invitations and any preserved cancelled attempts.
Apply the same command with `--confirmation=TOKEN` within fifteen minutes.
The token is actor-bound and stale state rejects the operation. Repeating the
same approved apply returns the recorded correction without changing money or
participation again. The command sends no mail and creates no ownership links.

The scope excludes wallet/hybrid/manual settlement, multiple items, changed
fees, withdrawn/refunded entries, any draw or results, category adjustments,
another unresolved or paid checkout and any ambiguous invitation or receipt.
Only safely cancelled, unpaid, never-handed-off competing attempts with deleted
unpaid entries may be preserved unchanged; they are included in review.

Signed PayFast replays retain original identity, reference and amount validation.
The separate exact correction mapping permits acknowledging that original
settlement without restoring the mistaken participant or reactivating a later
withdrawal/refund. Invalid mappings fail closed. Category changes for a corrected
entry require separate review before a difference checkout can be started.

Required migration:
`database/migrations/2026_11_19_120000_create_individual_beneficiary_corrections.php`.
Allowlisting does not authorize deployment, migration execution or a live apply.
Use `IndividualBeneficiaryCorrectionTest` for the focused regression evidence;
local test results are separate from production application and verification.

Focused local evidence: the isolated in-memory SQLite suite passed eleven tests and
67 assertions, including preserved cancelled attempts, payment-proof conflicts, signed payment replay,
one payer-owned refund after withdrawal, stale review rejection and no extra
contact notifications. MySQL concurrency and live operation require separate
verification. Repair catalogue reference: RK-063.
