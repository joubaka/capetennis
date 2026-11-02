@extends('layouts.backend')
@section('title', 'Player Plus payment reviews')
@section('content')
<div class="container-xxl container-p-y" style="max-width:1000px">
  <a href="{{ route('superadmin.player-plus.manage.index') }}">← Player Plus management</a>
  <h1>Player Plus payment reviews</h1>
  <p>Review late and additional payments against the original payer and every provider receipt. This queue records evidence of an external resolution; it does not execute refunds, transfer a purchase or activate membership.</p>
  <div class="list-group">
    @forelse($orders as $order)
      @php($resolution = $review->reviews($order)->where('metadata->payment_fingerprint', $review->fingerprint($order))->first())
      <a class="list-group-item list-group-item-action py-3" href="{{ route('superadmin.player-plus.show', $order) }}">
        <strong>P{{ $order->id }} · {{ $order->player?->full_name ?? 'Player unavailable' }}</strong>
        <span class="d-block">{{ $resolution ? 'External resolution evidence recorded' : 'Needs review' }} · {{ $order->user?->name ?? 'Payer unavailable' }}</span>
        <span class="d-block">{{ $order->reconciliation_reason }}</span>
      </a>
    @empty<p class="alert alert-info">No Plus payments require review.</p>@endforelse
  </div>
  <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
