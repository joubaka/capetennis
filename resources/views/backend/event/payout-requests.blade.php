@extends('layouts.backend')
@section('title', $event->name.' - Payout requests')
@section('content')
@include('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Event completion and payout requests'])
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Request an event payout</h2>
<p>Provisional remaining registration funds: <strong>R{{ number_format($snapshot['balance_cents'] / 100, 2) }}</strong>. Clothing funds are excluded.</p>
<p>This is an event-level request for review. It does not establish a personal entitlement or choose a recipient. Super User must reconcile expenses, reimbursements, profit shares and refunds before any payment is recorded separately.</p>
@if($snapshot['pending_refunds'])<div class="alert alert-warning">Resolve {{ $snapshot['pending_refunds'] }} pending registration refund(s) before requesting a payout.</div>@endif
@if(!$snapshot['completed'])
<form method="POST" action="{{ route('admin.events.payout-requests.complete', $event) }}" class="border rounded p-3 mb-3">
@csrf<h3 class="h6">Complete event</h3>
<p>Confirm the tournament has finished to unlock the final payout request. This does not publish results or change registration settings.</p>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required id="completion-confirm"><label class="form-check-label" for="completion-confirm">I confirm this event has finished.</label></div>
<button class="btn btn-outline-primary" type="submit">Complete event and continue</button>
</form>
@else<p class="badge bg-success">Event completion recorded</p>@endif
@if($outstanding)<div class="alert alert-info">An outstanding request is already with Super User. Reviewed means reviewed only; no payment is recorded by this workflow.</div>
@elseif(!$snapshot['pending_refunds'] && $snapshot['balance_cents'] > 0)
<form method="POST" action="{{ route('admin.events.payout-requests.store', $event) }}">
@csrf<input type="hidden" name="request_token" value="{{ old('request_token', (string) Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="preview_token" value="{{ $previewToken }}">
<div class="mb-3"><label class="form-label" for="request-kind">Payment request</label><select class="form-select" id="request-kind" name="kind"><option value="interim" @selected(old('kind', $snapshot['completed'] ? 'final' : 'interim') === 'interim')>Interim payment</option>@if($snapshot['completed'])<option value="final" @selected(old('kind', $snapshot['completed'] ? 'final' : 'interim') === 'final')>Final payment - full remaining balance</option>@endif</select></div>
<div class="mb-3" id="interim-amount-field"><label class="form-label" for="request-amount">Interim amount (rand)</label><input class="form-control" id="request-amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" aria-describedby="amount-help"><div class="form-text" id="amount-help">Enter an amount below the remaining balance for an interim request. Final requests use the full remaining balance calculated by Cape Tennis; leave this field blank.</div></div>
<div class="mb-3"><label class="form-label" for="request-reason">Reason / payment details for review</label><textarea required maxlength="2000" class="form-control" name="reason" id="request-reason">{{ old('reason') }}</textarea></div>
<p id="final-amount" class="fw-semibold">Final amount for review: R{{ number_format($snapshot['balance_cents'] / 100, 2) }}</p><button class="btn btn-primary" type="submit">Request payment</button>
</form>
@elseif(!$outstanding && !$snapshot['pending_refunds'])<p>No positive registration balance is available to request.</p>@endif
</div></div>
<div class="card"><div class="card-body"><h2 class="h5">Request history</h2>
@forelse($requests as $item)<article class="border rounded p-3 mb-3"><strong>{{ ucfirst($item->kind) }} - R{{ number_format($item->amount, 2) }}</strong><p class="mb-1">{{ ucfirst($item->status) }} - {{ $item->created_at->format('d M Y H:i') }}</p><p>{{ $item->reason }}</p>@if($item->review_note)<p class="mb-0">Review: {{ $item->review_note }}</p>@endif</article>@empty<p>No payout requests yet.</p>@endforelse
{{ $requests->links() }}</div></div>
@endsection
@section('page-script')
<script>
(function () {
  const kind = document.getElementById('request-kind');
  if (!kind) return;
  const amount = document.getElementById('request-amount');
  const sync = () => {
    const final = kind.value === 'final';
    document.getElementById('interim-amount-field').hidden = final;
    document.getElementById('final-amount').hidden = !final;
    amount.disabled = final;
    amount.required = !final;
  };
  kind.addEventListener('change', sync); sync();
})();
</script>
@endsection