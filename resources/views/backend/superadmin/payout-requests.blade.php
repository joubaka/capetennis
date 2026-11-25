@extends('layouts.backend')
@section('title', 'Event payout requests')
@section('content')
<h1 class="h4">Event payout requests</h1>
<p>Review only. No bank transfer, payout record, wallet change or notification is made here. Reconcile expenses, reimbursements, director profit shares, refunds and recipient identity separately.</p>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@forelse($requests as $item)
<article class="card mb-3"><div class="card-body">
<h2 class="h5">{{ $item->event?->name ?? 'Unavailable event' }}</h2><p>{{ ucfirst($item->kind) }} request: <strong>R{{ number_format($item->amount, 2) }}</strong> - {{ ucfirst($item->status) }}</p>
<p>Requested by {{ $item->requester?->name ?? 'Unavailable account' }} - {{ $item->created_at->format('d M Y H:i') }}</p><p>{{ $item->reason }}</p>
@if(isset($previews[$item->event_id]))
@php($current = $previews[$item->event_id])
<p>Current provisional registration funds: R{{ number_format($current['balance_cents'] / 100, 2) }}. Clothing excluded. Pending registration refunds: {{ $current['pending_refunds'] }}.</p>
@if($current != $item->snapshot)<div class="alert alert-warning">Balance or completion state changed since this request. Reject it with a note and ask for a fresh request.</div>@endif
<p><a href="{{ route('superadmin.finances.event', $item->event) }}">Review event finances</a></p>
<form method="POST" action="{{ route('superadmin.payout-requests.review', [$item->event, $item]) }}">@csrf
<input type="hidden" name="preview_token" value="{{ $service->token($current) }}">
<label class="form-label" for="review-note-{{ $item->id }}">Review note</label><textarea required maxlength="2000" class="form-control mb-3" name="review_note" id="review-note-{{ $item->id }}">{{ $item->review_note }}</textarea>
<div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-outline-primary" name="status" value="reviewed">Mark reviewed - no payment</button><button type="submit" class="btn btn-outline-danger" name="status" value="rejected">Reject request</button><button type="submit" class="btn btn-outline-secondary" name="status" value="closed">Close administratively</button></div>
<div class="form-text">Closing releases this request for a new submission. Record the reason or separate reconciliation reference in the note; closing does not claim a payment occurred.</div>
</form>@endif
</div></article>
@empty<div class="alert alert-info">No outstanding event payout requests.</div>@endforelse
{{ $requests->links() }}
@endsection
