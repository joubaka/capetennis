<details class="mb-3"><summary>Original payer’s bank details (only if PayFast requires them)</summary>
  <p>Use the original paying account. These details are sent only for this refund; they are not stored or restored after an error.</p>
  <label class="form-label" for="{{ $bankFieldPrefix }}-holder">Account holder</label><input id="{{ $bankFieldPrefix }}-holder" name="bank_details[account_holder]" maxlength="100" autocomplete="off" class="form-control mb-2">
  <label class="form-label" for="{{ $bankFieldPrefix }}-bank">Bank name</label><input id="{{ $bankFieldPrefix }}-bank" name="bank_details[bank_name]" maxlength="100" autocomplete="off" class="form-control mb-2">
  <label class="form-label" for="{{ $bankFieldPrefix }}-branch">Branch code</label><input id="{{ $bankFieldPrefix }}-branch" name="bank_details[branch_code]" inputmode="numeric" pattern="[0-9]{4,6}" autocomplete="off" class="form-control mb-2">
  <label class="form-label" for="{{ $bankFieldPrefix }}-account">Account number</label><input id="{{ $bankFieldPrefix }}-account" name="bank_details[account_number]" inputmode="numeric" pattern="[0-9]{5,12}" autocomplete="off" class="form-control mb-2">
  <label class="form-label" for="{{ $bankFieldPrefix }}-type">Account type</label><select id="{{ $bankFieldPrefix }}-type" name="bank_details[account_type]" class="form-select mb-2"><option value="">Choose account type</option><option value="current">Current / cheque</option><option value="savings">Savings</option></select>
  <label><input type="checkbox" name="bank_details[original_payer]" value="1"> These details belong to the original payer.</label>
</details>
