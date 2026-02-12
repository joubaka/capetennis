@extends('layouts/layoutMaster')

@section('title', 'Checkout')

@section('content')

<div class="container-xxl flex-grow-1 container-p-y">

  {{-- ================= TOASTS ================= --}}
  <div class="toast-container position-fixed bottom-0 end-0 p-3">

    @if(session('success'))
      <div class="toast align-items-center text-bg-success border-0 show mb-2">
        <div class="d-flex">
          <div class="toast-body">
            {{ session('success') }}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto"
                  data-bs-dismiss="toast"></button>
        </div>
      </div>
    @endif

    @if($errors->any())
      <div class="toast align-items-center text-bg-danger border-0 show mb-2">
        <div class="d-flex">
          <div class="toast-body">
            {{ $errors->first() }}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto"
                  data-bs-dismiss="toast"></button>
        </div>
      </div>
    @endif

  </div>

  <h3 class="mb-4">Checkout</h3>

  @php
      use App\Models\RegistrationOrder;

      $wallet = Auth::user()->wallet ?? null;
      $walletBalance = (float) ($wallet->balance ?? 0);

      $orderId = (int) request('custom_int5');
      $order = $orderId
          ? RegistrationOrder::with('items')->find($orderId)
          : null;

      $total = $order
          ? (float) $order->items->sum('item_price')
          : 0;

      $walletApplied = min($walletBalance, $total);
      $remaining = round($total - $walletApplied, 2);
      $walletAfter = round($walletBalance - $walletApplied, 2);
  @endphp

  <div class="row">

    {{-- ================= WALLET SECTION ================= --}}
    <div class="col-xl-6 mb-4">

      <div class="card border-primary shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">
            <i class="ti ti-wallet me-1"></i>
            Cape Tennis Wallet
          </h5>
        </div>

        <div class="card-body">

          <p><strong>Registration Total:</strong>
            R {{ number_format($total, 2) }}</p>

          <hr>

          <p>Current Balance:
            <strong>R {{ number_format($walletBalance, 2) }}</strong></p>

          @if($walletBalance > 0)

            <p class="text-success">
              Wallet Applied:
              <strong>- R {{ number_format($walletApplied, 2) }}</strong>
            </p>

            <p>
              Balance After Payment:
              <strong>R {{ number_format($walletAfter, 2) }}</strong>
            </p>

            <hr>

            <p>
              Remaining to Pay:
              <strong class="{{ $remaining > 0 ? 'text-danger' : 'text-success' }}">
                R {{ number_format($remaining, 2) }}
              </strong>
            </p>

            {{-- ================= FULL WALLET ================= --}}
            @if($remaining <= 0)

              <form action="{{ route('registration.hybrid.complete', $orderId) }}"
                    method="POST">
                @csrf

                <button type="submit"
                        class="btn btn-success btn-lg w-100"
                        onclick="this.disabled=true; this.form.submit();">
                  Confirm Wallet Payment
                </button>
              </form>

            {{-- ================= PARTIAL WALLET ================= --}}
            @else

              <form action="{{ route('registration.hybrid.pay') }}"
                    method="POST">
                @csrf

                <input type="hidden" name="custom_int5" value="{{ $orderId }}">
                <input type="hidden" name="wallet_applied" value="{{ $walletApplied }}">
                <input type="hidden" name="remaining_amount" value="{{ $remaining }}">

                <button class="btn btn-success btn-lg w-100">
                  Apply Wallet & Continue to PayFast
                </button>
              </form>

            @endif

            <small class="text-muted d-block mt-3">
              Wallet payments avoid PayFast gateway fees.
            </small>

          @else
            <p class="text-muted">
              You currently have no wallet funds available.
            </p>
          @endif

        </div>
      </div>

    </div>

    {{-- ================= PAYFAST ONLY ================= --}}
    <div class="col-xl-6 mb-4">

      <div class="card border-danger shadow-sm">
        <div class="card-header bg-danger text-white">
          <h5 class="mb-0">
            <i class="ti ti-credit-card me-1"></i>
            Pay Online (PayFast)
          </h5>
        </div>

        <div class="card-body">

          <p>
            Amount to Pay via PayFast:
            <strong>R {{ number_format($total, 2) }}</strong>
          </p>

          @php
            $returnUrl = route('frontend.registration.success', $orderId);
            $cancelUrl = route('registration.hybrid.cancel', $orderId);
            $notifyUrl = route('notify');
          @endphp

          <form action="{{ $payfast->url }}" method="post">

            <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
            <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">

            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
            <input type="hidden" name="cancel_url" value="{{ $cancelUrl }}">
            <input type="hidden" name="notify_url" value="{{ $notifyUrl }}">

            <input type="hidden" name="amount" value="{{ $total }}">
            <input type="hidden" name="item_name" value="Event Registration">
            <input type="hidden" name="custom_int5" value="{{ $orderId }}">
            <input type="hidden" name="custom_wallet_applied" value="0">

            <button class="btn btn-danger btn-lg w-100">
              Pay with PayFast Only
            </button>

          </form>

        </div>
      </div>

    </div>

  </div>

  <a href="{{ url()->previous() }}" class="btn btn-warning mt-4">
    Back
  </a>

</div>

@endsection
