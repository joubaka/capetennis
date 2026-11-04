@extends('layouts/layoutMaster')
@section('title', 'Review late team payment')
@section('content')
<div class="container-xxl container-p-y">
  <a href="{{ route('backend.late-team-payments.index', $event) }}">Back to late payments</a>
  <h3 class="mt-3">Review registration confirmation</h3>
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <div class="card"><div class="card-body">
    <h5>{{ $order->player?->name }} {{ $order->player?->surname }}</h5>
    <p>{{ $event->name }} · {{ $order->team?->name }} · Order #{{ $order->id }}</p>
    <p>Verified receipt: <strong>{{ $result['pf_payment_id'] }}</strong><br>Amount received: <strong>R{{ number_format($result['amount'], 2) }}</strong></p>
    <p>Reason: {{ $data['reason'] }}</p>
    <p>All payment, payer, roster and invitation checks passed. Confirming uses the existing receipt and marks this player registered. No new charge, wallet debit or confirmation email is made.</p>
    @if(count($result['successor_order_ids']))
      <p class="alert alert-warning">The following unpaid replacement checkouts will close: {{ implode(', ', $result['successor_order_ids']) }}.</p>
    @endif
    <form method="POST" action="{{ route('backend.late-team-payments.apply', [$event, $order]) }}">
      @csrf
      <input type="hidden" name="preview_token" value="{{ $token }}">
      <label class="d-flex gap-2 align-items-start mb-3" style="min-height:44px"><input class="form-check-input flex-shrink-0" type="checkbox" name="confirm" value="1" required> I reviewed this payment and want to confirm this registration.</label>
      <button class="btn btn-success" style="min-height:44px" type="submit">Reconcile payment and confirm registration</button>
    </form>
  </div></div>
</div>
@endsection
