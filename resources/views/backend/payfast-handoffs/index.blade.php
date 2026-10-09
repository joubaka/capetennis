@extends('layouts/layoutMaster')
@section('title', 'PayFast checkout recovery')
@section('content')
<div class="container-xxl container-p-y">
  <a href="{{ route('backend.payfast-handoffs.events') }}">All events</a>
  <h3 class="mt-3">{{ $event->name }}: PayFast checkout recovery</h3>
  <div class="alert alert-warning">Check the exact order in PayFast before cancelling checkout. Only cancel an attempt confirmed failed, cancelled, or never created, with no payment received and no pending or payable attempt. A parent returning or reporting no payment is insufficient. Completed payments need reconciliation instead. Cancelling releases the wallet reservation so the parent can start registration again.</div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  @forelse($orders as $order)
    <div class="card mb-3"><div class="card-body">
      <h5>Order #{{ $order->id }}</h5>
      <p>{{ $order->user?->name }} · {{ $order->user?->email }}<br>Sent {{ $order->payfast_handed_off_at->format('d M Y H:i:s') }} · PayFast R{{ number_format($order->payfast_amount_due, 2) }} · Wallet reserved R{{ number_format($order->wallet_reserved, 2) }}</p>
      <form method="POST" action="{{ route('backend.payfast-handoffs.release', [$event, $order]) }}">
        @csrf
        <input type="hidden" name="handed_off_at" value="{{ $order->payfast_handed_off_at->toISOString() }}">
        <label class="form-label" for="evidence-{{ $order->id }}">Provider evidence reference</label>
        <input class="form-control mb-3" id="evidence-{{ $order->id }}" name="evidence_reference" required minlength="3" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9._:/\-]{2,119}" placeholder="PayFast case or reconciliation reference">
        <label class="d-flex gap-2 align-items-start mb-3" style="min-height:44px"><input class="form-check-input flex-shrink-0" type="checkbox" name="provider_attempt_closed" value="1" required> I verified this exact attempt is failed, cancelled, or never created; no payment was received and no pending or payable attempt remains.</label>
        <button class="btn btn-warning" style="min-height:44px" type="submit">Cancel checkout so parent can start again</button>
      </form>
    </div></div>
  @empty
    <p>No unresolved registration checkouts for this event.</p>
  @endforelse
  {{ $orders->links() }}
</div>
@endsection
