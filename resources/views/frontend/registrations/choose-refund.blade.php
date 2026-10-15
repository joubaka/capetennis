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

      @if($pendingBank)
        <div class="alert alert-warning">
          Your bank refund is pending administrator review. This does not confirm that money has been returned.
          You can correct the bank details below. Saving them will not retry the payment.
        </div>
      @endif

      {{-- Amount summary --}}
      <div class="mb-3">
        <p class="mb-1">
          <strong>Total Paid:</strong> R{{ number_format($gross, 2) }}
        </p>
        @if(($walletPaid ?? 0) > 0)
          <p class="mb-1 text-muted small">
            <i class="ti ti-wallet me-1"></i> Wallet: R{{ number_format($walletPaid, 2) }}
            @if(($payfastGross ?? 0) > 0)
              &nbsp;|&nbsp;
              <i class="ti ti-credit-card me-1"></i> PayFast: R{{ number_format($payfastGross, 2) }}
            @endif
          </p>
        @endif
        <p class="mb-1 text-muted">
          <strong>Withdrawal fee (10%):</strong> R{{ number_format($fee, 2) }}
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
      @if(!$registration->isRefundPending() && !$registration->isRefundCompleted())
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
      @endif

      @if(($pendingBank || ($payfastGross ?? 0) > 0) && !$registration->isRefundCompleted())
      {{-- BANK REFUND BUTTON --}}
      <button type="button"
              class="btn btn-outline-primary w-100"
              data-bs-toggle="modal"
              data-bs-target="#bankRefundModal">
        <i class="ti ti-building-bank me-1"></i>
        {{ $pendingBank ? 'Correct Bank Details' : 'Refund PayFast Portion' }}
      </button>

      <small class="text-muted d-block mt-1">
        Bank details must match the account used for the original payment. If the automatic refund fails, an administrator must review the request.
      </small>
      @endif

    </div>
  </div>
</div>


{{-- ================= BANK REFUND MODAL ================= --}}
@if(($pendingBank || ($payfastGross ?? 0) > 0) && !$registration->isRefundCompleted())
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

          <p class="text-muted">Use the original payer's bank account. PayFast may reject details that do not match the original transaction.</p>

          <div class="mb-3">
            <label class="form-label">Account Holder Name</label>
            <input type="text"
                   name="account_name"
                   value="{{ old('account_name', $registration->refund_account_name) }}"
                   class="form-control"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Bank Name</label>
            <select name="bank_name" class="form-select" required>
              <option value="">Select bank</option>
              @foreach($bankNames as $value => $label)
                <option value="{{ $value }}" @selected(old('bank_name', $registration->refund_bank_name) === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Account Number</label>
            <input type="text"
                   name="account_number"
                   value="{{ old('account_number', $registration->refund_account_number) }}"
                   class="form-control"
                   inputmode="numeric"
                   maxlength="12"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Branch Code</label>
            <input type="text"
                   name="branch_code"
                   value="{{ old('branch_code', $registration->refund_branch_code) }}"
                   class="form-control"
                   inputmode="numeric"
                   maxlength="6"
                   required>
          </div>

          <div class="mb-3">
            <label class="form-label">Account Type</label>
            <select name="account_type"
                    class="form-select"
                    required>
              <option value="">Select</option>
              <option value="current" @selected(old('account_type', $registration->refund_account_type) === 'current')>Current / Cheque</option>
              <option value="savings" @selected(old('account_type', $registration->refund_account_type) === 'savings')>Savings</option>
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
            {{ $pendingBank ? 'Save Corrected Bank Details' : 'Submit Refund Request' }}
          </button>
        </div>

      </form>

    </div>
  </div>
</div>
@endif

@endsection
