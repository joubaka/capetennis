@extends('layouts.backend')
@section('title', 'Review team withdrawal')
@section('content')
<div class="container-xl py-3" style="max-width:760px">
  <h1 class="h4">Review withdrawal / refund</h1>
  <p class="text-muted">{{ $event->name }} · {{ $team->name }}</p>
  <div class="card"><div class="card-body">
    <h2 class="h5">{{ $slot->player?->full_name }} · Rank {{ $slot->rank }}</h2>
    <p>Original payer: <strong>{{ $review['order']->user->name }}</strong> · Order #{{ $review['order']->id }}</p>
    @if($review['refundAvailable'])
      <dl class="row"><dt class="col-7">Verified payment</dt><dd class="col-5 text-end">R{{ number_format($review['amounts']['gross'], 2) }}</dd>
        <dt class="col-7">Withdrawal fee</dt><dd class="col-5 text-end">R{{ number_format($review['amounts']['fee'], 2) }}</dd>
        <dt class="col-7">Refund to payer wallet</dt><dd class="col-5 text-end fw-bold">R{{ number_format($review['amounts']['net'], 2) }}</dd></dl>
      <p>The refund goes to the original payer’s wallet. This action does not send a PayFast or bank refund.</p>
    @else
      <div class="alert alert-warning">The withdrawal deadline has passed. This will withdraw the player without a refund.</div>
    @endif
    <p>Confirmation frees this roster place and removes upcoming fixture participation. Completed matches must be resolved first. No reserve is automatically promoted.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('backend.team.withdrawal.store', [$event, $team, $slot]) }}">
      @csrf
      <input type="hidden" name="order_id" value="{{ $review['order']->id }}"><input type="hidden" name="fingerprint" value="{{ $review['fingerprint'] }}">
      <label class="form-label w-100">Reason<textarea class="form-control" name="reason" required minlength="3" maxlength="1000" rows="3">{{ old('reason') }}</textarea></label>
      <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">{{ $review['refundAvailable'] ? 'Confirm withdrawal and the displayed refund to the original payer’s wallet.' : 'Confirm withdrawal without a refund.' }}</span></label>
      <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger">{{ $review['refundAvailable'] ? 'Withdraw and refund to wallet' : 'Withdraw without refund' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $team->region_id]) }}">Cancel</a></div>
    </form>
  </div></div>
</div>
@endsection
