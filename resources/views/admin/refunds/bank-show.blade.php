@extends('layouts/layoutMaster')

@section('title', 'Bank Refund Details')

@section('content')
<div class="container mt-4">

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Bank Refund #{{ $registration->id }}</h5>

      <a href="{{ route('admin.refunds.bank.index') }}"
         class="btn btn-sm btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>
        Back to Refund List
      </a>
    </div>

    <div class="card-body">

      <p><strong>Player:</strong> {{ $registration->display_name }}</p>
      <p><strong>Amount:</strong> R{{ number_format($registration->refund_net, 2) }}</p>

      <hr>

      <p><strong>Account Name:</strong> {{ $registration->refund_account_name }}</p>
      <p><strong>Bank:</strong> {{ $registration->refund_bank_name }}</p>
      <p><strong>Account Number:</strong> {{ $registration->refund_account_number }}</p>
      <p><strong>Branch Code:</strong> {{ $registration->refund_branch_code }}</p>
      <p><strong>Account Type:</strong> {{ ucfirst($registration->refund_account_type) }}</p>

      <hr>

      @if($registration->refund_status === 'pending')
      <form method="POST"
            action="{{ route('admin.refunds.bank.complete', $registration) }}">
        @csrf
        <button class="btn btn-success"
                onclick="this.disabled=true; this.form.submit();">
          Mark as Completed
        </button>
      </form>
      @else
        <span class="badge bg-success">
          Refund Completed
        </span>
      @endif

    </div>
  </div>

</div>
@endsection
