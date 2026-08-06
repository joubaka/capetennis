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

      $total            = round((float) $order->items->sum('item_price'), 2);
      $walletReserved   = round((float) $order->wallet_reserved, 2);
      $payfastDue       = round((float) $order->payfast_amount_due, 2);
      $walletBalance    = round((float) ($order->user->wallet->balance ?? 0), 2);

      // Validation: Ensure amounts are consistent
      $calculatedPayFastDue = round($total - $walletReserved, 2);
      if (abs($payfastDue - $calculatedPayFastDue) > 0.01) {
          Log::warning('CHECKOUT AMOUNT MISMATCH', [
              'order_id' => $orderId,
              'total' => $total,
              'wallet_reserved' => $walletReserved,
              'payfast_due_stored' => $payfastDue,
              'payfast_due_calculated' => $calculatedPayFastDue,
          ]);
          $payfastDue = $calculatedPayFastDue;
      }

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

          <div class="mb-3">
            <p class="text-muted mb-1">Registration Total:</p>
            <h5 class="text-primary">
              R {{ number_format($total, 2) }}
            </h5>
          </div>

          <hr>

          <div class="mb-3">
            <p class="text-muted mb-1">Wallet Balance Available:</p>
            <h5>
              R {{ number_format($walletBalance, 2) }}
            </h5>
          </div>

          @if($walletReserved > 0)
            <div class="alert alert-success mb-3" role="alert">
              <i class="ti ti-circle-check me-2"></i>
              <strong>Wallet Applied:</strong> R {{ number_format($walletReserved, 2) }}
              <span id="walletAppliedDisplay"></span>
            </div>
          @endif

          <hr>

          <div class="mb-4">
            <p class="text-muted mb-1">PayFast Payment Due:</p>
            <h4 class="{{ $payfastDue > 0 ? 'text-danger' : 'text-success' }}" id="payfastDueDisplay">
              R {{ number_format($payfastDue, 2) }}
            </h4>
          </div>

          @if($walletBalance > 0 && $walletReserved <= 0 && $payfastDue > 0)
            <button type="button" class="btn btn-primary w-100 mb-3" id="applyWalletBtn">
              <i class="ti ti-wallet me-1"></i> Apply Wallet Balance (R {{ number_format(min($walletBalance, $total), 2) }})
            </button>
          @endif

          @if($payfastDue <= 0)

            <form action="{{ route('registration.hybrid.complete', $orderId) }}"
                  method="POST">
              @csrf

              <button type="submit"
                      class="btn btn-success btn-lg w-100"
                      onclick="this.disabled=true; this.form.submit();">
                <i class="ti ti-circle-check me-1"></i>
                Confirm Wallet Payment
              </button>
            </form>

          @endif

          <small class="text-muted d-block mt-3">
            @if($walletReserved > 0)
              <i class="ti ti-info-circle me-1"></i>
              Wallet portion is reserved for this order.
            @else
              <i class="ti ti-info-circle me-1"></i>
              You can optionally apply your wallet balance to reduce the PayFast amount.
            @endif
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

          <div class="mb-3">
            <p class="text-muted mb-1">Amount Due via PayFast:</p>
            <h4 class="text-danger">
              R {{ number_format($payfastDue, 2) }}
            </h4>
          </div>

          {{-- Show breakdown if wallet was applied --}}
          @if($walletReserved > 0)
            <div class="alert alert-info mb-3" role="alert">
              <small>
                <strong>Breakdown:</strong><br>
                Total Registration: R {{ number_format($total, 2) }}<br>
                − Wallet Applied: R {{ number_format($walletReserved, 2) }}<br>
                <strong>= PayFast Amount: R {{ number_format($payfastDue, 2) }}</strong>
              </small>
            </div>
          @endif

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
              @if($categoryEvent)
                <input type="hidden" name="custom_int1" value="{{ $categoryEvent->id }}">
              @endif
              @if($player)
                <input type="hidden" name="custom_int2" value="{{ $player->id }}">
              @endif
              @if($event)
                <input type="hidden" name="custom_int3" value="{{ $event->id }}">
              @endif
              @if(auth()->check())
                <input type="hidden" name="custom_int4" value="{{ auth()->id() }}">
              @endif
              @if($orderId)
                <input type="hidden" name="custom_int5" value="{{ $orderId }}">
              @endif

              @if($category)
                <input type="hidden" name="custom_str1" value="{{ $category->name }}">
              @endif
              @if($player)
                <input type="hidden" name="custom_str2" value="{{ trim($player->name . ' ' . $player->surname) }}">
              @endif
              @if($event)
                <input type="hidden" name="custom_str3" value="{{ $event->name }}">
              @endif
              @if(auth()->check())
                <input type="hidden" name="custom_str4" value="{{ trim(auth()->user()->name) }}">
              @endif

              {{-- NOTE: custom_wallet_reserved is NOT sent to PayFast (not a PayFast field) --}}

              @php
                $formFields = array_filter([
                  'merchant_id'  => $payfast->id,
                  'merchant_key' => $payfast->key,
                  'return_url'   => $returnUrl,
                  'cancel_url'   => $cancelUrl,
                  'notify_url'   => $notifyUrl,
                  'amount'       => number_format($payfastDue, 2, '.', ''),
                  'item_name'    => $event ? $event->name : 'Event Registration',
                  'custom_int1'  => $categoryEvent ? (string)$categoryEvent->id : null,
                  'custom_int2'  => $player ? (string)$player->id : null,
                  'custom_int3'  => $event ? (string)$event->id : null,
                  'custom_int4'  => (string)auth()->id(),
                  'custom_int5'  => (string)$orderId,
                  'custom_str1'  => $category ? $category->name : null,
                  'custom_str2'  => $player ? trim($player->name . ' ' . $player->surname) : null,
                  'custom_str3'  => $event ? $event->name : null,
                  'custom_str4'  => trim(auth()->user()->name),
                ], fn($v) => $v !== null && $v !== '');
              @endphp
              <input type="hidden" name="signature" value="{{ $payfast->generateFormSignature($formFields) }}">

              <button class="btn btn-danger btn-lg w-100" onclick="this.disabled=true; this.form.submit();">
                Pay R {{ number_format($payfastDue, 2) }} with PayFast
              </button>

            </form>

          @else

            <div class="alert alert-success mb-0">
              <i class="ti ti-circle-check me-2"></i>
              No additional payment required. Your wallet covers the full amount.
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

@section('page-script')
<script>
$(function () {
  $('#applyWalletBtn').on('click', function () {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Applying...');

    $.ajax({
      url: @json(route('registration.hybrid.apply-wallet')),
      type: 'POST',
      xhrFields: {
        withCredentials: true  // 🔐 Ensure session cookies are sent with AJAX request
      },
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        order_id: {{ $orderId }}
      },
      success: function (res) {
        if (res.success) {
          if (res.wallet_covers_all) {
            var $form = $('<form>', {
              method: 'POST',
              action: @json(route('registration.hybrid.complete', ['orderId' => $orderId]))
            });

            $form.append($('<input>', {
              type: 'hidden',
              name: '_token',
              value: $('meta[name="csrf-token"]').attr('content')
            }));

            $('body').append($form);
            $form.trigger('submit');
            return;
          }

          // Important: signature must be regenerated server-side after amount changes.
          // Reload checkout so amount + signature always match.
          window.location.reload();
        }
      },
      error: function (xhr) {
        $btn.prop('disabled', false).html('<i class="ti ti-wallet me-1"></i> Apply Wallet Balance');
        var errorMsg = 'Failed to apply wallet. Please try again.';

        if (xhr.status === 403) {
          errorMsg = 'Session expired. Please refresh the page and login again.';
        } else if (xhr.status === 401) {
          errorMsg = 'Please login to continue.';
          setTimeout(function() { window.location.href = APP_URL + '/login'; }, 2000);
        } else if (xhr.responseJSON && xhr.responseJSON.error) {
          errorMsg = xhr.responseJSON.error;
        }

        alert(errorMsg);
      }
    });
  });
});
</script>
@endsection
