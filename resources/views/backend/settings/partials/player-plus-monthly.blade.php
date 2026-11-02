<div class="mt-3 pt-3 border-top" data-plus-monthly-settings>
  @if($showAnnualContext ?? true)<p class="small text-muted">New annual purchases renew automatically at the agreed annual price until cancelled. Existing once-off annual purchases keep their original conditions.</p>@endif
  <h3 class="h6">Monthly automatic renewal</h3>
  <label class="form-check form-switch">
    <input class="form-check-input" type="checkbox" role="switch" id="sa-plus-monthly-enabled" @checked(\App\Services\Performance\PlayerPlusService::monthlyEnabled())>
    <span class="form-check-label">Offer monthly automatic renewal</span>
  </label>
  <label class="form-label mt-2" for="sa-plus-monthly-price">Monthly price per player (R)</label>
  <div class="d-flex flex-wrap gap-2">
    <input class="form-control" style="max-width:12rem;min-height:44px" id="sa-plus-monthly-price" type="number" min="5" max="99999.99" step="0.01" value="{{ \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::monthlyPriceCents()) }}" required>
    <button class="btn btn-outline-primary" type="button" id="sa-plus-monthly-save" style="min-height:44px">Save monthly settings</button>
  </div>
  <p class="small text-muted mt-2 mb-0">Monthly payments recur through PayFast until the payer cancels. Price changes apply to new subscriptions only. Pausing new purchases does not stop existing automatic renewals. Confirm recurring billing is enabled on the PayFast merchant account before offering this plan.</p>
  <p id="sa-plus-monthly-status" class="small mt-2" role="status"></p>
</div>
<script>
document.getElementById('sa-plus-monthly-save').addEventListener('click', async function () {
  const button = this;
  const input = document.getElementById('sa-plus-monthly-price');
  const status = document.getElementById('sa-plus-monthly-status');
  if (!input.reportValidity()) return;
  button.disabled = true;
  try {
    const response = await fetch(@json(route('settings.player-plus-monthly')), {
      method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify({player_plus_monthly_price: input.value, player_plus_monthly_enabled: document.getElementById('sa-plus-monthly-enabled').checked})
    });
    const result = await response.json();
    status.textContent = response.ok ? result.message : (result.errors?.player_plus_monthly_price?.[0] || result.message || 'Monthly settings could not be saved.');
  } catch (_) { status.textContent = 'Monthly settings could not be saved. Please retry.'; }
  finally { button.disabled = false; }
});
</script>
