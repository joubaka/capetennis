@php
    $annualPrice = \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::priceCents());
    $monthlyPrice = \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::monthlyPriceCents());
@endphp
<div {{ $attributes->class(['d-flex flex-column gap-1']) }} data-player-plus-pricing>
  <div><strong>R{{ $monthlyPrice }}</strong> <span class="small">per player / month</span></div>
  <div><strong>R{{ $annualPrice }}</strong> <span class="small">per player / year</span></div>
  <p class="small mb-0">Both plans renew automatically through PayFast until cancelled. Stop future renewals from My Plus orders.</p>
  @if(! \App\Services\Performance\PlayerPlusService::salesEnabled())
    <p class="small mb-0" role="status">New purchases are currently paused.</p>
  @elseif(! \App\Services\Performance\PlayerPlusService::monthlyEnabled())
    <p class="small mb-0" role="status">Monthly purchases are currently unavailable.</p>
  @endif
</div>
