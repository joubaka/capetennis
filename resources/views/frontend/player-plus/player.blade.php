@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus registration')
@section('content')
<div class="container-xxl container-p-y pp-player pp-journey" data-plus-player-page data-plus-review-plan="{{ $errors->any() ? old('plan', 'annual_recurring') : '' }}">
  @include('frontend.player-plus.styles')
  <style>
    .pp-player .pp-layout{display:block!important;max-width:760px;margin:auto}.pp-player .pp-plan-choices{padding:1.5rem;border:1px solid #dce5ef;border-radius:16px;background:#fff}.pp-player .pp-plan-choices h2{color:#172e45;font-size:1.5rem}.pp-player .pp-plan-choice{display:flex;flex-direction:column;gap:.5rem;text-align:left;width:100%;padding:1.25rem;margin:1rem 0;background:#eef4fa;border:1px solid #9ab4cf;border-radius:12px;color:#172e45;min-height:44px}.pp-player .pp-plan-choice:hover{border-color:#004177;background:#e5eff8}.pp-player .pp-plan-choice>span:last-child{align-self:flex-start;background:#ff9f43;border-radius:7px;padding:.5rem 1rem;color:#172e45;font-weight:700}.pp-player [hidden]{display:none!important}.pp-player .pp-purchase [data-plus-change-plan]{width:auto;margin:0 0 1rem}
  </style>
  <style>
    .pp-player{max-width:1120px;color:#243e50;font-size:1rem;line-height:1.65}.pp-player .small,.pp-player small{font-size:.875rem}.pp-player .pp-nav{display:flex;flex-wrap:wrap;gap:.25rem 1.5rem;justify-content:space-between;margin-bottom:1rem}.pp-player .pp-nav a{min-height:44px;display:inline-flex;align-items:center}
    .pp-player .pp-hero{background:linear-gradient(115deg,#172e45,#004177);color:#fff;border-radius:18px;padding:clamp(1.5rem,4vw,2.5rem);margin-bottom:1.5rem}.pp-player .pp-eyebrow{font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;font-weight:700;margin:0 0 .75rem}.pp-player .pp-hero h1{color:#fff;font-size:clamp(1.8rem,4vw,2.5rem);line-height:1.2;letter-spacing:-.025em;margin:0 0 .75rem}.pp-player .pp-intro{color:#dee9f5;max-width:65ch;margin:0}.pp-player .pp-profile{color:#dee9f5;margin:1rem 0 0;font-size:.875rem}
    .pp-player .pp-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:1.5rem;align-items:start}.pp-player .pp-layout>*{min-width:0}.pp-player .pp-benefits{margin:0}.pp-player .pp-status{border:1px solid #dce5ef;border-left:4px solid #004177;background:#eef4fa;border-radius:12px;padding:1.1rem 1.25rem;line-height:1.65;margin-bottom:1.25rem}.pp-player .pp-status p{margin:.35rem 0 0;color:#38516e}.pp-player .pp-status[data-plus-readiness="available"]{border-left-color:#258164;background:#eef7f2}.pp-player .pp-status h2{font-size:1rem;color:#172e45;margin:0}
    .pp-player .pp-purchase{border:1px solid #c8d8e4;border-radius:16px;padding:1.5rem;background:#fff;margin-bottom:1rem}.pp-player .pp-purchase h2{font-size:1.25rem;color:#172e45;margin:0}.pp-player .pp-plan-price{font-size:2rem;font-weight:700;color:#172e45;margin:.5rem 0 0;line-height:1.3}.pp-player .pp-plan-price span{font-size:1rem;font-weight:400}.pp-player .pp-plan-note{color:#526c78;margin:.65rem 0 1.25rem}.pp-player .pp-consents{border:0;padding:0;margin:1.25rem 0 0;min-width:0}.pp-player .pp-consents legend{font-size:1rem;font-weight:600;color:#172e45;margin-bottom:.75rem}.pp-player .pp-consent{display:flex;gap:.75rem;align-items:flex-start;padding:.65rem 0;margin:0;cursor:pointer;font-size:.9375rem;line-height:1.6}.pp-player .pp-consent input{flex:0 0 20px;width:20px;height:20px;margin-top:.25rem;accent-color:#004177}.pp-player .pp-purchase .btn{width:100%;margin-top:1.25rem;min-height:48px}.pp-player .pp-sponsor-note{background:#fff6eb;border:1px solid #f2d6b8;border-radius:10px;padding:1rem;margin-bottom:1rem;color:#62401d}.pp-player .pp-plan-availability{font-size:.9375rem;color:#526c78;margin:0 0 1rem}.pp-player .pp-actions{margin-top:1.25rem;padding:1.5rem;border:1px solid #dce5ef;border-radius:16px;background:#fff}.pp-player .pp-actions h2{font-size:1.2rem;color:#172e45}.pp-player .pp-action-links{display:flex;gap:.75rem;flex-wrap:wrap}.pp-player .pp-footer{border-top:1px solid #dce5ef;margin-top:1.5rem;padding-top:1rem}.pp-player .pp-error-summary{margin-bottom:1.5rem}.pp-player .pp-field-error{color:#a12222;font-size:.875rem;margin:.25rem 0}
    @media(min-width:992px){.pp-player .pp-details{grid-column:1;grid-row:1}.pp-player .pp-checkout{grid-column:2;grid-row:1}}@media(max-width:991.98px){.pp-player .pp-layout{grid-template-columns:1fr}.pp-player .pp-benefits>ul{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:575.98px){.pp-player .pp-purchase,.pp-player .pp-actions{padding:1.2rem}.pp-player .pp-benefits>ul{grid-template-columns:1fr}.pp-player .pp-action-links{flex-direction:column}.pp-player .pp-action-links .btn{width:100%}}
  </style>
  <nav class="pp-nav" aria-label="Player Plus navigation"><a href="{{ route('player-plus.index') }}">← Choose another player</a><a href="{{ route('player-plus.orders.index') }}">My Plus orders →</a></nav>
  @php
    $membershipStatusOnly = ($purchaseBlocked ?? false) || ($adminAccess ?? null)?->mode === 'suspended'
      || (($adminAccess ?? null)?->mode === 'grant' && $adminAccess->starts_at->lte(now()) && $adminAccess->ends_at->isFuture())
      || ($membership && $membership->starts_at->lte(now()) && $membership->ends_at->isFuture());
  @endphp
  @unless($membershipStatusOnly)<x-player-plus-stepper :current="3" />@endunless
  <header class="pp-hero">
    <p class="pp-eyebrow">Cape Tennis · Optional Player Plus</p><h1>Player Plus for {{ $player->full_name }}</h1><p class="pp-intro">@if($canViewRating)Your player views are available below. Check membership status and follow their progress.@elseif($membershipStatusOnly)Check this player's membership status below.@elseif(!$enabled)New purchases are currently paused. Check this player’s membership and rating availability below.@else Choose a plan for this player, then review the price and renewal terms before paying.@endif</p><p class="pp-profile">Profile #{{ $player->id }} @if($player->dateOfBirth) · Born {{ substr($player->dateOfBirth, 0, 4) }} @endif</p>
  </header>
  @if($errors->any())
    <section class="alert alert-danger pp-error-summary" role="alert" aria-labelledby="pp-error-heading"><h2 class="h6" id="pp-error-heading">Please review your checkout details</h2><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
  @endif
  @if($readiness)
    <section class="pp-status text-body" aria-label="Rating availability" data-plus-readiness="{{ $readiness['state'] }}"><h2>{{ $readiness['label'] }}</h2><p>{{ $readiness['description'] }}</p></section>
  @endif
  <div class="pp-layout">
    <section class="pp-checkout" aria-label="Player Plus membership and checkout">
      @unless($linked)<p class="pp-sponsor-note"><strong>You are sponsoring {{ $player->name }}.</strong> Only the player and accounts already linked to them can use their private Player Plus benefits. Payment does not link the player to your account.</p>@endunless
      @if($purchaseBlocked ?? false)
        <p class="alert alert-info">A new purchase for this player is not currently available. The linked account holder or existing payer can review their own access and orders, or contact Cape Tennis support.</p>
      @elseif(($adminAccess ?? null)?->mode === 'suspended')
        <p class="alert alert-warning">Player Plus access is deactivated. Contact Cape Tennis support. Deactivation does not stop automatic renewal; the payer can manage billing in My Plus orders.</p>
      @elseif(($adminAccess ?? null)?->mode === 'grant' && $adminAccess->starts_at->lte(now()) && $adminAccess->ends_at->isFuture())
        <p class="alert alert-success">This player has admin-granted access until {{ $adminAccess->ends_at->format('j M Y H:i') }}. This grant does not record a payment or change existing subscription billing.</p>
      @elseif($membership && $membership->starts_at->lte(now()) && $membership->ends_at->isFuture())
        <p class="alert alert-success">This player is covered until {{ $membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }}. @if($membership->order?->isRecurring())The payer can manage automatic renewal in My Plus orders.@else This existing once-off term has no automatic renewal or charge.@endif</p>
      @else
        @if($membership && $linked)<p class="alert alert-secondary">Membership expired on {{ $membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }}. @if($membership->order?->isRecurring())The payer should check payment and renewal status in My Plus orders.@else No renewal payment has been taken automatically.@endif</p>@endif
        @if($enabled && \App\Services\Performance\PlayerPlusService::monthlyReady())
          <section class="pp-plan-choices" data-plus-plan-choices hidden aria-labelledby="pp-plan-heading">
            <h2 id="pp-plan-heading" tabindex="-1">Choose a plan</h2>
            <p>For {{ $player->full_name }} · One named player</p>
            <button type="button" class="pp-plan-choice" data-plus-choose="annual_recurring"><strong>Annual · R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} / year</strong><span>Renews automatically each year until cancelled.</span><span>Continue <span aria-hidden="true">→</span></span></button>
            <button type="button" class="pp-plan-choice" data-plus-choose="annual"><strong>Wallet · R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} for 12 months</strong><span>Once-off payment. No automatic renewal. Available wallet funds: R{{ \App\Services\Performance\PlayerPlusService::amount($walletAvailableCents ?? 0) }}.</span><span>Review wallet payment <span aria-hidden="true">→</span></span></button>
            @if($monthlyEnabled)<button type="button" class="pp-plan-choice" data-plus-choose="monthly"><strong>Monthly · R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} / month</strong><span>Renews automatically each month until cancelled.</span><span>Continue <span aria-hidden="true">→</span></span></button>@endif
            <p class="small">Eligible results are needed; buying Plus does not guarantee a rating. <a href="{{ route('player-plus.terms') }}">Purchase details &amp; payment support</a></p>
          </section>
          <form method="post" action="{{ route('player-plus.store', $player) }}" class="pp-purchase" data-plus-plan="annual_recurring" aria-labelledby="pp-annual-heading">
            @csrf
            <input type="hidden" name="request_token" value="{{ $token }}">
            <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
            <input type="hidden" name="quoted_price_cents" value="{{ $priceCents }}">
            <input type="hidden" name="plan" value="annual_recurring">
            <button type="button" class="btn btn-outline-primary" data-plus-change-plan hidden>← Change plan</button>
            <h2 id="pp-annual-heading" tabindex="-1">Review &amp; pay · Annual Player Plus</h2>
            <p>For {{ $player->full_name }}</p>
            <p class="pp-plan-price">R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} <span>/ player / year</span></p>
            <p class="pp-plan-note">Pay now, then renew automatically each year through PayFast until cancelled. Stop future renewals from My Plus orders; paid access continues until the paid period ends.</p>
            <button type="button" class="btn btn-outline-primary" data-plus-choose="annual" hidden data-plus-wallet-alternative>Pay once from wallet instead · No automatic renewal</button>
            <fieldset class="pp-consents"><legend>Confirm your purchase</legend>
              <label class="pp-consent" for="pp-annual-confirm"><input id="pp-annual-confirm" type="checkbox" name="confirm" value="1" required @error('confirm') aria-invalid="true" aria-describedby="pp-annual-confirm-error" @enderror><span>I have read the <a href="{{ route('player-plus.terms') }}">purchase details</a> and am buying Player Plus for {{ $player->full_name }}. Ratings or pathway assessments may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
              @error('confirm')<p class="pp-field-error" id="pp-annual-confirm-error">{{ $message }}</p>@enderror
              <label class="pp-consent" for="pp-annual-recurring"><input id="pp-annual-recurring" type="checkbox" name="recurring_consent" value="1" required @error('recurring_consent') aria-invalid="true" aria-describedby="pp-annual-recurring-error" @enderror><span>I authorise R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} now and automatic annual payments of R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} through PayFast until I cancel.</span></label>
              @error('recurring_consent')<p class="pp-field-error" id="pp-annual-recurring-error">{{ $message }}</p>@enderror
            </fieldset>
            <button class="btn btn-primary">Continue to PayFast · R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }}</button>
          </form>
          <form method="post" action="{{ route('player-plus.store', $player) }}" class="pp-purchase" data-plus-plan="annual" aria-labelledby="pp-wallet-heading">
            @csrf
            <input type="hidden" name="request_token" value="{{ $token }}">
            <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
            <input type="hidden" name="quoted_price_cents" value="{{ $priceCents }}">
            <input type="hidden" name="plan" value="annual">
            <input type="hidden" name="payment_method" value="wallet">
            <button type="button" class="btn btn-outline-primary" data-plus-change-plan hidden>← Change plan</button>
            <h2 id="pp-wallet-heading" tabindex="-1">Review &amp; pay · Wallet</h2>
            <p>For {{ $player->full_name }}</p>
            <p class="pp-plan-price">R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} <span>for 12 months</span></p>
            <p class="pp-plan-note">Pay once from your wallet. No automatic renewal or future wallet debit. Available wallet funds: <strong>R{{ \App\Services\Performance\PlayerPlusService::amount($walletAvailableCents ?? 0) }}</strong>. Funds reserved for other checkouts are excluded.</p>
            @if(($walletAvailableCents ?? 0) < $priceCents)<p class="alert alert-info">Your available wallet funds do not cover this purchase. Choose the PayFast plan or add wallet funds before paying.</p>@endif
            <fieldset class="pp-consents"><legend>Confirm your wallet purchase</legend>
              <label class="pp-consent" for="pp-wallet-confirm"><input id="pp-wallet-confirm" type="checkbox" name="confirm" value="1" required @error('confirm') aria-invalid="true" aria-describedby="pp-wallet-confirm-error" @enderror><span>I have read the <a href="{{ route('player-plus.terms') }}">purchase details</a> and am buying Player Plus for {{ $player->full_name }}. Ratings may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
              @error('confirm')<p class="pp-field-error" id="pp-wallet-confirm-error">{{ $message }}</p>@enderror
              <label class="pp-consent" for="pp-wallet-consent"><input id="pp-wallet-consent" type="checkbox" name="wallet_consent" value="1" required @error('wallet_consent') aria-invalid="true" aria-describedby="pp-wallet-consent-error" @enderror><span>I authorise a once-off R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} debit from my wallet for 12 months. There is no automatic renewal.</span></label>
              @error('wallet_consent')<p class="pp-field-error" id="pp-wallet-consent-error">{{ $message }}</p>@enderror
            </fieldset>
            <button class="btn btn-primary" @disabled(($walletAvailableCents ?? 0) < $priceCents)>Pay from wallet · R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }}</button>
          </form>
          @if($monthlyEnabled)
            <form method="post" action="{{ route('player-plus.store', $player) }}" class="pp-purchase" data-plus-monthly-purchase data-plus-plan="monthly" aria-labelledby="pp-monthly-heading">
              @csrf
              <button type="button" class="btn btn-outline-primary" data-plus-change-plan hidden>← Change plan</button>
              <h2 id="pp-monthly-heading" tabindex="-1">Review &amp; pay · Monthly Player Plus</h2>
              <p>For {{ $player->full_name }}</p>
              <p class="pp-plan-price">R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} <span>/ player / month</span></p>
              <p class="pp-plan-note">Pay the first month now. PayFast charges the same agreed price each month until you cancel. Stop future renewals from My Plus orders; verified paid access remains until the end of the paid period.</p>
              <input type="hidden" name="request_token" value="{{ $token }}">
              <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
              <input type="hidden" name="plan" value="monthly">
              <input type="hidden" name="quoted_price_cents" value="{{ $monthlyPriceCents }}">
              <fieldset class="pp-consents"><legend>Confirm your purchase</legend>
                <label class="pp-consent" for="pp-monthly-confirm"><input id="pp-monthly-confirm" type="checkbox" name="confirm" value="1" required @error('confirm') aria-invalid="true" aria-describedby="pp-monthly-confirm-error" @enderror><span>I have read the <a href="{{ route('player-plus.terms') }}">purchase details</a> and am buying Player Plus for {{ $player->full_name }}. Ratings or pathway assessments may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
                @error('confirm')<p class="pp-field-error" id="pp-monthly-confirm-error">{{ $message }}</p>@enderror
                <label class="pp-consent" for="pp-monthly-recurring"><input id="pp-monthly-recurring" type="checkbox" name="recurring_consent" value="1" required @error('recurring_consent') aria-invalid="true" aria-describedby="pp-monthly-recurring-error" @enderror><span>I authorise a payment of R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} now and automatic monthly payments of R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} through PayFast until I cancel.</span></label>
                @error('recurring_consent')<p class="pp-field-error" id="pp-monthly-recurring-error">{{ $message }}</p>@enderror
              </fieldset>
              <button class="btn btn-primary">Continue to PayFast · R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }}</button>
            </form>
          @else
            <p class="pp-plan-availability">Monthly purchases are currently unavailable. Annual PayFast and once-off wallet options are available above.</p>
          @endif
        @else
          <section class="pp-status" aria-label="Purchases paused"><h2>Purchases are paused</h2><p>New purchases are currently paused. Existing verified memberships keep their benefits until expiry.</p></section>
        @endif
      @endif
    </section>
  </div>
  <p class="small">To stop a PayFast subscription, open <strong>My Plus orders</strong> from My Profile or your player dashboard, choose the order, then <strong>Stop automatic renewal</strong>. Only the payer can cancel. Paid access continues until expiry. Once-off wallet purchases have no renewal to cancel.</p>
  @if($canViewRating)
    <section class="pp-actions" aria-labelledby="pp-explore-heading"><h2 id="pp-explore-heading">Follow {{ $player->name }}’s progress</h2><p>Your available player views, together in one place.</p><div class="pp-action-links">
      <a class="btn btn-primary" href="{{ route('my.tennis', ['player' => $player->id]) }}#rating">View rating and progress <span class="ms-2" aria-hidden="true">→</span></a>
      <a class="btn btn-outline-primary" data-pathway-navigation href="{{ route('my.tennis.pathway', $player) }}">View tennis pathway</a>
      <a class="btn btn-outline-primary" href="{{ route('my.tennis.record', $player) }}">Playing Record</a>
      <a class="btn btn-outline-primary" href="{{ route('my.tennis.record', ['player' => $player, 'section' => 'opponents']) }}">Opponent Insights</a>
      <a class="btn btn-outline-primary" href="{{ route('my.tennis.event-history', $player) }}">Past-event ratings</a>
    </div></section>
  @endif
  <script src="{{ asset('assets/js/player-plus-stepper.js') }}" defer></script>
  <script src="{{ asset('assets/js/player-pathway.js') }}?v={{ filemtime(public_path('assets/js/player-pathway.js')) }}" defer></script>
  <p class="pp-footer"><a href="{{ route('my.tennis') }}">← Back to My Tennis</a></p>
</div>
@endsection
