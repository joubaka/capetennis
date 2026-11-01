@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus checkout')
@section('content')
<div class="container-xxl container-p-y" style="max-width:900px">
  @php($subscription = $subscription ?? null)
  <h1>Player Plus checkout</h1>
  <h2 class="h4">{{ $order->player?->full_name ?? 'Player profile unavailable' }} · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} {{ $order->isMonthly() ? 'per month · automatic renewal' : 'for 12 months' }}</h2>
  <p>Profile #{{ $order->player_id }} @if($order->player?->dateOfBirth) · Born {{ substr($order->player->dateOfBirth, 0, 4) }} @endif</p>
  <p>Order P{{ $order->id }} · {{ str_replace('_', ' ', ucfirst($order->status)) }}</p>
  <x-player-plus-benefits :price-cents="$order->price_cents" :plan="$order->isMonthly() ? 'monthly' : 'annual'" />
  @if(session('success'))<p class="alert alert-success">{{ session('success') }}</p>@endif
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  @if($order->activated_at)
    <p class="alert alert-{{ $order->term_ends_at->isFuture() ? 'success' : 'secondary' }}">Payment verified. Purchased membership term: {{ $order->term_starts_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }} to {{ $order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }} SAST. @if($order->isMonthly())Monthly payments and purchased terms are recorded separately in My Plus orders.@else {{ $order->term_ends_at->isFuture() ? 'No automatic renewal.' : 'This purchased term has expired. No automatic renewal payment was taken.' }}@endif</p>
    @if($order->reconciliation_reason)<p class="alert alert-warning">An additional payment needs Cape Tennis reconciliation. The original membership term is unchanged. Keep order P{{ $order->id }} as your reference.</p>@endif
  @elseif($order->status === 'reconciliation_required')
    <p class="alert alert-warning">Your payment was received and needs Cape Tennis reconciliation. Membership was not activated. Keep order P{{ $order->id }} as your reference.</p>
  @elseif($order->status === 'pending' && $order->expires_at->isFuture())
    @if($form)
      <p>Continue to PayFast to {{ $order->isMonthly() ? 'authorise a monthly subscription at' : 'make a once-off' }} R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }}{{ $order->isMonthly() ? ' per month until cancelled' : '' }}. Membership starts only after verified payment confirmation.</p>
      {!! $form !!}
      <button type="submit" form="payfastForm" class="btn btn-primary" style="min-height:44px">Continue to PayFast · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }}</button>
    @elseif(!$order->payfast_handed_off_at)
      @if(\App\Services\Performance\PlayerPlusService::salesEnabled())
        <form method="post" action="{{ route('player-plus.orders.pay', $order) }}">@csrf<button class="btn btn-primary" style="min-height:44px">Pay R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} with PayFast</button></form>
      @else<p class="alert alert-info">Purchases are paused. This order cannot begin payment.</p>@endif
    @else
      <p class="alert alert-info">Awaiting verified PayFast confirmation. Returning to this page does not activate membership.</p>
      <a href="{{ route('player-plus.orders.show', $order) }}" class="btn btn-outline-primary">Check payment status</a>
    @endif
  @else<p>This checkout is closed or expired. A delayed payment will be held for reconciliation with the original payer.</p>@endif
  @if($order->status === 'pending' && (!$order->payfast_handed_off_at || !$order->expires_at->isFuture()))
    <form class="mt-3" method="post" action="{{ route('player-plus.orders.cancel', $order) }}">@csrf<button class="btn btn-outline-secondary">Close checkout</button></form>
  @endif
  @if($subscription)
    <section class="border rounded p-3 mt-3" aria-label="Automatic renewal" data-plus-subscription>
      <h2 class="h5">Monthly automatic renewal</h2>
      @if($subscription->cancelled_at)
        <p>Automatic renewal stopped. PayFast confirmed cancellation on {{ $subscription->cancelled_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }} SAST. Verified paid access remains until its paid expiry.</p>
      @else
        @if($subscription->cancellation_requested_at)<p class="alert alert-warning">Cancellation needs PayFast confirmation. Automatic charges may continue until it is confirmed. Retry below or contact support.</p>
        @else<p>R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} per month. Next scheduled renewal: {{ $subscription->next_billing_at->timezone('Africa/Johannesburg')->format('j M Y') }}. Access extends only after verified payment.</p>@endif
        <form method="post" action="{{ route('player-plus.orders.stop-renewal', $order) }}" onsubmit="return confirm('Stop future monthly renewals? Your verified paid access remains until its paid expiry.');">
          @csrf<button class="btn btn-outline-danger" style="min-height:44px">{{ $subscription->cancellation_requested_at ? 'Retry stopping automatic renewal' : 'Stop automatic renewal' }}</button>
        </form>
      @endif
    </section>
  @endif
  @if($order->player)<p class="mt-3"><a href="{{ route('player-plus.player', $order->player) }}">Player membership and benefits</a></p>@endif
  <p><a href="{{ route('player-plus.orders.index') }}">My Plus orders</a> · <a href="mailto:support@capetennis.co.za?subject=Player%20Plus%20order%20P{{ $order->id }}">Contact payment support</a></p>
</div>
@endsection
