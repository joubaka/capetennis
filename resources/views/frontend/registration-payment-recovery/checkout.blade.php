@extends('layouts/layoutMaster')

@section('title', 'Registration payment')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="card mx-auto" style="max-width: 640px">
    <div class="card-body p-4">
      <h3>Complete your registration payment</h3>
      <p class="text-muted">Your registration remains reserved. Pay the exact outstanding amount securely through PayFast.</p>
      <div class="alert alert-info"><strong>Amount due:</strong> R {{ number_format((float) $recovery->amount_due, 2) }}</div>
      @if($order->payfast_handed_off_at)
        <div class="alert alert-info">Your payment has been sent to PayFast and is awaiting confirmation.</div>
      @else
        <a class="btn btn-danger btn-lg w-100" href="{{ $paymentUrl }}">Continue to PayFast</a>
      @endif
    </div>
  </div>
</div>
@endsection
