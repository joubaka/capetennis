@extends('layouts/layoutMaster')

@section('title', 'Choose Refund Option')

@section('content')
<div class="container mt-4" style="max-width: 520px;">

  <div class="card shadow-sm">
    <div class="card-header">
      <h5 class="mb-0">
        Refund for {{ $registration->display_name }}
      </h5>
    </div>

    <div class="card-body">

      {{-- Amount summary --}}
      <div class="mb-3">
        <p class="mb-1">
          <strong>Paid:</strong> R{{ number_format($gross, 2) }}
        </p>
        <p class="mb-1 text-muted">
          <strong>Refund fee (10%):</strong> R{{ number_format($fee, 2) }}
        </p>
        <p class="fs-5 mt-2">
          You will receive:
          <span class="text-success fw-bold">
            R{{ number_format($net, 2) }}
          </span>
        </p>
      </div>

      <hr>

      {{-- WALLET REFUND --}}
      <form method="POST"
            action="{{ route('registrations.refund.request', $registration) }}"
            class="mb-3">
        @csrf
        <input type="hidden" name="method" value="wallet">

        <button type="submit"
                class="btn btn-success w-100"
                onclick="this.disabled=true; this.form.submit();">
          <i class="ti ti-wallet me-1"></i>
          Refund to Wallet (Instant)
        </button>

        <small class="text-muted d-block mt-1">
          Wallet refunds are processed immediately.
        </small>
      </form>

      {{-- BANK REFUND --}}
      <form method="POST"
            action="{{ route('registrations.refund.request', $registration) }}">
        @csrf
        <input type="hidden" name="method" value="bank">

        <button type="submit"
                class="btn btn-outline-primary w-100"
                onclick="this.disabled=true; this.form.submit();">
          <i class="ti ti-building-bank me-1"></i>
          Refund to Bank Account
        </button>

        <small class="text-muted d-block mt-1">
          Bank refunds are processed manually and may take a few days.
        </small>
      </form>

    </div>
  </div>

</div>
@endsection
