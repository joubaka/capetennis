@if((float) $freeTrialTermsEvent->entryFee <= 0)
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="terms_accepted" value="1" id="{{ $freeTrialTermsId }}" required>
    <label class="form-check-label small" for="{{ $freeTrialTermsId }}">I accept the <a href="#free-trial-registration-terms">registration terms and refund policy</a>.</label>
  </div>
@endif
