@extends('layouts.backend')
@section('title', 'Clothing refunds')
@section('content')
<div class="card"><div class="card-body">
<h4>Clothing refunds — {{ $order->player?->name }}</h4>
<p>Order #{{ $order->id }} · {{ $event->name }} · Original payer: {{ $order->user?->name }}</p>
<a class="btn btn-outline-secondary mb-3" href="{{ route('backend.region.clothing.orders', ['region' => $order->team->region_id, 'event_id' => $event->id]) }}">Back to clothing orders</a>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="row g-3">@foreach($order->items as $item)<div class="col-md-6">@include('backend.clothing.partials.refund-item', ['formKey' => 'order-'.$item->id])</div>@endforeach</div>
<h5 class="mt-4">Refund history</h5>
@forelse($order->refunds->sortByDesc('id') as $refund)
<div class="border rounded p-3 mb-3">
<p><strong>R{{ $refund->refund_net }} · {{ ucfirst($refund->refund_method) }} · {{ ucfirst($refund->refund_status) }}</strong></p>
<p>{{ $refund->reason }}</p>
<ul>@foreach($refund->items as $line)<li>{{ $line->item?->item_name }} · {{ $line->quantity }} units · R{{ $line->amount }}</li>@endforeach</ul>
@if($refund->refund_status === 'pending' && $providerAttempts->has($refund->id))
  @if($providerAttempts->get($refund->id)->status === 'confirmed')
    <p>PayFast confirmed this refund. Finish recording the confirmed result locally.</p>
    <form method="POST" action="{{ route('backend.clothing.refunds.retry', [$event, $order, $refund]) }}">@csrf<button class="btn btn-outline-primary">Finish recording confirmed refund</button></form>
  @else
    <p class="text-warning">PayFast submission needs reconciliation. This amount stays reserved. Do not submit another payment or refund for it.</p>
  @endif
@endif
@if($refund->external_reference)<p>Payment reference: {{ $refund->external_reference }}</p>@endif
@if($refund->refund_status === 'pending' && ! $providerAttempts->has($refund->id) && in_array($refund->refund_method, ['bank', 'payfast'], true))
<p>Complete the refund to the original payer in {{ $refund->refund_method === 'payfast' ? 'PayFast' : 'your bank' }} first. Recording it here does not move money.</p>
<form method="POST" action="{{ route('backend.clothing.refunds.complete', [$event, $order, $refund]) }}">@csrf
<label class="form-label" for="reference-{{ $refund->id }}">Payment reference (no banking details)</label><input required class="form-control mb-2" name="reference" id="reference-{{ $refund->id }}" minlength="3" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9._:/\-]{2,119}">
<label class="d-block mb-2"><input type="checkbox" name="paid_to_original_payer" value="1" required> I confirm this refund was paid to the original payer.</label>
<button class="btn btn-outline-danger">Record completed refund</button></form>
@endif
</div>
@empty<p>No refunds yet.</p>@endforelse
</div></div>
@include('backend.clothing.partials.refund-script')
@endsection
