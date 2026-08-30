@extends('layouts/layoutMaster')

@section('title', 'Checkout')

@section('content')

@section('page-style')
<style>
  .registration-checkout { max-width: 1180px; }
  /* The horizontal header is fixed, so keep this page content below both navbar rows. */
  .layout-navbar-fixed.layout-horizontal .registration-checkout { margin-top: 4.75rem; }
  .registration-checkout .checkout-intro { max-width: 820px; }
  .registration-checkout .checkout-intro h3 { font-size: 1.75rem; }
  .registration-checkout .checkout-intro > p { font-size: .9rem; }
  .registration-checkout .checkout-eyebrow { letter-spacing: .08em; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
  .registration-checkout .checkout-card { height: 100%; border: 1px solid rgba(67, 89, 113, .12); border-radius: .8rem; overflow: hidden; }
  .registration-checkout.container-p-y { padding-top: 2rem !important; padding-bottom: 2rem !important; }
  .registration-checkout .checkout-card .card-header { padding: 1.15rem 1.25rem; border-bottom: 0; }
  .registration-checkout .checkout-card .card-body { padding: 1rem 1.1rem; font-size: .9rem; }
  .registration-checkout .checkout-card .card-header h5 { font-size: 1.05rem; }
  .registration-checkout .checkout-card .card-header .small { opacity: .86; }
  .registration-checkout .checkout-card .mb-3 { margin-bottom: .7rem !important; }
  .registration-checkout .checkout-card .mb-4 { margin-bottom: .85rem !important; }
  .registration-checkout .checkout-card hr { margin: .7rem 0; }
  .registration-checkout .amount { font-size: 1.25rem; letter-spacing: -.02em; margin-bottom: 0; }
  .registration-checkout .checkout-step { display: inline-flex; align-items: center; gap: .45rem; font-size: .8rem; font-weight: 600; }
  .registration-checkout .checkout-step + .checkout-step::before { content: '›'; margin: 0 .25rem; opacity: .55; }
  .registration-checkout .payfast-submit { min-height: 48px; font-size: .98rem; font-weight: 700; }
  .registration-checkout .checkout-note { display: flex; gap: .5rem; align-items: flex-start; }
  .registration-checkout .checkout-summary { border: 1px solid rgba(67, 89, 113, .12); border-radius: .8rem; background: #fff; box-shadow: 0 .35rem 1.25rem rgba(67, 89, 113, .1); }
  .registration-checkout .checkout-summary .summary-item { padding: 1rem 1.2rem; }
  .registration-checkout .checkout-summary .summary-item + .summary-item { border-left: 1px solid rgba(67, 89, 113, .12); }
  .registration-checkout .choice-label { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
  .registration-checkout .choice-card { position: relative; }
  .registration-checkout .choice-card.wallet-choice { border-top: 3px solid #7367f0; }
  .registration-checkout .choice-card.payfast-choice { border-top: 3px solid #ea5455; }
  .registration-checkout .choice-card .card-body { min-height: 220px; }
  .registration-checkout .checkout-card h4 { font-size: 1.25rem; }
  .registration-checkout .checkout-card .btn { padding-top: .55rem; padding-bottom: .55rem; }
  .registration-checkout .checkout-actions { margin-top: auto; }
  @media (max-width: 767.98px) {
    .layout-navbar-fixed.layout-horizontal .registration-checkout { margin-top: 0; }
    .registration-checkout.container-p-y { padding-top: 1.25rem !important; }
    .registration-checkout .checkout-summary .summary-item { padding: .8rem; }
    .registration-checkout .checkout-summary .summary-item + .summary-item { border-left: 0; border-top: 1px solid rgba(67, 89, 113, .12); }
    .registration-checkout .checkout-card .card-body { padding: .9rem; }
    .registration-checkout .amount { font-size: 1.35rem; }
  }
</style>
@endsection

<div class="container-xxl flex-grow-1 container-p-y registration-checkout">

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

  <div class="checkout-intro mb-4">
    <div class="checkout-eyebrow text-primary mb-2">Cape Tennis registration</div>
    <div class="checkout-step text-primary"><i class="ti ti-clipboard-check"></i> Registration ready</div>
    <span class="checkout-step text-muted"><i class="ti ti-lock"></i> Secure payment</span>
    <h3 class="mb-1 mt-1">Complete your registration</h3>
    <p class="text-muted mb-0">Choose how you’d like to pay. You can use your wallet, PayFast, or combine both.</p>
  </div>

  @php
      use App\Models\RegistrationOrder;
      use App\Models\CategoryEvent;
      use App\Models\Event;

      $orderId = (int) ($order->id ?? request('custom_int5'));

      $order = isset($order) ? $order : ($orderId
          ? RegistrationOrder::with('items.category_event.event', 'items.category_event.category', 'items.player', 'user.wallet')->find($orderId)
          : null);

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

  <div class="checkout-summary row g-0 mb-4" aria-label="Payment summary">
    <div class="summary-item col-md-4">
      <div class="text-muted small"><i class="ti ti-receipt me-1"></i>Registration total</div>
      <div class="h5 mb-0 mt-1">R {{ number_format($total, 2) }}</div>
    </div>
    <div class="summary-item col-md-4">
      <div class="text-muted small"><i class="ti ti-wallet me-1"></i>Wallet available</div>
      <div class="h5 mb-0 mt-1 text-primary">R {{ number_format($walletBalance, 2) }}</div>
    </div>
    <div class="summary-item col-md-4">
      <div class="text-muted small"><i class="ti ti-credit-card me-1"></i>PayFast due now</div>
      <div class="h5 mb-0 mt-1 {{ $payfastDue > 0 ? 'text-danger' : 'text-success' }}" id="payfastDueSummary">R {{ number_format($payfastDue, 2) }}</div>
    </div>
  </div>

  <div class="row align-items-stretch">

    {{-- ================= WALLET SECTION ================= --}}
    <div class="col-xl-6 mb-4">

      <div class="card checkout-card choice-card wallet-choice shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">
            <i class="ti ti-wallet me-1"></i>
            Use your wallet
          </h5>
          <div class="small mt-1">Apply available funds to reduce what you pay online.</div>
        </div>

        <div class="card-body d-flex flex-column">

          <div class="mb-3">
            <p class="text-muted mb-1">Registration Total:</p>
            <h5 class="text-primary amount">
              R {{ number_format($total, 2) }}
            </h5>
          </div>

          <hr>

          <div class="mb-3">
            <p class="text-muted mb-1">Wallet Balance Available:</p>
            <h5 class="amount">
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
            <h4 class="amount {{ $payfastDue > 0 ? 'text-danger' : 'text-success' }}" id="payfastDueDisplay" aria-live="polite">
              R {{ number_format($payfastDue, 2) }}
            </h4>
          </div>

          @if($walletBalance > 0 && $walletReserved <= 0 && $payfastDue > 0)
            <button type="button" class="btn btn-primary w-100 mb-3" id="applyWalletBtn">
              <i class="ti ti-wallet me-1"></i> Apply Wallet Balance (R {{ number_format(min($walletBalance, $total), 2) }})
            </button>
            <form action="{{ route('registration.payfast-only', $orderId) }}" method="POST" class="mb-3" data-audit-order-id="{{ $orderId }}">
              @csrf
              <button type="submit" data-audit-action="payment.payfast-only-selected" data-audit-order-id="{{ $orderId }}" class="btn btn-outline-danger w-100">
                <i class="ti ti-credit-card me-1"></i> Pay full amount with PayFast
              </button>
            </form>
            <small class="text-muted d-block mb-3">Use this if you do not want to use your wallet. PayFast payments below R20 are not supported.</small>
          @endif

          @if($walletReserved > 0 && $payfastDue > 0 && $payfastDue < 20)
            <div class="alert alert-warning small" role="alert">
              PayFast requires at least R20. Your wallet would leave only R{{ number_format($payfastDue, 2) }} to pay, so please pay the full registration amount by PayFast.
            </div>
            <form action="{{ route('registration.payfast-only', $orderId) }}" method="POST" class="mb-3" data-audit-order-id="{{ $orderId }}">
              @csrf
              <button type="submit" data-audit-action="payment.payfast-only-selected" data-audit-order-id="{{ $orderId }}" class="btn btn-outline-danger w-100">
                <i class="ti ti-credit-card me-1"></i> Pay full amount with PayFast
              </button>
            </form>
          @endif

          @if($walletReserved > 0 && $payfastDue >= 20)
            <form action="{{ route('registration.payfast-only', $orderId) }}" method="POST" class="mb-3" data-audit-order-id="{{ $orderId }}">
              @csrf
              <button type="submit" data-audit-action="payment.payfast-only-selected" data-audit-order-id="{{ $orderId }}" class="btn btn-outline-danger w-100">
                <i class="ti ti-credit-card me-1"></i> Pay full amount with PayFast instead
              </button>
            </form>
            <small class="text-muted d-block mb-3">This releases the wallet reservation and restarts payment for the full registration amount.</small>
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

      <div class="card checkout-card choice-card payfast-choice shadow-sm">
        <div class="card-header bg-danger text-white">
          <h5 class="mb-0">
            <i class="ti ti-credit-card me-1"></i>
            Pay online
          </h5>
          <div class="small mt-1">Secure checkout powered by PayFast.</div>
        </div>

        <div class="card-body d-flex flex-column">

          <div class="mb-3">
            <p class="text-muted mb-1">Amount Due via PayFast:</p>
            <h4 class="text-danger amount">
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

          @if($payfastDue > 0 && filled($payfast->id) && filled($payfast->key) && !($walletReserved > 0 && $payfastDue < 20))

            <form action="{{ $payfast->url }}" method="post" data-audit-order-id="{{ $orderId }}">

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

              <button type="submit" data-audit-action="payment.payfast-submit" data-audit-order-id="{{ $orderId }}" class="btn btn-danger btn-lg w-100 payfast-submit" onclick="this.disabled=true; this.setAttribute('aria-busy', 'true'); this.form.submit(); return false;">
                Pay R {{ number_format($payfastDue, 2) }} with PayFast
              </button>

            </form>

          @elseif($payfastDue > 0)

            <div class="alert alert-danger mb-0" role="alert">
              <strong>Online payment is temporarily unavailable.</strong>
              <div class="small mt-1">Your registration has not been marked as paid. Please contact support before trying again.</div>
            </div>

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

  <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary mt-1">
    <i class="ti ti-arrow-left me-1"></i>
    Cancel and go back
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

          // Regenerate the amount and PayFast signature without replaying the
          // original registration POST (which would create another order).
          window.location.assign(@json(route('registration.checkout', ['order' => $orderId])));
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
