@extends('layouts/layoutMaster')

@section('title', 'Checkout')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/rateyo/rateyo.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/rateyo/rateyo.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/wizard-ex-checkout.css') }}?v={{ config('app.asset_version') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
<style>
  #wizard-checkout .playerRow ~ .playerRow .removePlayer {
    display: inline-flex !important;
  }
  #wizard-checkout .playerRow:first-child .removePlayer {
    display: none !important;
  }

  @media (max-width: 767.98px) {
    body { overflow-x: hidden; }
    .content-wrapper > .container-xxl > h4 {
      font-size: 1.15rem;
      margin-bottom: .35rem !important;
      padding-top: .75rem !important;
    }
    .content-wrapper > .container-xxl > p {
      font-size: .85rem;
      margin-bottom: .85rem;
    }

    #wizard-checkout {
      width: calc(100% + 1.7rem);
      margin-left: -.85rem;
      margin-right: -.85rem;
      overflow: hidden;
      margin-top: 0 !important;
      background: #fff;
    }

    #wizard-checkout .bs-stepper-header {
      width: 100%;
      min-height: 72px;
      padding: .7rem .5rem !important;
      overflow-x: auto;
      justify-content: flex-start;
      flex-direction: row !important;
      gap: .15rem;
      scrollbar-width: none;
      border-bottom: 1px solid rgba(67, 89, 113, .12) !important;
    }
    #wizard-checkout .bs-stepper-header::-webkit-scrollbar { display: none; }

    #wizard-checkout .bs-stepper-header .step-trigger {
      min-width: 84px;
      padding: .25rem .35rem;
      flex-direction: column !important;
      gap: .1rem !important;
    }
    #wizard-checkout .bs-stepper-header .bs-stepper-icon {
      width: 28px;
      height: 28px;
      margin-bottom: .15rem;
    }
    #wizard-checkout .bs-stepper-header .bs-stepper-icon svg {
      width: 24px;
      height: 24px;
    }
    #wizard-checkout .bs-stepper-header .bs-stepper-label {
      max-width: 82px;
      font-size: .68rem;
      line-height: 1.15;
      white-space: normal;
      text-align: center;
    }

    #wizard-checkout.wizard-icons .bs-stepper-header .step {
      padding: 0 !important;
      text-align: center;
    }

    #wizard-checkout .bs-stepper-header .step {
      flex: 0 0 auto;
    }
    #wizard-checkout .bs-stepper-header .line {
      flex: 0 0 18px;
      min-width: 18px;
    }
    #wizard-checkout .bs-stepper-header .line i { font-size: .85rem; }

    #wizard-checkout .bs-stepper-content {
      padding: 1rem .85rem 1.5rem !important;
      border-top: 0 !important;
    }
    #wizard-checkout .bs-stepper-content > form > .content > .row {
      --bs-gutter-x: .85rem;
      --bs-gutter-y: 0;
    }
    #wizard-checkout .card,
    #wizard-checkout .border.rounded {
      border-radius: .65rem !important;
    }
    #wizard-checkout .form-select-lg,
    #wizard-checkout .form-control {
      min-height: 48px;
      font-size: 1rem;
    }
    #wizard-checkout .btn {
      min-height: 48px;
    }
    #wizard-checkout .checkout-player-actions {
      align-items: stretch;
    }
    #wizard-checkout .checkout-player-actions .btn {
      flex: 1 1 0;
      min-width: 0;
      white-space: normal;
      line-height: 1.2;
    }
    #wizard-checkout .playerRow ~ .playerRow .removePlayer {
      display: inline-flex !important;
    }
    #wizard-checkout .playerRow:first-child .removePlayer {
      display: none !important;
    }
    #wizard-checkout .playerRow .card-body,
    #wizard-checkout .border.rounded.p-4 {
      padding: 1rem !important;
    }
    #wizard-checkout .col-xl-4 {
      margin-top: .75rem;
    }
    #wizard-checkout .col-xl-8 + .col-xl-4 {
      position: relative;
    }
    #wizard-checkout .alert {
      font-size: .85rem;
      line-height: 1.4;
    }
  }
</style>
@endsection

@section('page-script')
<script>
  var APP_URL = "{{ url('/') }}";
</script>
<script src="{{ asset('assets/js/wizard-ex-checkout.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('assets/js/forms-selects.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('assets/js/select2-search-addon.js') }}?v={{ config('app.asset_version') }}"></script>
@endsection

@section('content')

<h4 class="fw-bold py-3 mb-4">
  <span class="text-muted fw-light">Checkout</span>
</h4>

<p>Sign up for {{ $event->name }}</p>

@if($errors->any())
  <div class="alert alert-danger" role="alert">
    {{ $errors->first() }}
  </div>
@endif

<div id="wizard-checkout" class="bs-stepper wizard-icons wizard-icons-example mt-2">

  {{-- STEP HEADER --}}
  <div class="bs-stepper-header m-auto border-0 py-5">
    <div class="step" data-target="#checkout-address">
      <button type="button" class="step-trigger">
        <span class="bs-stepper-icon">
          <svg viewBox="0 0 58 54">
            <use xlink:href="{{ asset('assets/svg/icons/wizard-checkout-cart.svg#wizardCart') }}"></use>
          </svg>
        </span>
        <span class="bs-stepper-label">Registration</span>
      </button>
    </div>

    <div class="line">
      <i class="ti ti-chevron-right"></i>
    </div>

    <div class="step" data-target="#checkout-confirm-details">
      <button type="button" class="step-trigger">
        <span class="bs-stepper-icon">
          <svg viewBox="0 0 54 54">
            <use xlink:href="{{ asset('assets/svg/icons/wizard-checkout-address.svg#wizardCheckoutAddress') }}"></use>
          </svg>
        </span>
        <span class="bs-stepper-label">Confirm Details</span>
      </button>
    </div>

    <div class="line">
      <i class="ti ti-chevron-right"></i>
    </div>

    <div class="step" data-target="#checkout-cart">
      <button type="button" class="step-trigger">
        <span class="bs-stepper-icon">
          <svg viewBox="0 0 54 54">
            <use xlink:href="{{ asset('assets/svg/icons/wizard-checkout-address.svg#wizardCheckoutAddress') }}"></use>
          </svg>
        </span>
        <span class="bs-stepper-label">Cart</span>
      </button>
    </div>

    @if($requireCodeOfConduct || $requireTerms)
    <div class="line">
      <i class="ti ti-chevron-right"></i>
    </div>

    <div class="step" data-target="#checkout-terms">
      <button type="button" class="step-trigger">
        <span class="bs-stepper-icon">
          <svg viewBox="0 0 54 54">
            <use xlink:href="{{ asset('assets/svg/icons/wizard-checkout-address.svg#wizardCheckoutAddress') }}"></use>
          </svg>
        </span>
        <span class="bs-stepper-label">Terms & Conditions</span>
      </button>
    </div>
    @endif
  </div>

  {{-- CONTENT --}}
  <div class="bs-stepper-content border-top">

    <form id="wizard-checkout-form" action="{{ route('pay.now.payfast') }}" method="POST">
      @csrf

      <input type="hidden" id="eventPrice" value="{{ $event->entryFee }}">
      <input type="hidden" id="myevent" value="{{ $event->name }}">

      {{-- =========================
           STEP 1 – REGISTRATION
      ========================== --}}
      <div id="checkout-address" class="content">
        <div class="row">

          <div class="col-xl-8">

            <p>Please select players to enter</p>

            <div class="card mb-2 border border-warning">
              <div class="card-body">
                Search player name below. If the profile does not exist, an option to add a new player will appear.
              </div>
            </div>

            <div class="row mb-3 playerRow">
              <div class="card numPlayers p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <span class="fw-semibold playerNr">Player 1</span>
                  <button type="button" class="btn btn-outline-danger btn-sm removePlayer d-none" aria-label="Remove Player 1">
                    <i class="ti ti-trash me-1"></i>Remove
                  </button>
                </div>
                <div class="row">

                  {{-- PLAYER --}}
                  <div class="col-md-5 mb-4">
                    <label class="form-label">Player</label>
                    <select name="player[]" class="select2player form-select form-select-lg">
                      <option value="0">Please select</option>
                    </select>
                  </div>

                  {{-- PARENT --}}
                  @if($parentEvent)
                    <div class="col-md-5 mb-4 parentInput">
                      <label class="form-label">Parent</label>
                      <input name="parent[]" type="text" class="form-control parent-name" placeholder="John Doe">
                    </div>
                  @endif

                  {{-- CATEGORY --}}
                  <div class="col-md-5 mb-4">
                    <label class="form-label">Category</label>
                    <select name="category[]" class="select2Basic select2category form-select form-select-lg">
                      <option value="0">Please select</option>
                      @foreach($eventCats as $value)
                        <option value="{{ $value->id }}"
                                data-price="{{ $value->entry_fee }}"
                                data-name="{{ $value->category->name }}">
                          {{ $value->category->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>

                </div>
              </div>
            </div>

            <div id="tool-placeholder"></div>

            <div class="d-flex flex-wrap gap-2 mb-4 checkout-player-actions">
              <button type="button" class="btn btn-outline-secondary" id="addPlayer">
                Register another player into event
              </button>
              <button type="button" class="btn btn-primary btn-next">
                Continue
              </button>
            </div>

          </div>

          {{-- RIGHT SUMMARY --}}
          <div class="col-xl-4">
            <div class="border rounded p-4 mb-3">
              <h6>Players</h6>

              <div class="playersCart"></div>

              <hr>
              <dl class="row mb-0">
                <dt class="col-6">Total</dt>
                <dd class="col-6 fw-semibold text-end orderTotal">R0.00</dd>
              </dl>
            </div>

          </div>

        </div>
      </div>

      {{-- =========================
           STEP 2 – CONFIRM PLAYER DETAILS
      ========================== --}}
      <div id="checkout-confirm-details" class="content">
        <div class="row">

          <div class="col-xl-8">
            <h5>Confirm Player Details</h5>
            <p class="text-muted">Please review and confirm each player's details are correct before continuing.</p>

            <div id="confirmPlayerCards">
              <div class="alert alert-info">Select players in the previous step first.</div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="border rounded p-4 mb-3">
              <h6>Players</h6>
              <div class="playersCart"></div>
              <hr>
              <dl class="row mb-0">
                <dt class="col-6">Total</dt>
                <dd class="col-6 fw-semibold text-end orderTotal">R0.00</dd>
              </dl>
            </div>

            <div class="d-grid">
              <button type="button" class="btn btn-primary btn-next" id="goToCart" disabled>
                <i class="ti ti-arrow-right me-1"></i> Continue to Cart
              </button>
              <small class="text-muted text-center mt-2">All player details must be confirmed to proceed.</small>
            </div>
          </div>

        </div>
      </div>

      {{-- =========================
           STEP 3 – CART
      ========================== --}}
      <div id="checkout-cart" class="content">
        <div class="row">

          <div class="col-xl-8">
            <h5>My Registrations</h5>
            <ul class="list-group mb-3" id="myUl"></ul>
          </div>

          <div class="col-xl-4">
            <div class="border rounded p-4 mb-3">
              <h6>Price Details</h6>

              <dl class="row mb-0">
                <dt class="col-6">Payment Total</dt>
                <dd class="col-6 text-end orderTotal"></dd>
              </dl>

              <hr>

              <dl class="row mb-0">
                <dt class="col-6">Total</dt>
                <dd class="col-6 fw-semibold text-end orderTotal"></dd>
              </dl>
            </div>

            <div class="d-grid">
              @if($requireCodeOfConduct || $requireTerms)
                <button type="button" class="btn btn-primary btn-next" id="goToTerms">
                  Continue
                </button>
              @else
                <button type="submit" class="btn btn-success btn-lg" id="payment">
                  <i class="ti ti-lock me-1"></i> Confirm Order
                </button>
              @endif
            </div>
          </div>

        </div>
      </div>

      {{-- =========================
           STEP 4 – TERMS & CONDITIONS
      ========================== --}}
      @if($requireCodeOfConduct || $requireTerms)
      <div id="checkout-terms" class="content">
        <div class="row">

          <div class="col-xl-8">
            <h5>Terms & Conditions</h5>
            <p class="text-muted">Please review and accept before confirming your order.</p>

            @if($requireCodeOfConduct)
              {{-- Code of Conduct Section --}}
              @if($agreement)
                <div class="card mb-4">
                  <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="ti ti-file-certificate me-2"></i>Code of Conduct – {{ $agreement->title }} (v{{ $agreement->version }})</h6>
                  </div>
                  <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    {!! $agreement->content !!}
                  </div>
                </div>
              @else
                <div class="alert alert-info">No active Code of Conduct at this time.</div>
              @endif

              {{-- Per-player CoC status --}}
              <div class="card mb-4">
                <div class="card-header bg-light">
                  <h6 class="mb-0"><i class="ti ti-users me-2"></i>Code of Conduct Acceptance per Player</h6>
                </div>
                <div class="card-body">
                  <div id="playerCocStatus">
                    <p class="text-muted">Loading player status...</p>
                  </div>
                </div>
              </div>
            @endif

            @if($requireTerms)
              {{-- General Terms Acceptance --}}
              <div class="card mb-4">
                <div class="card-body">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="acceptTerms">
                    <label class="form-check-label" for="acceptTerms">
                      I confirm that I have read and accept the
                      @if($requireCodeOfConduct)
                        <strong>Terms & Conditions</strong> and the <strong>Code of Conduct</strong>
                      @else
                        <strong>Terms & Conditions</strong>
                      @endif
                      for this event.
                    </label>
                  </div>
                </div>
              </div>
            @endif
          </div>

          <div class="col-xl-4">
            <div class="border rounded p-4 mb-3">
              <h6>Order Summary</h6>

              <ul class="list-group mb-3" id="termsOrderList"></ul>

              <hr>
              <dl class="row mb-0">
                <dt class="col-6">Total</dt>
                <dd class="col-6 fw-semibold text-end orderTotal">R0.00</dd>
              </dl>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-success btn-lg" id="payment" disabled>
                <i class="ti ti-lock me-1"></i> Confirm Order
              </button>
              <small class="text-muted text-center mt-2">You must accept the terms above to proceed.</small>
            </div>
          </div>

        </div>
      </div>
      @endif

      {{-- =========================
           PAYFAST FIELDS
      ========================== --}}
      <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
      <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">
      <input type="hidden" name="return_url" value="https://www.capetennis.co.za/public/events/success/{{ $event->id }}?email={{ $user->email }}">
      <input type="hidden" name="cancel_url" value="{{ $payfast->cancel_url }}">
      <input type="hidden" name="notify_url" value="{{ $payfast->notify_url }}">
      <input type="hidden" name="amount" id="amount">
      <input type="hidden" name="item_name" value="Registration">

      <input type="hidden" name="custom_int3" value="{{ $event->id }}">
      <input type="hidden" name="custom_str3" value="{{ $event->name }}">
      <input type="hidden" name="custom_int4" value="{{ $user->id }}">
      <input type="hidden" name="custom_str4" value="{{ $user->name }}">
      <input type="hidden" name="custom_int5" value="{{ $orderId }}">
      <input type="hidden" name="custom_str5" value="Order">
      <input type="hidden" name="terms_accepted" id="termsAcceptedField" value="{{ ($requireCodeOfConduct || $requireTerms) ? '0' : '1' }}">

    </form>
  </div>
</div>

@include('_partials/_modals/modal-add-new-address')
@include('_partials/_modals/modal-add-player')

@endsection
