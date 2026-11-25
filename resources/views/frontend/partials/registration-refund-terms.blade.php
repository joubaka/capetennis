<div class="border rounded p-3">
  <h6>Registration, withdrawals and refunds</h6>
  <p>Registration is subject to the event's eligibility and availability rules. Check the event details and the refund deadline before confirming your entry.</p>
  @if(isset($refundTermsEvent))
    <p><strong>Refund deadline:</strong> {{ $refundTermsEvent->withdrawalCloseAt()->format('d M Y H:i') }} ({{ config('app.timezone') }}).</p>
    @if($refundTermsEvent->start_date)
      <p><strong>Self-service withdrawal closes:</strong> {{ $refundTermsEvent->start_date->copy()->startOfDay()->format('d M Y H:i') }} ({{ config('app.timezone') }}).</p>
    @endif
  @endif
  <p>To request a refund, sign in with the account that paid, open the event and find your registration or team place. Choose <strong>Withdraw</strong>, review the warning and confirm. Then choose an available refund option and submit the request. For team places, submitting the refund option may also complete the withdrawal. Check that both the withdrawal and refund request have been confirmed.</p>
  <p>Self-service withdrawal closes at the start of the event's start date in the application timezone. The refund deadline is separate: a withdrawal after the refund deadline normally retains the entry fee. Contact the organiser if self-service is closed or no refund option is available.</p>
  @if(($refundTermsFunding ?? 'individual') === 'team')
    <p>The standard team-registration refund deducts a 10% withdrawal fee from the amount paid. Review the displayed fee, amount and available refund destination before submitting.</p>
  @else
    <p>The standard withdrawal fee is 10%. A refund to the payer's Cape Tennis wallet deducts 10% of the total paid. For an individual registration paid partly by wallet and partly by PayFast, a bank refund deducts 10% from the PayFast portion and returns the wallet contribution to the payer's wallet in full. Wallet-funded payments can only be returned to the wallet. Review the displayed calculation and available destination before submitting.</p>
  @endif
  <p>Refunds belong to the original payer. A wallet credit is not a bank payment. Bank or PayFast refunds may require bank details and processing or administrator review; <strong>Pending</strong> does not mean money has been returned. Follow the refund status until it is completed. Do not submit a second request while one is pending.</p>
  <p class="mb-0"><strong>No Refund</strong> means a withdrawn entry has no refund recorded; withdrawal alone does not return money. If no refund is requested or the withdrawal is outside the refund deadline, the entry fee remains retained. These terms do not limit any rights that otherwise apply. For help, contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a> with your event and payment reference.</p>
</div>
