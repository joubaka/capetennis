# Individual pending bank refund retries

A retry executes the refund already approved on the withdrawn entry. It must
use the saved `refund_gross`, `refund_fee` and `refund_net` amounts, rather than
recalculate a handling fee or substitute the original payment's net settlement.
For example, a gross refund of R375 with a zero fee must retry R375; a saved
R37.50 fee keeps the approved net refund at R337.50.

Validate the pending state, original funding and snapshot arithmetic before
contacting PayFast. Missing, inconsistent or unsupported amounts require
reconciliation; do not invent a replacement snapshot during a retry. Preserve
the existing role, entry, event and provider-dispatch safeguards.

For supported hybrid funding, return the wallet contribution up to the approved
net amount and assign the remainder to PayFast. Neither component may exceed
its original funding, and the components must sum to the saved net in cents.
The PayFast component must be positive and the original payer's wallet must
exist when a wallet credit is required. An allocation with no PayFast component
requires separate reconciliation. The original payer remains the recipient.

A failed eligibility query leaves the approved claim pending. An uncertain
refund POST requires reconciliation before another dispatch. A successful API
response or the application's completed flag is not independent proof that the
payer's bank received the money.

The durable dispatch guard covers supported original single-item PayFast-only
orders; it does not establish equivalent protection for every hybrid or basket.
This amount correction does not redesign hybrid settlement or its existing
wallet-credit failure handling.

This procedure does not authorize refund execution, deployment, a bank transfer,
wallet conversion, or changing an existing approval.
