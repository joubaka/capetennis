@extends('layouts/contentNavbarLayout')
@section('title', 'My Player Plus orders')
@section('content')
<div class="container-xxl container-p-y" style="max-width:900px">
  <h1>My Player Plus orders</h1>
  <p>Your purchases and payment attempts appear here. Sponsoring a player does not grant access to their private ratings.</p>
  <a class="btn btn-outline-primary mb-3" style="min-height:44px" href="{{ route('player-plus.index') }}">Player memberships</a>
  <div class="list-group">
    @forelse($orders as $order)
      <a class="list-group-item list-group-item-action py-3" href="{{ route('player-plus.orders.show', $order) }}">
        <strong>P{{ $order->id }} · {{ $order->player?->full_name ?? 'Player profile unavailable' }}</strong>
        <span class="d-block">R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} · {{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
        @if($order->term_ends_at)<span class="d-block">Purchased term {{ $order->term_ends_at->isFuture() ? 'ends' : 'ended' }} {{ $order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y') }}</span>@endif
        @if($order->reconciliation_reason)<span class="d-block">Payment review recorded. Open this order for support details.</span>@endif
      </a>
    @empty<p class="alert alert-info">You have no Player Plus orders yet.</p>@endforelse
  </div>
  <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
