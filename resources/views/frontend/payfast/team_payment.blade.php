@extends('layouts/layoutMaster')

@section('title', 'Checkout')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10 mb-3">
      <div class="card shadow-sm">
        <div class="card-header">
          Payment for
        </div>
        <div class="card-body">
          <h5 class="card-title">{{ $payfast->item_name }}</h5>
          <h6 class="card-subtitle text-muted">{{ $payfast->custom_str4 }}</h6>

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
              <span>R{{ number_format($total, 2) }}</span>
            </li>
          </ul>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <p class="text-muted mb-1">Wallet Balance Available:</p>
                <h5 class="mb-0">R{{ number_format($walletBalance, 2) }}</h5>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <p class="text-muted mb-1">PayFast Amount Due:</p>
                <h5 class="mb-0 {{ $payfastDue > 0 ? 'text-danger' : 'text-success' }}" id="payfastDueDisplay">R{{ number_format($payfastDue, 2) }}</h5>
              </div>
            </div>
          </div>

          @if($walletReserved > 0)
            <div class="alert alert-success mb-3" role="alert">
              <i class="ti ti-circle-check me-2"></i>
              <strong>Wallet Applied:</strong> R{{ number_format($walletReserved, 2) }}
            </div>
          @endif

          @if($walletBalance > 0 && $walletReserved <= 0 && $payfastDue > 0)
            <form id="teamHybridForm" action="{{ route('team.hybrid.pay') }}" method="post" class="mb-3">
              @csrf
              <input type="hidden" name="custom_int5" value="{{ $order->id }}">
              <input type="hidden" name="wallet_applied" value="{{ number_format(min($walletBalance, $total), 2, '.', '') }}">
              <input type="hidden" name="remaining_amount" value="{{ number_format(max(0, $total - min($walletBalance, $total)), 2, '.', '') }}">
              <input type="hidden" name="type" value="team">
              <button type="submit" class="btn btn-primary w-100" id="applyWalletBtn">
                <i class="ti ti-wallet me-1"></i> Apply Wallet Balance
              </button>
            </form>
          @endif

          @php
            $returnUrl = route('event.success', ['id' => $event->id]);
            $cancelUrl = route('team.checkout', ['order' => $order->id]);
            $notifyUrl = route('notify.team');
          @endphp

          @if($payfastDue > 0)
            <div class="border rounded p-4">
              <form action="{{ $payfast->url }}" method="post">
                <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
                <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">
                <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                <input type="hidden" name="cancel_url" value="{{ $cancelUrl }}">
                <input type="hidden" name="notify_url" value="{{ $notifyUrl }}">
                <input type="hidden" name="amount" value="{{ number_format($payfastDue, 2, '.', '') }}">
                <input type="hidden" name="item_name" value="{{ $payfast->item_name }}">

                @if($payfast->custom_int1)
                  <input type="hidden" name="custom_int1" value="{{ $payfast->custom_int1 }}">
                @endif
                @if($payfast->custom_str1)
                  <input type="hidden" name="custom_str1" value="{{ $payfast->custom_str1 }}">
                @endif
                @if($payfast->custom_int2)
                  <input type="hidden" name="custom_int2" value="{{ $payfast->custom_int2 }}">
                @endif
                @if($payfast->custom_str2)
                  <input type="hidden" name="custom_str2" value="{{ $payfast->custom_str2 }}">
                @endif
                @if($payfast->custom_int3)
                  <input type="hidden" name="custom_int3" value="{{ $payfast->custom_int3 }}">
                @endif
                @if($payfast->custom_str3)
                  <input type="hidden" name="custom_str3" value="{{ $payfast->custom_str3 }}">
                @endif
                @if($payfast->custom_int4)
                  <input type="hidden" name="custom_int4" value="{{ $payfast->custom_int4 }}">
                @endif
                @if($payfast->custom_str4)
                  <input type="hidden" name="custom_str4" value="{{ $payfast->custom_str4 }}">
                @endif
                @if($payfast->custom_int5)
                  <input type="hidden" name="custom_int5" value="{{ $payfast->custom_int5 }}">
                @endif
                <input type="hidden" name="custom_str5" value="TeamOrder">

                @php
                  $formFields = array_filter([
                    'merchant_id'  => $payfast->id,
                    'merchant_key' => $payfast->key,
                    'return_url'   => $returnUrl,
                    'cancel_url'   => $cancelUrl,
                    'notify_url'   => $notifyUrl,
                    'amount'       => number_format($payfastDue, 2, '.', ''),
                    'item_name'    => $payfast->item_name,
                    'custom_int1'  => $payfast->custom_int1 ? (string)$payfast->custom_int1 : null,
                    'custom_str1'  => $payfast->custom_str1 ?: null,
                    'custom_int2'  => $payfast->custom_int2 ? (string)$payfast->custom_int2 : null,
                    'custom_str2'  => $payfast->custom_str2 ?: null,
                    'custom_int3'  => $payfast->custom_int3 ? (string)$payfast->custom_int3 : null,
                    'custom_str3'  => $payfast->custom_str3 ?: null,
                    'custom_int4'  => $payfast->custom_int4 ? (string)$payfast->custom_int4 : null,
                    'custom_str4'  => $payfast->custom_str4 ?: null,
                    'custom_int5'  => $payfast->custom_int5 ? (string)$payfast->custom_int5 : null,
                    'custom_str5'  => 'TeamOrder',
                  ], fn($v) => $v !== null && $v !== '');
                @endphp
                <input type="hidden" name="signature" value="{{ $payfast->generateFormSignature($formFields) }}">

                <button class="btn btn-danger btn-lg w-100">Pay now with Payfast</button>
              </form>
            </div>
          @else
            <div class="alert alert-success mb-3">
              <i class="ti ti-circle-check me-2"></i>
              No additional payment required. Your wallet covers the full amount.
            </div>
            <form action="{{ route('team.hybrid.complete', ['order' => $order->id]) }}" method="post">
              @csrf
              <button type="submit" class="btn btn-success btn-lg w-100">Confirm Wallet Payment</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
