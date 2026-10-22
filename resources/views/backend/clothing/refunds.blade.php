@extends('layouts.backend')
@section('title', 'Clothing refunds')
@section('content')
@php
  $refundAllowed = app(\App\Services\Clothing\ClothingRefundService::class)->canRefund($event, $order, auth()->user());
  $refundDeadline = app(\App\Services\Clothing\ClothingRefundService::class)->deadline($event, $order);
@endphp
<div class="card clothing-refund-workspace"><div class="card-body">
<h4>Clothing refunds — {{ $order->player?->full_name }}</h4>
<p>Order #{{ $order->id }} · {{ $event->name }} · Original payer: {{ $order->user?->name }}</p>
<a class="btn btn-outline-secondary mb-3" href="{{ route('backend.region.clothing.orders', array_filter(['region' => $order->team->region_id, 'event_id' => request()->boolean('all_events') && auth()->user()->hasRole('super-user') ? null : $event->id, 'sort' => in_array(request('sort'), ['rank', 'name', 'team', 'age_group', 'date_desc', 'date_asc'], true) ? request('sort') : 'rank', 'search' => request('search', ''), 'team' => request('team', '')], fn ($value) => $value !== null)) }}">Back to clothing orders</a>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if($refundAllowed)
@include('backend.clothing.partials.refund-selection')
@else<p>Only a super-user can refund after the clothing deadline or while no deadline is set.</p>@endif
<h5 class="mt-4">Refund history</h5>
@forelse($order->refunds->sortByDesc('id') as $refund)
<div class="border rounded p-3 mb-3">
<p><strong>R{{ $refund->refund_net }} · {{ ucfirst($refund->refund_method) }} · {{ ucfirst($refund->refund_status) }}</strong></p>
<p>{{ $refund->reason }}</p>
<p class="text-muted">Requested by {{ $refund->requested_by_name ?? $refund->requestedBy?->name ?? 'Not recorded' }} · {{ $refund->created_at?->format('d M Y H:i') }}<br>
Completed by {{ $refund->completed_by_name ?? $refund->completedBy?->name ?? 'Not recorded' }}{{ $refund->refunded_at ? ' · '.$refund->refunded_at->format('d M Y H:i') : '' }}</p>
@if($refund->refund_method === 'payfast' && $refund->refund_status === 'completed' && ! $refund->completed_by)
<p class="small text-muted">PayFast completion is recorded; the person who completed it was not recorded. The requester above is the recorded initiator.</p>
@endif
@include('backend.clothing.partials.historical-refund-source')
@if($refund->late_refund_note)
<div class="alert alert-warning"><strong>Late refund — clothing payout requires reconciliation</strong><p class="mb-1">Potential payout adjustment: R{{ $refund->refund_net }}. If already paid to the region or supplier, reconcile that payout separately.</p><p class="mb-1">{{ $refund->late_refund_note }}</p><p class="mb-0">Status: {{ $refund->reconciliation_status }}</p></div>
@if($refund->reconciliation_status === 'resolved')<p>Reconciled: {{ $refund->reconciliation_note }} · {{ $refund->reconciled_at?->format('d M Y H:i') }}</p>@elseif(auth()->user()->hasRole('super-user') && $refund->refund_status === 'completed')
<form method="POST" action="{{ route('backend.clothing.refunds.reconcile', [$event, $order, $refund]) }}">@csrf<label class="form-label" for="reconcile-{{ $refund->id }}">How was the clothing payout reconciled? Include the adjustment or evidence reference.</label><textarea required class="form-control mb-2" maxlength="1000" name="reconciliation_note" id="reconcile-{{ $refund->id }}"></textarea><button class="btn btn-outline-primary mb-2">Record reconciliation</button></form>
@endif
@endif
<ul>@foreach($refund->items as $line)<li>{{ $line->item?->item_name }} · {{ $line->quantity }} units · R{{ $line->amount }}</li>@endforeach</ul>
@if($refund->refund_status === 'pending' && $providerAttempts->has($refund->id))
  @if($providerAttempts->get($refund->id)->status === 'confirmed' && $refundAllowed)
    <p>PayFast confirmed this refund. Finish recording the confirmed result locally.</p>
    <form method="POST" action="{{ route('backend.clothing.refunds.retry', [$event, $order, $refund]) }}">@csrf @include('backend.clothing.partials.late-note', ['noteKey' => 'retry-late-'.$refund->id])<button class="btn btn-outline-primary">Finish recording confirmed refund</button></form>
  @elseif(in_array($providerAttempts->get($refund->id)->status, ['not_dispatched', 'reconciled_ready'], true))
    @if($providerAttempts->get($refund->id)->status === 'reconciled_ready')
    <p>PayFast history was reconciled and showed no refund. This saved refund is ready to retry.</p>
    @else
    <p>No refund was submitted to PayFast. This amount remains reserved for the saved refund.</p>
    @endif
    @if($refundAllowed)
    <form method="POST" action="{{ route('backend.clothing.refunds.retry', [$event, $order, $refund]) }}">
      @csrf
      @include('backend.clothing.partials.refund-bank-details', ['bankFieldPrefix' => 'retry-bank-'.$refund->id])
      @include('backend.clothing.partials.late-note', ['noteKey' => 'retry-late-'.$refund->id])
      <button class="btn btn-outline-danger">Retry saved PayFast refund of R{{ $refund->refund_net }}</button>
    </form>
    @endif
  @else
    <p class="text-warning">PayFast submission needs reconciliation. This amount stays reserved. Do not submit another payment or refund for it.</p>
  @endif
@endif
@if($refund->external_reference)<p>Payment reference: {{ $refund->external_reference }}</p>@endif
@if($refundAllowed && $refund->refund_status === 'pending' && ! $providerAttempts->has($refund->id) && in_array($refund->refund_method, ['bank', 'payfast'], true))
<p>Complete the refund to the original payer in {{ $refund->refund_method === 'payfast' ? 'PayFast' : 'your bank' }} first. Recording it here does not move money.</p>
<form method="POST" action="{{ route('backend.clothing.refunds.complete', [$event, $order, $refund]) }}">@csrf
<label class="form-label" for="reference-{{ $refund->id }}">Payment reference (no banking details)</label><input required class="form-control mb-2" name="reference" id="reference-{{ $refund->id }}" minlength="3" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9._:/\-]{2,119}">
<label class="d-block mb-2"><input type="checkbox" name="paid_to_original_payer" value="1" required> I confirm this refund was paid to the original payer.</label>
@include('backend.clothing.partials.late-note', ['noteKey' => 'manual-late-'.$refund->id])
<button class="btn btn-outline-danger">Record completed refund</button></form>
@endif
</div>
@empty<p>No refunds yet.</p>@endforelse
</div></div>
@include('backend.clothing.partials.refund-script')
@include('backend.clothing.partials.refund-styles')
@endsection
