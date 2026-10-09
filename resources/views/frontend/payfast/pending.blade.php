@extends('layouts/layoutMaster')
@section('title', 'Payment status')
@section('content')
<div class="container-xxl container-p-y">
  <div class="card mx-auto" style="max-width: 720px">
    <div class="card-body">
      <h3>We are waiting for your payment result</h3>
      <p>Registration order <strong>#{{ $order->id }}</strong> was sent to PayFast. This does not mean payment was completed. Your registration is confirmed only once we verify payment.</p>
      <p>If you completed payment, check the status below. If you closed PayFast, cancelled, or could not reach the payment page, contact Cape Tennis with your order number. We can check the attempt and cancel the checkout safely so you can start registration again.</p>
      <p>Please avoid making another payment for this registration while the result is unresolved.</p>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" style="min-height:44px" href="{{ route('registration.checkout', $order) }}">Check payment status</a>
        <a class="btn btn-outline-secondary" style="min-height:44px" href="mailto:support@capetennis.co.za?subject=Registration%20order%20{{ $order->id }}%20payment%20assistance">Contact Cape Tennis</a>
      </div>
    </div>
  </div>
</div>
@endsection
