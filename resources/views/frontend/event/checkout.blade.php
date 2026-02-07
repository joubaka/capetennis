@extends('layouts/layoutMaster')

@section('title', 'Checkout')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/rateyo/rateyo.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/rateyo/rateyo.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
@endsection

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/wizard-ex-checkout.css') }}" />
@endsection

@section('page-script')
<script src="{{ asset('assets/js/wizard-ex-checkout.js') }}"></script>
<script src="{{ asset('assets/js/forms-selects.js') }}"></script>
<script src="{{ asset('assets/js/select2-search-addon.js') }}"></script>
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
                <div class="row">

                  {{-- PLAYER --}}
                  <div class="col-md-5 mb-4">
                    <label class="form-label playerNr">Player 1</label>
                    <select name="player[]" class="select2Basic select2player form-select form-select-lg">
                      <option value="0">Please select</option>
                      @foreach($players as $player)
                        <option value="{{ $player->id }}" data-name="{{ $player->name }} {{ $player->surname }}">
                          {{ $player->name }} {{ $player->surname }}
                        </option>
                      @endforeach
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

            <div class="mb-4">
              <button type="button" class="btn btn-primary btn-sm" id="addPlayer">
                Register another player into event
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

            <div class="d-grid">
              <button type="button" class="btn btn-primary btn-next">Continue</button>
            </div>
          </div>

        </div>
      </div>

      {{-- =========================
           STEP 2 – CART
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
              <button type="submit" class="btn btn-primary" id="payment">
                Confirm Order
              </button>
            </div>
          </div>

        </div>
      </div>

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

    </form>
  </div>
</div>

@include('_partials/_modals/modal-add-new-address')
@include('_partials/_modals/modal-add-player')

@endsection
