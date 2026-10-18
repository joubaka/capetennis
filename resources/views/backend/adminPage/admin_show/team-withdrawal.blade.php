@extends('layouts.backend')
@section('title', 'Review team withdrawal')
@section('content')
<style>
  .team-withdrawal-review .btn { min-height:44px; max-width:100%; white-space:normal; }
  .team-withdrawal-review .form-select { min-height:44px; }
</style>
<div class="container-xl py-3 team-withdrawal-review" style="max-width:760px">
  <h1 class="h4">Review withdrawal / refund</h1>
  <p class="text-muted">{{ $event->name }} · {{ $team->name }}</p>
  <div class="card"><div class="card-body">
    <h2 class="h5">{{ $slot->player?->full_name }} · Rank {{ $slot->rank }}</h2>
    <p>Original payer: <strong>{{ $review['order']->user->name }}</strong> · Order #{{ $review['order']->id }}</p>
    @if($review['refundAvailable'])
      <form method="GET" class="mb-3">
        <label for="withdrawal-destination" class="form-label">Refund destination</label>
        <select id="withdrawal-destination" name="destination" class="form-select mb-3" onchange="this.form.requestSubmit()">
          <option value="wallet" @selected($review['destination'] === 'wallet')>Original payer wallet</option>
          @if($review['order']->payfast_paid)<option value="payfast" @selected($review['destination'] === 'payfast')>Original PayFast payment source</option>@endif
        </select>
        <label for="withdrawal-fee-mode" class="form-label">Handling fee</label>
        <select id="withdrawal-fee-mode" name="fee_mode" class="form-select mb-3" onchange="this.form.requestSubmit()">
          <option value="current" @selected($review['feeMode'] === 'current')>Apply current withdrawal fee (R{{ number_format($review['currentFee'], 2) }})</option>
          <option value="full" @selected($review['feeMode'] === 'full')>Full refund · no handling fee</option>
        </select>
        <button class="btn btn-outline-primary btn-sm">Update refund preview</button>
      </form>
      <dl class="row"><dt class="col-7">Verified payment</dt><dd class="col-5 text-end">R{{ number_format($review['amounts']['gross'], 2) }}</dd>
        <dt class="col-7">Withdrawal fee</dt><dd class="col-5 text-end">R{{ number_format($review['amounts']['fee'], 2) }}</dd>
        <dt class="col-7">Total refund</dt><dd class="col-5 text-end fw-bold">R{{ number_format($review['amounts']['net'], 2) }}</dd>
        @if($review['destination'] === 'payfast')<dt class="col-7">To original PayFast source</dt><dd class="col-5 text-end">R{{ number_format($review['amounts']['payfastNet'], 2) }}</dd>
          <dt class="col-7">To original payer wallet</dt><dd class="col-5 text-end">R{{ number_format($review['amounts']['walletNet'], 2) }}</dd>@endif
      </dl>
      @if($review['destination'] === 'wallet')<p>The refund goes to the original payer’s wallet.</p>
      @else<p>PayFast must support returning this exact amount to its original payment source. Any original wallet-funded portion returns to the payer’s wallet after PayFast confirms. If confirmation is unavailable, the refund remains pending for review.</p>@endif
    @else
      <div class="alert alert-warning">The withdrawal deadline has passed. This will withdraw the player without a refund.</div>
    @endif
    <p>Confirmation frees this roster place and removes upcoming fixture participation. Completed matches must be resolved first. No reserve is automatically promoted.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('backend.team.withdrawal.store', [$event, $team, $slot]) }}">
      @csrf
      <input type="hidden" name="order_id" value="{{ $review['order']->id }}"><input type="hidden" name="fingerprint" value="{{ $review['fingerprint'] }}">
      <input type="hidden" name="destination" value="{{ $review['destination'] }}"><input type="hidden" name="fee_mode" value="{{ $review['feeMode'] }}">
      <label class="form-label w-100">Reason<textarea class="form-control" name="reason" required minlength="3" maxlength="1000" rows="3">{{ old('reason') }}</textarea></label>
      <label class="form-check my-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required><span class="form-check-label">{{ $review['refundAvailable'] ? 'Confirm withdrawal and the displayed refund to the selected original payer destination.' : 'Confirm withdrawal without a refund.' }}</span></label>
      <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger">{{ $review['refundAvailable'] ? ($review['destination'] === 'payfast' ? 'Withdraw and refund original PayFast source' : 'Withdraw and refund to wallet') : 'Withdraw without refund' }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $team->region_id]) }}">Cancel</a></div>
    </form>
  </div></div>
</div>
@endsection
