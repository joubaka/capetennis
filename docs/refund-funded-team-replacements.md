# Refund-funded team replacements

The standard paid roster replacement form offers **Use refund toward replacement
player** to super users. It follows the existing withdrawal deadline and current
refund fee policy; it does not transfer or rewrite the original paid order.
After the deadline, a super user may explicitly select **Override refund deadline
for this replacement** and give the reason in the replacement form. This uses
the canonical admin withdrawal override and records the actor, reason and
deadline in its audit. The current refund fee still applies. Without the override,
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
