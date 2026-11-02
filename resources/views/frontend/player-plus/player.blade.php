@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus registration')
@section('content')
<div class="container-xxl container-p-y pp-player" data-plus-player-page>
  <style>
    .pp-player{max-width:1080px;color:#243e50}.pp-player .pp-nav{display:flex;flex-wrap:wrap;gap:.25rem 1.5rem;justify-content:space-between;margin-bottom:1rem}.pp-player .pp-nav a{min-height:44px;display:inline-flex;align-items:center;font-size:.875rem}
    .pp-player .pp-hero{display:flex;justify-content:space-between;gap:2rem;align-items:center;background:#153b4b;color:#fff;border-radius:22px;padding:2.25rem;margin-bottom:1.5rem;position:relative;overflow:hidden}.pp-player .pp-hero:after{content:'';position:absolute;width:240px;height:240px;border:1px solid #ffffff18;border-radius:50%;right:-90px;top:-100px;pointer-events:none}.pp-player .pp-eyebrow{font-size:.72rem;letter-spacing:.16em;text-transform:uppercase;color:#a9e0cf;font-weight:700;margin:0 0 .8rem}.pp-player .pp-hero h1{color:#fff;font-size:clamp(1.7rem,4vw,2.4rem);letter-spacing:-.035em;line-height:1.15;margin:0 0 .75rem;max-width:650px}.pp-player .pp-intro{color:#d2e3e8;max-width:540px;line-height:1.65;margin:0}.pp-player .pp-profile{color:#b9d2d8;font-size:.78rem;margin:1rem 0 0}.pp-player .pp-price{flex-shrink:0;border-left:1px solid #ffffff30;padding-left:2rem;position:relative;z-index:1}.pp-player .pp-price strong{font-size:2.25rem;display:block;letter-spacing:-.04em}.pp-player .pp-price span{display:block;font-size:.8rem;color:#d2e3e8}.pp-player .pp-price small{display:block;max-width:175px;margin-top:.8rem;color:#a9e0cf;line-height:1.5}
    .pp-player .pp-benefits{padding:1.5rem!important;border-radius:18px!important;box-shadow:0 4px 20px #153b4b05}.pp-player .pp-benefits>div:first-child{display:none!important}.pp-player .pp-benefits>p:first-of-type{color:#526c78;line-height:1.7}.pp-player .pp-benefits>p.fw-semibold{font-size:1.2rem;color:#173b4b;margin-top:1.25rem}.pp-player .pp-benefits>ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;list-style:none;padding:0!important;margin:1.25rem 0!important}.pp-player .pp-benefits>ul>li{position:relative;padding:1.2rem 1.2rem 1.2rem 3.8rem;background:#f3f8f6;border:1px solid #e2ede7;border-radius:12px;line-height:1.7;color:#385763}.pp-player .pp-benefits>ul>li:before{content:'↗';position:absolute;left:1rem;top:1.2rem;display:grid;place-items:center;width:1.9rem;height:1.9rem;border-radius:8px;background:#dfefe7;color:#176b55;font-size:1.15rem;font-weight:700}.pp-player .pp-benefits>ul>li:nth-child(2):before{content:'≡'}.pp-player .pp-benefits>ul>li:nth-child(3):before{content:'★'}.pp-player .pp-benefits>ul>li:nth-child(4):before{content:'→'}.pp-player .pp-benefits details{border-top:1px solid #e2ebe7;padding-top:.5rem}.pp-player .pp-benefits summary{font-weight:600;color:#254e5a}
    .pp-player .pp-status{border:1px solid #dce7e5;border-left:4px solid #437d7b;background:#f0f6f6;border-radius:12px;padding:1.2rem 1.4rem;margin-top:1.25rem;line-height:1.65}.pp-player .pp-status strong{color:#244c59}.pp-player .pp-status p{color:#526b75}.pp-player .pp-status[data-plus-readiness="available"]{border-left-color:#258164;background:#eef7f2}.pp-player .pp-purchase{border-radius:16px;border:1px solid #dce7e5;padding:1.5rem;line-height:1.7}.pp-player .pp-actions{margin-top:1.5rem;padding:1.5rem;border:1px solid #dce7e5;border-radius:16px;background:#fff}.pp-player .pp-actions h2{font-size:1.1rem;color:#173b4b;margin:0 0 .5rem}.pp-player .pp-actions p{font-size:.875rem;color:#607681;margin-bottom:1rem}.pp-player .pp-action-links{display:flex;gap:.75rem;flex-wrap:wrap}.pp-player .pp-action-links .btn{min-height:46px;display:inline-flex;align-items:center;justify-content:center;white-space:normal}.pp-player .btn-primary{background:#176b59;border-color:#176b59}.pp-player .btn-primary:hover{background:#10523f;border-color:#10523f}.pp-player .btn-outline-primary{color:#176b59;border-color:#a8c8bc}.pp-player .btn-outline-primary:hover{background:#eef7f2;border-color:#176b59;color:#10523f}.pp-player a:not(.btn){color:#176b59}.pp-player .pp-footer{border-top:1px solid #dce7e5;margin-top:1.75rem;padding-top:1rem;font-size:.875rem}.pp-player a:focus-visible,.pp-player button:focus-visible,.pp-player summary:focus-visible{outline:3px solid #409b86;outline-offset:3px}
    @media(max-width:767.98px){.pp-player .pp-hero{padding:1.5rem;align-items:flex-start;flex-direction:column;gap:1.25rem}.pp-player .pp-price{border-left:0;border-top:1px solid #ffffff30;padding:1rem 0 0;width:100%}.pp-player .pp-price small{max-width:none}.pp-player .pp-price strong{font-size:1.9rem}.pp-player .pp-benefits>ul{grid-template-columns:1fr}.pp-player .pp-benefits{padding:1.2rem!important}.pp-player .pp-actions{padding:1.2rem}.pp-player .pp-action-links{flex-direction:column}.pp-player .pp-action-links .btn{width:100%}}
  </style>
  <nav class="pp-nav" aria-label="Player Plus navigation"><a href="{{ route('player-plus.index') }}">← Choose another player</a><a href="{{ route('player-plus.orders.index') }}">My Plus orders →</a></nav>
  <header class="pp-hero">
    <div><p class="pp-eyebrow">Cape Tennis · Optional Player Plus</p><h1>Player Plus for {{ $player->full_name }}</h1><p class="pp-intro">A clearer picture of their tennis. Understand playing strength, follow progress and explore the next step.</p><p class="pp-profile">Profile #{{ $player->id }} @if($player->dateOfBirth) · Born {{ substr($player->dateOfBirth, 0, 4) }} @endif</p></div>
    <div class="pp-price"><strong>R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }}</strong><span>per player / year</span><small>Annual automatic renewal<br>Renews until cancelled</small>@if($monthlyEnabled)<small>Or R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} / month<br>Automatically renews until cancelled</small>@endif</div>
  </header>
  <x-player-plus-benefits :price-cents="$priceCents" plan="annual_recurring" class="pp-benefits" />
  @if($readiness)
    <section class="pp-status text-body" aria-label="Rating availability" data-plus-readiness="{{ $readiness['state'] }}">
      <strong>{{ $readiness['label'] }}</strong><p class="mb-0 mt-1">{{ $readiness['description'] }}</p>
    </section>
  @endif
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  @if($membership && $membership->starts_at->lte(now()) && $membership->ends_at->isFuture())
    <p class="alert alert-success">This player is covered until {{ $membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }}. @if($membership->order?->isRecurring())The payer can manage automatic renewal in My Plus orders.@else This existing once-off term has no automatic renewal or charge.@endif</p>
  @else
    @if($membership && $linked)<p class="alert alert-secondary mt-3">Membership expired on {{ $membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }}. @if($membership->order?->isRecurring())The payer should check payment and renewal status in My Plus orders.@else No renewal payment has been taken automatically.@endif</p>@endif
    @if($enabled && \App\Services\Performance\PlayerPlusService::monthlyReady())
    <form method="post" action="{{ route('player-plus.store', $player) }}" class="card card-body mt-3 pp-purchase">
      @csrf
      <input type="hidden" name="request_token" value="{{ $token }}">
      <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
      <input type="hidden" name="quoted_price_cents" value="{{ $priceCents }}">
      <input type="hidden" name="plan" value="annual_recurring">
      <h2 class="h5">Annual automatic renewal · R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} / year</h2>
      <label class="d-flex gap-2 align-items-start"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I have read the <a href="{{ route('player-plus.terms') }}">purchase details</a> and am buying Player Plus for {{ $player->full_name }}. Ratings or pathway assessments may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
      <label class="d-flex gap-2 align-items-start mt-3"><input type="checkbox" name="recurring_consent" value="1" required class="mt-1"><span>I authorise R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} now and automatic annual payments of R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} through PayFast until I cancel. Stop future renewals from My Plus orders; verified paid access remains until the paid period ends.</span></label>
      <button class="btn btn-primary mt-3 align-self-start" style="min-height:44px">Start annual checkout · R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }}</button>
    </form>
    @if($monthlyEnabled)
    <form method="post" action="{{ route('player-plus.store', $player) }}" class="card card-body mt-3 pp-purchase" data-plus-monthly-purchase>
      @csrf
      <h2 class="h5">Monthly automatic renewal · R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} / month</h2>
      <p>Pay the first month now. PayFast charges the same agreed price each month until you cancel. Stop future renewals from My Plus orders; verified paid access remains until the end of the paid period.</p>
      <input type="hidden" name="request_token" value="{{ $token }}">
      <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
      <input type="hidden" name="plan" value="monthly">
      <input type="hidden" name="quoted_price_cents" value="{{ $monthlyPriceCents }}">
      <label class="d-flex gap-2 align-items-start"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I have read the <a href="{{ route('player-plus.terms') }}">purchase details</a>. I am buying Player Plus for {{ $player->full_name }}. Ratings may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
      <label class="d-flex gap-2 align-items-start mt-3"><input type="checkbox" name="recurring_consent" value="1" required class="mt-1"><span>I authorise a payment of R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} now and automatic monthly payments of R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }} through PayFast until I cancel.</span></label>
      <button class="btn btn-primary mt-3 align-self-start" style="min-height:44px">Start monthly checkout · R{{ \App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents) }}</button>
    </form>
    @endif
    @else<section class="pp-status" aria-label="Purchases paused"><strong>Purchases are paused</strong><p class="mb-0 mt-1">New purchases are currently paused. Existing verified memberships keep their benefits until expiry.</p></section>@endif
  @endif
  @if($canViewRating)
    <section class="pp-actions" aria-labelledby="pp-explore-heading"><h2 id="pp-explore-heading">Follow {{ $player->name }}’s progress</h2><p>Your available player views, together in one place.</p><div class="pp-action-links">
    <a class="btn btn-primary" href="{{ route('my.tennis', ['player' => $player->id]) }}#rating">View rating and progress <span class="ms-2" aria-hidden="true">→</span></a>
    <a class="btn btn-outline-primary" href="{{ route('my.tennis.pathway', $player) }}">View tennis pathway</a>
    <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $player->id]) }}#rating">Explore CTR ratings</a>
    </div></section>
  @endif
  @unless($linked)<p class="mt-3">You are sponsoring this player. Only the player and accounts already linked to them can use their private Player Plus benefits.</p>@endunless
  <p class="pp-footer"><a href="{{ route('my.tennis') }}">← Back to My Tennis</a></p>
</div>
@endsection
