@extends('layouts.backend')
@section('title', 'Review original-source refund')
@section('content')
<style>
  .team-refund-review .btn { min-height:44px; max-width:100%; white-space:normal; }
  .team-refund-review .form-control { min-height:44px; }
</style>
<div class="container-xl py-3 team-refund-review" style="max-width:760px">
  <h1 class="h4">Review original-source refund · Order #{{ $order->id }}</h1>
  <p>{{ $event->name }} · Original payer: {{ $order->user?->name }}</p>
  @foreach(['success', 'warning'] as $message)@if(session($message))<div class="alert alert-{{ $message }}" role="status">{{ session($message) }}</div>@endif @endforeach
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <div class="card"><div class="card-body">
    <p>The player is withdrawn and the roster place is free. Refund status: <strong>{{ $order->refund_status }}</strong>. PayFast confirmation: <strong>{{ $attempt->status }}</strong>.</p>
    <dl class="row"><dt class="col-7">Verified original payment</dt><dd class="col-5 text-end">R{{ number_format($attempt->gross, 2) }}</dd>
      <dt class="col-7">Handling fee</dt><dd class="col-5 text-end">R{{ number_format($attempt->fee, 2) }}</dd>
      <dt class="col-7">Original PayFast source</dt><dd class="col-5 text-end">R{{ number_format($attempt->amount, 2) }}</dd>
      <dt class="col-7">Original payer wallet</dt><dd class="col-5 text-end">R{{ number_format($attempt->wallet_amount, 2) }}</dd></dl>
    @if($order->refund_status !== 'completed')
      @if(in_array($attempt->status, ['ready', 'confirmed'], true))
        <p>{{ $attempt->status === 'confirmed' ? 'PayFast already confirmed. Finish the local refund record and any wallet portion without sending another provider refund.' : 'The request is saved but has not been sent. Continue the original-source refund.' }}</p>
        <form method="POST" action="{{ route('backend.team.withdrawal.refund.recover', [$event, $order]) }}">@csrf
          <input type="hidden" name="action" value="resume"><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">Confirm continuing this saved refund.</span></label>
          <button class="btn btn-primary">{{ $attempt->status === 'confirmed' ? 'Finish local refund' : 'Continue saved refund' }}</button>
        </form>
      @else
        @if($attempt->status === 'unavailable')<div class="alert alert-info">PayFast did not offer the requested amount back to its original source. No refund was submitted by this request. The refund remains pending; finance support must resolve it before another payout.</div>@endif
        <div class="alert alert-warning">PayFast has not confirmed this refund. Check PayFast history for payment {{ $attempt->pf_payment_id }}. Do not issue another refund or credit the wallet separately. If the exact refund is independently confirmed, record its evidence below. If PayFast cannot return it to its original source, resolve the pending case with finance support.</div>
        <form method="POST" action="{{ route('backend.team.withdrawal.refund.recover', [$event, $order]) }}">@csrf
          <input type="hidden" name="action" value="reconcile">
          <label class="form-label w-100">Original PayFast payment ID<input class="form-control" name="pf_payment_id" required maxlength="120" value="{{ old('pf_payment_id') }}"></label>
          <label class="form-label w-100">Confirmed provider refund amount<input class="form-control" name="amount" type="number" min="0.01" step="0.01" required value="{{ old('amount') }}"></label>
          <label class="form-label w-100">Provider refund reference<input class="form-control" name="reference" required maxlength="120" value="{{ old('reference') }}"></label>
          <label class="form-label w-100">Provider confirmation date and time<input class="form-control" name="confirmed_at" type="datetime-local" required value="{{ old('confirmed_at') }}"></label>
          <label class="form-label w-100">Evidence and reason<textarea class="form-control" name="reason" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea></label>
          <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="externally_confirmed" value="1" required><span class="form-check-label">I independently verified this exact refund to the original PayFast source.</span></label>
          <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">Record this confirmation and finish the local refund. No provider refund will be sent.</span></label>
          <button class="btn btn-primary">Record confirmed refund</button>
        </form>
      @endif
    @endif
  </div></div>
  <a class="btn btn-outline-secondary mt-3" href="{{ route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $order->team?->region_id]) }}">Back to team players</a>
</div>
@endsection
