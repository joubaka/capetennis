@php
    $annualPrice = \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::priceCents());
    $monthlyPrice = \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::monthlyPriceCents());
@endphp
<div {{ $attributes->class(['d-flex flex-column gap-1']) }} data-player-plus-pricing>
  <x-player-plus-coming-soon />
  @if(\App\Services\Performance\PlayerPlusService::salesEnabled())
    @if(\App\Services\Performance\PlayerPlusService::monthlyEnabled())
      <div><strong>R{{ $monthlyPrice }}</strong> <span class="small">per player / month</span></div>
    @endif
    <div><strong>R{{ $annualPrice }}</strong> <span class="small">per player / year</span></div>
    <p class="small mb-0">PayFast plans renew automatically until cancelled. Annual wallet payments are once off. Manage renewal in My Plus orders.</p>
  @else
    <p class="small mb-0">Pricing will be published when Player Plus launches.</p>
  @endif
  @if(! \App\Services\Performance\PlayerPlusService::salesEnabled())
    <p class="small mb-0" role="status">{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? 'Player Plus is coming soon. Purchases are not open yet.' : 'New purchases are currently paused.' }}</p>
  @elseif(! \App\Services\Performance\PlayerPlusService::monthlyEnabled())
    <p class="small mb-0" role="status">Monthly purchases are currently unavailable.</p>
  @endif
</div>
