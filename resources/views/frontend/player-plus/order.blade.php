@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus checkout')
@section('content')
<div class="container-xxl container-p-y" style="max-width:900px">
  <h1>Player Plus checkout</h1>
  <h2 class="h4">{{ $order->player->full_name }} · R499 for 12 months</h2>
  <p>Profile #{{ $order->player_id }} @if($order->player->dateOfBirth) · Born {{ substr($order->player->dateOfBirth, 0, 4) }} @endif</p>
  <p>Order P{{ $order->id }} · {{ str_replace('_', ' ', ucfirst($order->status)) }}</p>
  <x-player-plus-benefits />
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  @if($order->activated_at)
    <p class="alert alert-success">Payment verified. This player is covered until {{ $order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }}. No automatic renewal.</p>
    @if($order->reconciliation_reason)<p class="alert alert-warning">An additional payment needs Cape Tennis reconciliation. The original membership term is unchanged. Keep order P{{ $order->id }} as your reference.</p>@endif
  @elseif($order->status === 'reconciliation_required')
    <p class="alert alert-warning">Your payment was received and needs Cape Tennis reconciliation. Membership was not activated. Keep order P{{ $order->id }} as your reference.</p>
  @elseif($order->status === 'pending' && $order->expires_at->isFuture())
    @if($form)
      <p>Continue to PayFast to make a once-off R499 payment. Membership starts only after verified payment confirmation.</p>
      {!! $form !!}
      <button type="submit" form="payfastForm" class="btn btn-primary" style="min-height:44px">Continue to PayFast · R499</button>
    @elseif(!$order->payfast_handed_off_at)
      <form method="post" action="{{ route('player-plus.orders.pay', $order) }}">@csrf<button class="btn btn-primary" style="min-height:44px">Pay R499 with PayFast</button></form>
    @else
      <p class="alert alert-info">Awaiting verified PayFast confirmation. Returning to this page does not activate membership.</p>
      <a href="{{ route('player-plus.orders.show', $order) }}" class="btn btn-outline-primary">Check payment status</a>
    @endif
  @else<p>This checkout is closed or expired. A delayed payment will be held for reconciliation with the original payer.</p>@endif
  @if($order->status === 'pending' && (!$order->payfast_handed_off_at || !$order->expires_at->isFuture()))
    <form class="mt-3" method="post" action="{{ route('player-plus.orders.cancel', $order) }}">@csrf<button class="btn btn-outline-secondary">Close checkout</button></form>
  @endif
  <p class="mt-3"><a href="{{ route('player-plus.player', $order->player) }}">Player membership and benefits</a></p>
</div>
@endsection
