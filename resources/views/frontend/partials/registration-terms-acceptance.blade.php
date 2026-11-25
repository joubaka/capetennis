<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" name="terms_accepted" value="1" id="{{ $termsCheckboxId }}" required>
  <label class="form-check-label" for="{{ $termsCheckboxId }}">I have read and accept the registration Terms &amp; Conditions, including the withdrawal and refund terms above.</label>
</div>
@error('terms_accepted')<div class="text-danger mb-2" role="alert">{{ $message }}</div>@enderror
