@extends('layouts/layoutMaster')

@section('title', 'Wallet Details')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
  <script src="{{ asset('assets/js/app-dataTables.js') }}"></script>
@endsection

@section('content')
<div class="container">
  <!-- Back button -->
  <div class="mb-3">
    <a href="{{ URL::previous() }}" class="btn btn-outline-primary">
      <i class="ti ti-arrow-left"></i> Back
    </a>
  </div>

  <!-- Wallet Summary Card -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">{{ $user->name }}'s Wallet</h5>
      <div class="d-flex align-items-center gap-3">
        <span class="badge bg-success fs-6 p-2">
          Balance: R{{ number_format($wallet->balance, 2) }}
        </span>
       
      </div>
    </div>
    <div class="card-body">
      <p class="mb-0 text-muted">Below is the full list of wallet transactions for this user.</p>
    </div>
  </div>

  <!-- Transactions Table -->
  <div class="card shadow-sm">
    <div class="card-header bg-light">
      <h6 class="mb-0">Transaction History</h6>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-striped table-bordered mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Reference</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $tx)
            <tr>
              <td>{{ $tx->created_at->format('d M Y') }}</td>
              <td>
                <span class="badge {{ $tx->type === 'credit' ? 'bg-success' : 'bg-danger' }}">
                  {{ ucfirst($tx->type) }}
                </span>
              </td>
              <td class="fw-bold {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}">
                R{{ number_format($tx->amount, 2) }}
              </td>
              <td>{{ $tx->reference ?? '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted">No transactions found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@include('backend.wallet.modals.deposit-modal')
@endsection
