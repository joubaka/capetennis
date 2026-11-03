@extends('layouts/contentNavbarLayout')
@section('title', 'Continue to PayFast')
@section('content')
<div class="container-xxl container-p-y pp-journey" style="max-width:760px" data-plus-payfast-launch>
  @include('frontend.player-plus.styles')
  <section class="card card-body" aria-labelledby="plus-launch-heading">
    <h1 class="h4" id="plus-launch-heading">Taking you to PayFast</h1>
    <p>{{ $order->player->full_name }} · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} {{ $order->isRecurring() ? ($order->isMonthly() ? 'per month until cancelled' : 'per year until cancelled') : 'once off' }}.</p>
    <p role="status" data-plus-launch-status>Your agreed payment details are ready. If PayFast does not open automatically, use the button below.</p>
    {!! $form !!}
    <button type="submit" form="payfastForm" class="btn btn-primary align-self-start">Continue to PayFast · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }}</button>
    <p class="small mt-3 mb-0">Access starts only after verified payment. <a href="{{ route('player-plus.orders.show', $order) }}">View order P{{ $order->id }}</a></p>
  </section>
</div>
<script src="{{ asset('assets/js/player-plus-checkout.js') }}" defer></script>
@endsection
