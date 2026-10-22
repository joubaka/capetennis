@extends('layouts.backend')
@section('title', 'Review wallet refund')
@section('content')
<div class="card"><div class="card-body">
<h4>Review refund to wallet</h4>
<p>Order #{{ $order->id }} · {{ $event->name }}</p>
<p>Credit <strong>R{{ $refund->refund_net }}</strong> to <strong>{{ $refund->payer?->name }}</strong>, the original payer’s wallet.</p>
<p>This uses the same saved refund and items. PayFast has not paid this refund. For reconciled attempts, PayFast is checked again when you confirm. Confirming credits the wallet and closes the PayFast retry.</p>
<ul>@foreach($refund->items as $line)<li>{{ $line->item?->item_name }} · {{ $line->quantity }} units · R{{ $line->amount }}</li>@endforeach</ul>
<form method="POST" action="{{ route('backend.clothing.refunds.wallet.confirm', [$event, $order, $refund]) }}">@csrf
<input type="hidden" name="review_token" value="{{ $reviewToken }}">
<label class="d-block mb-3"><input required type="checkbox" name="confirmed" value="1"> I confirm this refund should credit the original payer’s wallet.</label>
<button class="btn btn-primary">Confirm wallet refund of R{{ $refund->refund_net }}</button>
<a class="btn btn-outline-secondary" href="{{ route('backend.clothing.refunds.show', [$event, $order]) }}">Cancel</a>
</form></div></div>
@endsection
