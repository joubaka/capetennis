@extends('layouts/layoutMaster')

@section('title', 'Checkout')

@section('content')
<div class="col-md-6 col-lg-6 mb-3">
  <div class="card">
    <div class="card-header">
      Payment for
    </div>
    <div class="card-body">
      <h5 class="card-title">{{ $payfast->item_name }}</h5>
      <h6 class="card-subtitle text-muted">{{ $payfast->custom_str4 }}</h6>

      {{-- 🔹 Payment breakdown --}}
    <ul class="list-group list-group-flush my-3">
  <li class="list-group-item d-flex justify-content-between">
    <span>Entry Fee</span>
    <span>R{{ number_format($event->entryFee, 2) }}</span>
  </li>
    
  @if(!empty($regionFee) && $regionFee > 0)
    <li class="list-group-item d-flex justify-content-between">
      <span>Provincial Region Fee</span>
      <span>R{{ number_format($regionFee, 2) }}</span>
    </li>
  @endif

  <li class="list-group-item d-flex justify-content-between fw-bold">
    <span>Total</span>
    <span>R{{ number_format($payfast->amount, 2) }}</span>
  </li>
</ul>


      <!-- PayFast Form -->
      <div class="border rounded p-4">
        <form action="{{ $payfast->url }}" method="post">
          <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
          <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">

          <input type="hidden" name="return_url" value="https://www.capetennis.co.za/public/events/success/{{ $event->id }}?email={{ $user->email }}">
          <input type="hidden" name="cancel_url" value="{{ $payfast->cancel_url }}">
          <input type="hidden" name="notify_url" value="{{ $payfast->notify_url_team }}">

          {{-- amounts --}}
          <input type="hidden" id="amount" name="amount" value="{{ number_format($payfast->amount, 2, '.', '') }}">
          <input type="hidden" id="item_name" name="item_name" value="{{ $payfast->item_name }}">

          <!-- Category -->
          <input type="hidden" name="custom_int1" value="{{ $payfast->custom_int1 }}">
          <input type="hidden" name="custom_str1" value="{{ $payfast->custom_str1 }}">

          <!-- Player -->
          <input type="hidden" name="custom_int2" value="{{ $payfast->custom_int2 }}">
          <input type="hidden" name="custom_str2" value="{{ $payfast->custom_str2 }}">

          <!-- Event -->
          <input type="hidden" name="custom_int3" value="{{ $payfast->custom_int3 }}">
          <input type="hidden" name="custom_str3" value="{{ $payfast->custom_str3 }}">

          <!-- User -->
          <input type="hidden" name="custom_int4" value="{{ $payfast->custom_int4 }}">
          <input type="hidden" name="custom_str4" value="{{ $payfast->custom_str4 }}">

          <!-- Order -->
          <input type="hidden" name="custom_int5" value="{{ $payfast->custom_int5 }}">
          <input type="hidden" name="custom_str5" value="Order">

          <button class="btn btn-danger btn-lg w-100">Pay now with Payfast</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
