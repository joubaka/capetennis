@extends('layouts/layoutMaster')

@section('title', 'Choose Refund Option')

@section('content')

{{-- ================= TOASTS ================= --}}
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">

  {{-- SUCCESS --}}
  @if(session('success'))
    <div class="toast align-items-center text-bg-success border-0 show"
         role="alert">
      <div class="d-flex">
        <div class="toast-body">
          {{ session('success') }}
        </div>
        <button type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>
    </div>
  @endif

  {{-- ERRORS --}}
  @if($errors->any())
    <div class="toast align-items-center text-bg-danger border-0 show"
         role="alert">
      <div class="d-flex">
        <div class="toast-body">
          {{ $errors->first() }}
        </div>
        <button type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
      </div>
    </div>
  @endif

</div>
{{-- ================= END TOASTS ================= --}}


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

      {{-- BANK REFUND BUTTON --}}
      <button type="button"
              class="btn btn-outline-primary w-100"
              data-bs-toggle="modal"
              data-bs-target="#bankRefundModal">
        <i class="ti ti-building-bank me-1"></i>
        Refund to Bank Account
      </button>

      <small class="text-muted d-block mt-1">
        Bank refunds are processed manually and may take 2–3 business days.
      </small>

    </div>
  </div>
</div>


{{-- ================= BANK REFUND MODAL ================= --}}
<div class="modal fade" id="bankRefundModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form method="POST"
            action="{{ route('registrations.refund.request', $registration) }}">
        @csrf
        <input type="hidden" name="method" value="bank">

        <div class="modal-header">
          <h5 class="modal-title">Bank Refund Details</h5>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Account Holder Name</label>
            <input type="text"
                   name="account_name"
                   class="form-control"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Bank Name</label>
            <input type="text"
                   name="bank_name"
                   class="form-control"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Account Number</label>
            <input type="text"
                   name="account_number"
                   class="form-control"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Branch Code</label>
            <input type="text"
                   name="branch_code"
                   class="form-control"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Account Type</label>
            <select name="account_type"
                    class="form-select"
                    required>
              <option value="">Select</option>
              <option value="cheque">Cheque</option>
              <option value="savings">Savings</option>
              <option value="business">Business</option>
            </select>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Cancel
          </button>

          <button type="submit"
                  class="btn btn-primary"
                  onclick="this.disabled=true; this.form.submit();">
            Submit Refund Request
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

@endsection
