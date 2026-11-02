@extends('layouts/contentNavbarLayout')
@section('title', 'My Player Plus orders')
@section('content')
<div class="container-xxl container-p-y pp-journey" style="max-width:900px">
  @include('frontend.player-plus.styles')
  <header class="pp-visual-hero">
    <span class="pp-hero-icon"><i class="ti ti-receipt" aria-hidden="true"></i></span>
    <span class="pp-visual-eyebrow">Player Plus · Your memberships</span>
    <h1>My Player Plus orders</h1>
    <p class="mb-0">Your purchases and payment attempts appear here. Sponsoring a player does not grant access to their private ratings.</p>
  </header>
  <a class="btn btn-outline-primary mb-3" style="min-height:44px" href="{{ route('player-plus.index') }}">Player memberships</a>
  <div class="list-group pp-order-list">
    @forelse($orders as $order)
      <a class="list-group-item list-group-item-action pp-order-row" href="{{ route('player-plus.orders.show', $order) }}">
        <span class="pp-order-icon"><i class="ti ti-file-invoice" aria-hidden="true"></i></span>
        <div class="pp-order-copy">
        <strong>P{{ $order->id }} · {{ $order->player?->full_name ?? 'Player profile unavailable' }}</strong>
        <span class="d-block">R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} · {{ $order->isMonthly() ? 'Monthly' : 'Annual' }} · {{ str_replace('_', ' ', ucfirst($order->status)) }}</span>
        @if($order->term_ends_at)<span class="d-block">Purchased term {{ $order->term_ends_at->isFuture() ? 'ends' : 'ended' }} {{ $order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y') }}</span>@endif
        @if($order->reconciliation_reason)<span class="d-block">Payment review recorded. Open this order for support details.</span>@endif
        </div>
        <i class="ti ti-chevron-right" aria-hidden="true"></i>
      </a>
    @empty<p class="alert alert-info">You have no Player Plus orders yet.</p>@endforelse
  </div>
  <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
