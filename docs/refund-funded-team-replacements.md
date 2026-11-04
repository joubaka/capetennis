# Refund-funded team replacements

**Move full payment to replacement** is a separate explicit super-user option
for carrying the original paid amount to the incoming player without a withdrawal
fee. Only this action selects canonical admin withdrawal `fee_mode=full`; the
wallet refund and **Use refund toward replacement player** actions retain the
current refund fee. The reason, actor, full-fee mode, original payment and new
checkout are audited. Deadline and closure overrides remain separate requirements.
The new entry is paid immediately when the original amount covers its current
server-calculated entry and regional fee. A genuine price increase still leaves
a shortfall; a lower price leaves the excess in the original payer's wallet.
This option does not repair replacements that were already saved.

The standard paid roster replacement form offers **Use refund toward replacement
player** to super users. It follows the existing withdrawal deadline and current
refund fee policy; it does not transfer or rewrite the original paid order.
After the deadline, a super user may explicitly select **Override refund deadline
for this replacement** and give the reason in the replacement form. This uses
the canonical admin withdrawal override and records the actor, reason and
deadline in its audit. The current refund fee still applies to wallet refunds and
refund-funded replacements; the explicit full-payment transfer waives it. Without the override,
late refund and refund-funded replacements remain blocked; an explicit refund
waiver remains available without returning money. The override cannot accompany
a waiver or be used before the deadline.

When site withdrawals are disabled, a paid replacement additionally requires
**Override withdrawal closure for this replacement** and a reason from the super
user, including when waiving the refund. This separate consent is bound to the
withdrawal review fingerprint and audit, and does not waive the refund deadline
or change the global setting. The normal withdrawal endpoints do not accept this
override. It is rejected if withdrawals have reopened before confirmation.

`TeamRosterService::replace()` withdraws the original entry, obtains its wallet
refund through `AdminTeamWithdrawalService`, adds the replacement, and calls
`TeamPaymentService::applyRefundToReplacement()` within one transaction. A failure
rolls back the withdrawal, refund and replacement together.

- The new order belongs to the original payer. Only the proven net refund credit
  may fund it, capped at the server-calculated current entry and regional fee.
  Existing wallet funds are not automatically spent.
- Full funding finalizes through the canonical wallet payment service. Excess
  refund remains in the original payer's wallet.
- Partial funding reserves the proceeds without a debit or paid entry. The
  original payer must complete checkout within its normal five-minute window.
  Expiry releases the reservation; the refund remains in their wallet. Partial
  funding is rejected if that payer cannot use the normal checkout.
- Active managed selection campaigns use their invitation workflow instead;
  this option is hidden and server-side guarded for those teams.
- Original paid history remains intact. The audit links the refund credit,
  original order, replacement order, applied amount and remaining balance.
  Repeated stale replacement submissions cannot issue a second refund.

Regression evidence lives in `tests/Feature/AdminTeamWithdrawalTest.php` and
`tests/Feature/TeamSelection/RegionalRosterTest.php`. Local tests do not establish
deployment or live payment behavior.

## Correct an already refunded replacement

Changing the new-replacement option does not repair an already saved refund.
Use the separate supervised `finance:correct-team-replacement` command, which
defaults to a read-only preview. Supply exact event, team, original paid order,
closed replacement order, original player and target player IDs, an existing
super-user actor and a reason. Apply requires `--apply` and the exact fingerprint
returned by the preview. A state change requires a new preview.

This correction requires the canonical withdrawal, refund-funding and roster
audit chain, equal original and current entry plus regional fees, one unique
unpaid replacement place, a safely closed unpaid replacement checkout, and the
exact original-payer wallet credit still unused and unreserved. Conflicting
payment, provider, selection, Trials or transfer history requires a different
reconciliation. Existing target-only upcoming fixtures may remain in place only
when they are unstarted and unscored, their current team tie and immutable
participant snapshots verify the target's exact team, and any mixed-side mapping
passes the canonical resolver. Original-player participation, ambiguous snapshots
and started or scored fixtures remain blocked. The locked draw, tie, fixture and
assignment state joins the preview fingerprint; no fixture, schedule or snapshot
is rewritten. All changes are locked and atomic.

The original refund credit remains immutable. A compensating debit reverses it,
and before/after audits retain the original refund and withdrawal projections.
The original paid order then provides active coverage through its replacement
beneficiary, preserving its original player, payer, receipt and payment evidence.
The expired replacement order stays closed. No new payment receipt, provider
request or confirmation email is created. Repeating the exact applied request
verifies its result without another debit or transfer.

Deploy the accompanying ordinary refund guards with this correction: the original
refund credit identity cannot be reused. Any later refund of corrected coverage
requires separate reconciliation. Do not erase the original credit or bypass
ledger idempotency to enable it. Regression evidence lives in
`tests/Feature/TeamReplacementPaymentCorrectionTest.php`; previous repair approval
does not authorize applying this command to another live incident.
