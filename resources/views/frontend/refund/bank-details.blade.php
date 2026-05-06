@extends('layouts/layoutMaster')

@section('title', 'Submit Bank Details for Refund')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">

      <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="ti ti-building-bank me-2"></i>Bank Details for Refund</h5>
        </div>
        <div class="card-body">

          <div class="alert alert-info mb-4">
            <strong>Refund:</strong> R{{ number_format($registration->refund_net, 2) }}
            for <strong>{{ $event?->name ?? 'Event' }}</strong><br>
            <small class="text-muted">Registration #{{ $registration->id }}</small>
          </div>

          @if($alreadyFilled)
            <div class="alert alert-success mb-3">
              <i class="ti ti-check me-1"></i> You have already submitted bank details. You can update them below before the refund is processed.
            </div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ request()->fullUrl() }}">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-semibold">Account Holder Name <span class="text-danger">*</span></label>
              <input type="text" name="refund_account_name" class="form-control @error('refund_account_name') is-invalid @enderror"
                     value="{{ old('refund_account_name', $registration->refund_account_name) }}"
                     placeholder="Name exactly as on bank account" required>
              @error('refund_account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Bank <span class="text-danger">*</span></label>
              <select name="refund_bank_name" class="form-select @error('refund_bank_name') is-invalid @enderror" required>
                <option value="">— Select bank —</option>
                @foreach($bankNames as $value => $label)
                  <option value="{{ $value }}" {{ old('refund_bank_name', $registration->refund_bank_name) === $value ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                @endforeach
              </select>
              @error('refund_bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
              <input type="text" name="refund_account_number" class="form-control @error('refund_account_number') is-invalid @enderror"
                     value="{{ old('refund_account_number', $registration->refund_account_number) }}"
                     placeholder="e.g. 1234567890" required maxlength="20">
              @error('refund_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Branch Code <span class="text-danger">*</span></label>
              <input type="text" name="refund_branch_code" class="form-control @error('refund_branch_code') is-invalid @enderror"
                     value="{{ old('refund_branch_code', $registration->refund_branch_code) }}"
                     placeholder="e.g. 632005" required maxlength="10" inputmode="numeric">
              @error('refund_branch_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Account Type <span class="text-danger">*</span></label>
              <select name="refund_account_type" class="form-select @error('refund_account_type') is-invalid @enderror" required>
                <option value="">— Select type —</option>
                <option value="current"  {{ old('refund_account_type', $registration->refund_account_type) === 'current'  ? 'selected' : '' }}>Current</option>
                <option value="savings"  {{ old('refund_account_type', $registration->refund_account_type) === 'savings'  ? 'selected' : '' }}>Savings</option>
              </select>
              @error('refund_account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="ti ti-check me-1"></i> Submit Bank Details
            </button>
          </form>

        </div>
      </div>

      <p class="text-center text-muted mt-3 small">
        Your details are stored securely. For help, contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a>.
      </p>

    </div>
  </div>
</div>
@endsection
