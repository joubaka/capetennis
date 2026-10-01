@extends('layouts/layoutMaster')
@section('title','Regional team participation payment')
@section('content')
<div class="container py-4"><div class="card card-body mx-auto" style="max-width:760px"><h4>{{ $event->name }} — team participation</h4><p>{{ $participation->player->full_name }} — Team {{ $participation->slot->tier }}</p>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<p>Participation fee: <strong>R{{ number_format($order->total_amount,2) }}</strong></p>
@if($participation->isPaid())<div class="alert alert-success">Payment confirmed.</div><form method="post" action="{{ route('interprovincial-trials.participation.withdraw',[$event,$participation]) }}">@csrf<button class="btn btn-outline-warning">Withdraw participation</button></form>
@elseif($payfast)<p>Your checkout is prepared for secure payment.</p>{!! $payfast->getForm() !!}<button type="submit" form="payfastForm" class="btn btn-primary">Continue to PayFast</button>
@elseif($order->payfast_handed_off_at)<div class="alert alert-info">PayFast payment is awaiting verification. Your checkout cannot be changed while it is resolving.</div>
@else
<form method="post" action="{{ route('interprovincial-trials.participation.payfast',[$event,$participation]) }}">@csrf<button class="btn btn-primary mb-3">Pay online</button></form>
@if(filled($programme?->bank_details))<h5>Pay by EFT</h5><pre style="white-space:pre-wrap">{{ $programme->bank_details }}</pre><p>Upload proof after making payment. An admin verifies it before payment is confirmed.</p><form method="post" enctype="multipart/form-data" action="{{ route('interprovincial-trials.participation.proof',[$event,$participation]) }}">@csrf<label class="form-label">Payment proof (PDF/JPEG/PNG, maximum 5 MB)</label><input class="form-control mb-2" type="file" name="proof" accept="application/pdf,image/jpeg,image/png" required><button class="btn btn-outline-primary">Upload proof</button></form>@endif
<form method="post" action="{{ route('interprovincial-trials.participation.cancel',[$event,$participation]) }}" class="mt-3">@csrf<button class="btn btn-outline-secondary">Cancel unpaid checkout</button></form>
@endif
@if($proofs->isNotEmpty())<h5 class="mt-3">Submitted proofs</h5>@foreach($proofs as $proof)<p><a href="{{ route('interprovincial-trials.participation.download',[$event,$proof]) }}">Proof {{ $proof->created_at }}</a> — {{ $proof->status }}</p>@endforeach@endif
<a href="{{ route('events.show',$event) }}" class="mt-3">Back to event</a></div></div>
@endsection
