@extends('layouts.backend')

@section('title', 'Bank Refund Details')

@section('content')
@php
  $paymentInfo = $registration->paymentInfo();
  $hasPayFastPayment = !empty($paymentInfo['pf_payment_id']);
@endphp
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h4 class="mb-0">Bank Refund #{{ $registration->id }}</h4>
    <a href="{{ route('admin.refunds.bank.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i> Back to Refund List
    </a>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ $errors->first() }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Player &amp; Event</h6></div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Player</dt>
        <dd class="col-sm-9">{{ $registration->display_name }}</dd>

        <dt class="col-sm-3">Event</dt>
        <dd class="col-sm-9">{{ optional($registration->categoryEvent->event)->name ?? '—' }}</dd>

        <dt class="col-sm-3">Category</dt>
        <dd class="col-sm-9">{{ optional($registration->categoryEvent->category)->name ?? '—' }}</dd>

        <dt class="col-sm-3">Status</dt>
        <dd class="col-sm-9">
          @if($registration->refund_status === 'completed')
            <span class="badge bg-success">Processed</span>
          @elseif($registration->refund_status === 'waived')
            <span class="badge bg-secondary">Waived without payment</span>
          @else
            <span class="badge bg-warning text-dark">Pending</span>
          @endif
        </dd>

        <dt class="col-sm-3">Recorded refund amount</dt><dd class="col-sm-9 fw-bold">R{{ number_format($registration->refund_net, 2) }}</dd>
        <dt class="col-sm-3">PayFast Transaction</dt>
        <dd class="col-sm-9"><code>{{ $paymentInfo['pf_payment_id'] ?? '—' }}</code></dd>
      </dl>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Refund Amount</h6></div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Gross</dt>
        <dd class="col-sm-9">R{{ number_format($registration->refund_gross, 2) }}</dd>

        <dt class="col-sm-3">Fee</dt>
        <dd class="col-sm-9 text-danger">R{{ number_format($registration->refund_fee, 2) }}</dd>

        <dt class="col-sm-3">Net</dt>
        <dd class="col-sm-9 fw-bold text-success">R{{ number_format($registration->refund_net, 2) }}</dd>

        <dt class="col-sm-3">Withdrawn At</dt>
        <dd class="col-sm-9">{{ optional($registration->withdrawn_at)->format('Y-m-d H:i') ?? '—' }}</dd>
      </dl>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="mb-0">Banking Details</h6>
      @if(auth()->user()->hasRole('super-user') && $registration->refund_status === 'pending')
        <button class="btn btn-sm btn-outline-primary" type="button"
                data-bs-toggle="collapse" data-bs-target="#editBankForm">
          <i class="ti ti-pencil me-1"></i>Edit / Enter Details
        </button>
      @endif
    </div>
    <div class="card-body">
      @if(!$registration->refund_account_name)<p class="text-warning">Bank details missing. Enter or review details before payment.</p>@endif
      <p class="small text-muted">Saving bank details does not process a refund.</p>
      <dl class="row mb-0">
        <dt class="col-sm-3">Account Name</dt>
        <dd class="col-sm-9">{{ $registration->refund_account_name ?? '—' }}</dd>

        <dt class="col-sm-3">Bank</dt>
        <dd class="col-sm-9">{{ $registration->refund_bank_name ?? '—' }}</dd>

        <dt class="col-sm-3">Account Number</dt>
        <dd class="col-sm-9">{{ $registration->refund_account_number ?? '—' }}</dd>

        <dt class="col-sm-3">Branch Code</dt>
        <dd class="col-sm-9">{{ $registration->refund_branch_code ?? '—' }}</dd>

        <dt class="col-sm-3">Account Type</dt>
        <dd class="col-sm-9">{{ ucfirst($registration->refund_account_type ?? '—') }}</dd>
      </dl>

      @if(auth()->user()->hasRole('super-user') && $registration->refund_status === 'pending')
        @php
          $bankNames = [
            'absa'         => 'ABSA',
            'capitec'      => 'Capitec',
            'fnb'          => 'FNB',
            'investec'     => 'Investec',
            'nedbank'      => 'Nedbank',
            'standard'     => 'Standard Bank',
            'african'      => 'African Bank',
            'discovery'    => 'Discovery Bank',
            'sasfin'       => 'Sasfin',
            'tyme'         => 'TymeBank',
            'other'        => 'Other',
          ];
        @endphp
        <div class="collapse mt-4 {{ !$registration->refund_account_name || $errors->hasAny(['refund_account_name', 'refund_bank_name', 'refund_account_number', 'refund_branch_code', 'refund_account_type']) ? 'show' : '' }}" id="editBankForm">
          <hr>
          <h6 class="text-muted mb-3"><i class="ti ti-shield-lock me-1"></i>Superadmin — Enter Bank Details on Behalf of User</h6>
          <form method="POST" action="{{ route('admin.refunds.bank.save-bank-details', $registration) }}">
            @csrf
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Account Holder Name <span class="text-danger">*</span></label>
                <input type="text" name="refund_account_name"
                       class="form-control @error('refund_account_name') is-invalid @enderror"
                       value="{{ old('refund_account_name', $registration->refund_account_name) }}"
                       placeholder="Name exactly as on bank account" required>
                @error('refund_account_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Bank <span class="text-danger">*</span></label>
                <select name="refund_bank_name" class="form-select @error('refund_bank_name') is-invalid @enderror" required>
                  <option value="">— Select bank —</option>
                  @foreach($bankNames as $val => $label)
                    <option value="{{ $val }}" {{ old('refund_bank_name', $registration->refund_bank_name) === $val ? 'selected' : '' }}>
                      {{ $label }}
                    </option>
                  @endforeach
                </select>
                @error('refund_bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                <input type="text" name="refund_account_number"
                       class="form-control @error('refund_account_number') is-invalid @enderror"
                       value="{{ old('refund_account_number', $registration->refund_account_number) }}"
                       placeholder="e.g. 1234567890" required maxlength="30">
                @error('refund_account_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Branch Code <span class="text-danger">*</span></label>
                <input type="text" name="refund_branch_code"
                       class="form-control @error('refund_branch_code') is-invalid @enderror"
                       value="{{ old('refund_branch_code', $registration->refund_branch_code) }}"
                       placeholder="e.g. 632005" required maxlength="20" inputmode="numeric">
                @error('refund_branch_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Account Type <span class="text-danger">*</span></label>
                <select name="refund_account_type" class="form-select @error('refund_account_type') is-invalid @enderror" required>
                  <option value="">— Select —</option>
                  <option value="current" {{ old('refund_account_type', $registration->refund_account_type) === 'current' ? 'selected' : '' }}>Current</option>
                  <option value="savings" {{ old('refund_account_type', $registration->refund_account_type) === 'savings' ? 'selected' : '' }}>Savings</option>
                </select>
                @error('refund_account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">
                  <i class="ti ti-device-floppy me-1"></i>Save Bank Details
                </button>
              </div>
            </div>
          </form>
        </div>
      @endif
    </div>
  </div>

  @if($hasPayFastPayment)
  <section class="card mb-4"><div class="card-body"><h6>Check provider status</h6><p class="small text-muted">Check PayFast settlement separately from Cape Tennis processing records.</p><a href="{{ route('admin.refunds.bank.payfast-query', $registration) }}" class="btn btn-outline-secondary">Query PayFast Status</a></div></section>
  @endif
  @if($registration->refund_status === 'pending')
  <section class="card mb-4"><div class="card-body"><h6>{{ $hasPayFastPayment ? 'Submit refund to PayFast' : 'Record a manual bank payment' }}</h6><p class="small text-muted">{{ $hasPayFastPayment ? 'This submits a refund to the payment provider. The PayFast-funded portion is submitted to the provider; a wallet-funded portion is credited separately. Confirm the details before proceeding.' : 'Use only after you have paid the refund through your bank. This records that payment in Cape Tennis.' }}</p>
    <form method="POST" action="{{ route('admin.refunds.bank.complete', $registration) }}"
          onsubmit="return confirm({{ Js::from($hasPayFastPayment ? 'Submit this refund to PayFast? Confirm the player and refund amount before proceeding.' : 'Confirm you have already paid this bank refund. Record the manual payment?') }});">
      @csrf
      <button class="btn {{ $hasPayFastPayment ? 'btn-warning' : 'btn-success' }}">{{ $hasPayFastPayment ? 'Submit to PayFast' : 'Record manual payment' }}</button>
    </form>
  </div></section>
  @endif

</div>
@endsection
