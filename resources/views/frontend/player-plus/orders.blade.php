@extends('layouts/contentNavbarLayout')
@section('title', 'My Player Plus orders')
@section('content')
<div class="container-xxl container-p-y pp-journey ct-player-workspace" style="max-width:900px">
  @include('frontend.player-plus.styles')
  <x-player-workspace-header :workspace="$workspace ?? null" title="My Player Plus orders" eyebrow="Player Plus · Your memberships" subtitle="Your purchases and payment attempts appear here. Sponsoring a player does not grant access to their private ratings.">
    <x-slot:navigation><nav class="d-flex flex-wrap gap-2" aria-label="Player Plus navigation"><a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $workspace['player']->id ?? null]) }}">My Tennis</a><a class="btn btn-outline-primary" href="{{ route('player-plus.index', ['player' => $workspace['player']->id ?? null]) }}">Player memberships</a></nav></x-slot:navigation>
  </x-player-workspace-header>
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
