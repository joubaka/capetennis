@extends('layouts/layoutMaster')

@section('title', 'Bank Refund Details')

@section('content')
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center mb-3">
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
            <span class="badge bg-success">Completed</span>
          @else
            <span class="badge bg-warning text-dark">Pending</span>
          @endif
        </dd>

        <dt class="col-sm-3">PayFast Transaction</dt>
        <dd class="col-sm-9"><code>{{ $registration->pf_transaction_id ?? '—' }}</code></dd>
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
    <div class="card-header"><h6 class="mb-0">Banking Details</h6></div>
    <div class="card-body">
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
    </div>
  </div>

  @if($registration->refund_status === 'pending')
  <div class="d-flex gap-2">
    <form method="POST" action="{{ route('admin.refunds.bank.complete', $registration) }}"
          onsubmit="return confirm('Mark this bank refund as paid?');">
      @csrf
      <button class="btn btn-success">
        <i class="ti ti-check me-1"></i> Mark as Completed
      </button>
    </form>

    @if($registration->pf_transaction_id)
    <a href="{{ route('admin.refunds.bank.payfast-query', $registration) }}"
       class="btn btn-outline-secondary">
      <i class="ti ti-search me-1"></i> Query PayFast Status
    </a>
    @endif
  </div>
  @endif

</div>
@endsection