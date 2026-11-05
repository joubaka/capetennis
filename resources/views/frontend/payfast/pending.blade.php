@extends('layouts/layoutMaster')
@section('title', 'Payment status')
@section('hidePwaInstall', 'true')
@section('content')
@php
  $retryAt = $order->payfast_handed_off_at->copy()->addMinutes(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES);
  $secondsUntilCheck = (int) ceil(now()->diffInSeconds($retryAt, false));
@endphp
<div class="container-xxl container-p-y">
  <div class="card mx-auto" style="max-width: 720px">
    <div class="card-body">
      <h3>Payment pending</h3>
      <p>We’re waiting for a confirmed payment for registration <strong>#{{ $order->id }}</strong>.</p>
      @if ($secondsUntilCheck > 0)
        <p>We’ll check automatically five minutes after you started payment. If the checkout expires, we’ll take you back to the event so you can pay again.</p>
      @endif
      <p>If you were charged or still can’t pay after five minutes, contact Cape Tennis with your order number.</p>
      <div class="d-flex flex-wrap gap-2">
        <a id="check-payment-status" class="btn btn-primary" style="min-height:44px" href="{{ route('registration.checkout', $order) }}">Check payment status</a>
        <a class="btn btn-outline-secondary" style="min-height:44px" href="mailto:support@capetennis.co.za?subject=Registration%20order%20{{ $order->id }}%20payment%20assistance">Contact Cape Tennis</a>
      </div>
    </div>
  </div>
</div>
@if ($secondsUntilCheck > 0)
  <script>
    window.setTimeout(function () {
      window.location.replace(document.getElementById('check-payment-status').href);
    }, @json($secondsUntilCheck * 1000));
  </script>
@endif
@endsection
