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
      use App\Models\CategoryEvent;
      use App\Models\Event;

      $orderId = (int) request('custom_int5');

      $order = $orderId
          ? RegistrationOrder::with('items.category_event.event', 'items.category_event.category', 'items.player', 'user.wallet')->find($orderId)
          : null;

      abort_if(!$order, 404);

      $total            = (float) $order->items->sum('item_price');
      $walletReserved   = (float) $order->wallet_reserved;
      $payfastDue       = (float) $order->payfast_amount_due;
      $walletBalance    = (float) ($order->user->wallet->balance ?? 0);
      
      // Get first item to extract event and category info
      $firstItem = $order->items->first();
      $categoryEvent = $firstItem?->category_event;
      $event = $categoryEvent?->event;
      $category = $categoryEvent?->category;
      $player = $firstItem?->player;
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

          <p>
            <strong>Registration Total:</strong>
            R {{ number_format($total, 2) }}
          </p>

          <hr>

          <p>
            Wallet Reserved for This Order:
            <strong class="text-success">
              - R {{ number_format($walletReserved, 2) }}
            </strong>
          </p>

          <p>
            Wallet Current Balance:
            <strong>
              R {{ number_format($walletBalance, 2) }}
            </strong>
          </p>

          <hr>

          <p>
            Remaining to Pay via PayFast:
            <strong class="{{ $payfastDue > 0 ? 'text-danger' : 'text-success' }}">
              R {{ number_format($payfastDue, 2) }}
            </strong>
          </p>

          @if($payfastDue <= 0)

            <form action="{{ route('registration.hybrid.complete', $orderId) }}"
                  method="POST">
              @csrf

              <button type="submit"
                      class="btn btn-success btn-lg w-100"
                      onclick="this.disabled=true; this.form.submit();">
                Confirm Wallet Payment
              </button>
            </form>

          @endif

          <small class="text-muted d-block mt-3">
            Wallet portion is already reserved for this order.
          </small>

        </div>
      </div>

    </div>

    {{-- ================= PAYFAST SECTION ================= --}}
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
            <strong>
              R {{ number_format($payfastDue, 2) }}
            </strong>
          </p>

          @php
            $returnUrl = route('frontend.registration.success', $orderId);
            $cancelUrl = route('registration.hybrid.cancel', $orderId);
            $notifyUrl = route('notify');
          @endphp

          @if($payfastDue > 0)

            <form action="{{ $payfast->url }}" method="post">

              <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
              <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">

              <input type="hidden" name="return_url" value="{{ $returnUrl }}">
              <input type="hidden" name="cancel_url" value="{{ $cancelUrl }}">
              <input type="hidden" name="notify_url" value="{{ $notifyUrl }}">

              {{-- 🔐 CRITICAL FIX --}}
              <input type="hidden" name="amount" value="{{ number_format($payfastDue, 2, '.', '') }}">

              <input type="hidden" name="item_name" value="{{ $event ? $event->name : 'Event Registration' }}">
              
              {{-- PayFast Custom Fields --}}
              <input type="hidden" name="custom_int1" value="{{ $categoryEvent ? $categoryEvent->id : '' }}">
              <input type="hidden" name="custom_int2" value="{{ $player ? $player->id : '' }}">
              <input type="hidden" name="custom_int3" value="{{ $event ? $event->id : '' }}">
              <input type="hidden" name="custom_int4" value="{{ auth()->id() }}">
              <input type="hidden" name="custom_int5" value="{{ $orderId }}">
              
              <input type="hidden" name="custom_str1" value="{{ $category ? $category->name : '' }}">
              <input type="hidden" name="custom_str2" value="{{ $player ? trim($player->name . ' ' . $player->surname) : '' }}">
              <input type="hidden" name="custom_str3" value="{{ $event ? $event->name : '' }}">
              <input type="hidden" name="custom_str4" value="{{ auth()->user()->name }}">
              
              <input type="hidden" name="custom_wallet_reserved" value="{{ $walletReserved }}">

              <button class="btn btn-danger btn-lg w-100">
                Pay Remaining with PayFast
              </button>

            </form>

          @else

            <div class="alert alert-success mb-0">
              No PayFast payment required.
            </div>

          @endif

        </div>
      </div>

    </div>

  </div>

  <a href="{{ url()->previous() }}" class="btn btn-warning mt-4">
    Back
  </a>

</div>

@endsection
