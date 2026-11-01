@extends('layouts.backend')
@section('title', 'Player Plus payment review')
@section('content')
<div class="container-xxl container-p-y" style="max-width:1000px;overflow-wrap:anywhere">
  <a href="{{ route('superadmin.player-plus.index') }}">Back to Plus payment reviews</a>
  <h1 class="mt-3">Payment review · P{{ $order->id }}</h1>
  @if(session('success'))<p class="alert alert-success">{{ session('success') }}</p>@endif
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  <p><strong>{{ $order->player?->full_name ?? 'Player unavailable' }}</strong> · Profile #{{ $order->player_id }}<br>Original payer: {{ $order->user?->name }} · {{ $order->user?->email }}<br>Stored checkout price: R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }}<br>Status: {{ $order->status }} · {{ $order->activated_at ? 'Original membership was activated' : 'Membership was not activated' }}</p>
  <p class="alert alert-warning">{{ $order->reconciliation_reason }}</p>
  <h2 class="h4">Verified provider receipts</h2>
  <p>{{ $receipts->total() }} verified receipts across {{ $receipts->lastPage() }} {{ $receipts->lastPage() === 1 ? 'page' : 'pages' }}. A resolution covers the complete receipt set, including receipts on other pages.</p>
  <div class="list-group">
    @foreach($receipts as $receipt)<div class="list-group-item">{{ $receipt->pf_payment_id }} · R{{ number_format((float) $receipt->amount_gross, 2) }}<span class="d-block small">Receipt #{{ $receipt->id }} · {{ $receipt->created_at }}</span></div>@endforeach
  </div>
  <div class="mt-3">{{ $receipts->links() }}</div>
  @if($resolution)
    <p class="alert alert-info">External resolution evidence is recorded for this exact set of receipts. New verified receipts require a new review. Payment status and original membership history are preserved.</p>
  @else
    <form method="post" action="{{ route('superadmin.player-plus.store', $order) }}" class="card card-body mt-4">
      @csrf
      <input type="hidden" name="payment_fingerprint" value="{{ $fingerprint }}">
      <h2 class="h4">Record an externally reviewed resolution</h2>
      <p>First verify the payer, all provider references, any earlier resolution and the approved remedy. Complete any authorised financial action through the established financial process. Retain its evidence before recording this note. Do not enter bank account numbers, card details or credentials.</p>
      <label class="form-label" for="plus-reference">External evidence reference</label>
      <input class="form-control mb-3" style="min-height:44px" id="plus-reference" name="reference" value="{{ old('reference') }}" minlength="3" maxlength="150" required>
      <label class="form-label" for="plus-note">Resolution and evidence reviewed</label>
      <textarea class="form-control mb-3" id="plus-note" name="note" rows="4" minlength="10" maxlength="1500" required>{{ old('note') }}</textarea>
      <label class="d-flex gap-2"><input type="checkbox" class="mt-1" name="confirm" value="1" required><span>I verified all {{ $receipts->total() }} receipts across every page and retained evidence of the external resolution. This action only records that review.</span></label>
      <button class="btn btn-primary align-self-start mt-3" style="min-height:44px">Record resolution evidence</button>
    </form>
  @endif
  <h2 class="h4 mt-4">Append-only review history</h2>
  @forelse($history as $item)
    <div class="border rounded p-3 mb-2"><strong>{{ $item->created_at }} · {{ $item->actor_name ?? 'Recorded actor' }}</strong><p class="mb-1">Reference: {{ $item->metadata['external_reference'] ?? '' }}</p><p class="mb-0" style="white-space:pre-wrap">{{ $item->reason }}</p></div>
  @empty<p>No resolution evidence has been recorded yet.</p>@endforelse
  {{ $history->links() }}
</div>
@endsection
