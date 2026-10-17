@php($paymentRecovery = session('team_payment_recovery'))
@if(is_array($paymentRecovery) && (int) ($paymentRecovery['event_id'] ?? 0) === (int) $event->id)
<div class="modal fade" id="teamPaymentRecoveryModal" tabindex="-1" aria-labelledby="teamPaymentRecoveryTitle" aria-describedby="teamPaymentRecoveryDescription" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="teamPaymentRecoveryTitle">Payment in progress</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="teamPaymentRecoveryDescription">Somebody is already processing a payment for this registration. You can restart now, which cancels the other pending checkout on Cape Tennis and creates a new checkout for you.</p>
        <p>If you have already paid, do not pay again. Check your registration and payment confirmation first.</p>
        <p class="small text-muted mb-0">This cannot stop a payment already open at PayFast. A late payment will be kept for reconciliation and will not complete the cancelled registration. Restarting checks the latest payment status first.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
        <form action="{{ $paymentRecovery['restart_url'] }}" method="post" id="teamPaymentRecoveryForm" class="w-100">
          @csrf
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="confirm_restart" value="1" id="teamPaymentRecoveryConfirm" required>
            <label class="form-check-label" for="teamPaymentRecoveryConfirm">I understand that the other pending Cape Tennis checkout will be cancelled.</label>
          </div>
          <button class="btn btn-primary w-100" id="teamPaymentRecoveryRetry" type="submit">Restart registration</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endif
