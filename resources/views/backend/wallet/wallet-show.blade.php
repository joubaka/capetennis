@extends('layouts.backend')

@section('title', 'Wallet Details')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection


@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="container">
  <!-- Back button -->
  <div class="mb-3">
    <a href="{{ URL::previous() }}" class="btn btn-outline-primary">
      <i class="ti ti-arrow-left"></i> Back
    </a>
  </div>

  @if(session('wallet_success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="ti ti-check-circle me-1"></i>{{ session('wallet_success') }}
      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <!-- Wallet Summary Card -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">{{ $user->name }}'s Wallet</h5>
      <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="badge bg-success fs-6 p-2">
          Balance: R{{ number_format($wallet->balance, 2) }}
        </span>
        @can('super-user')
        <button type="button" class="btn btn-primary btn-sm btn-wallet-add-tx"
                data-user-id="{{ $user->id }}"
                data-user-name="{{ $user->name }}"
                data-wallet-balance="R{{ number_format($wallet->balance, 2) }}">
          <i class="ti ti-plus"></i> Add Transaction
        </button>
        @endcan
      </div>
    </div>
    <div class="card-body">
      <p class="mb-0 text-muted">Recent transactions are shown one page at a time. The balance includes the complete wallet ledger. Existing entries are permanent; record a new credit or debit with a reference when an adjustment is needed.</p>
    </div>
  </div>

  <!-- Transactions Table -->
  <div class="card shadow-sm">
    <div class="card-header bg-light">
      <h6 class="mb-0">Transaction History</h6>
    </div>

    <p class="small text-muted px-3 mt-2 mb-2 d-sm-none">Scroll the table sideways to see all transaction details.</p>
    <div class="table-responsive">
      <table class="table table-hover table-striped table-bordered mb-0" style="min-width:640px">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Reference</th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $tx)
            <tr>
              <td class="text-nowrap"><small class="text-muted">{{ $tx->id }}</small></td>
              <td class="text-nowrap">{{ $tx->created_at->format('d M Y H:i') }}</td>
              <td>
                <span class="badge {{ $tx->type === 'credit' ? 'bg-success' : 'bg-danger' }}">
                  {{ ucfirst($tx->type) }}
                </span>
              </td>
              <td class="fw-bold text-nowrap {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}">
                R{{ number_format($tx->amount, 2) }}
              </td>
              <td>{{ $tx->meta['reference'] ?? '-' }}</td>
              <td><small class="text-muted">{{ $tx->source_type ?? '-' }}</small></td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">No transactions found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">{{ $transactions->links() }}</div>
</div>

@can('super-user')
{{-- Add Transaction Modal --}}
<div class="modal fade" id="modal-wallet-add-tx" aria-labelledby="wallet-add-title" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="form-wallet-add-tx" method="POST" action="{{ route('superadmin.wallets.transaction.store', $user) }}" class="modal-content">
      @csrf
      <input type="hidden" name="wallet_form" value="add">
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
      <div class="modal-header">
        <h5 id="wallet-add-title" class="modal-title"><i class="ti ti-plus-circle me-1 text-success"></i>Add Transaction</h5>
        <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">
          <i class="ti ti-user me-1"></i><strong>{{ $user->name }}</strong>
          &mdash; Balance: <span class="text-success fw-bold">R{{ number_format($wallet->balance, 2) }}</span>
        </p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Type</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="credit" id="tx-type-credit" @checked(old('type', 'credit') === 'credit')>
              <label class="form-check-label text-success" for="tx-type-credit"><i class="ti ti-arrow-up me-1"></i>Credit</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="debit" id="tx-type-debit" @checked(old('type') === 'debit')>
              <label class="form-check-label text-danger" for="tx-type-debit"><i class="ti ti-arrow-down me-1"></i>Debit</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="wallet-add-amount">Amount (R)</label>
          <input id="wallet-add-amount" type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00" value="{{ old('amount') }}">
        </div>
        <div class="mb-3">
          <label for="wallet-add-reference" class="form-label">Reference <small class="text-muted">(optional)</small></label>
          <input id="wallet-add-reference" type="text" name="reference" class="form-control" placeholder="e.g. Admin top-up" value="{{ old('reference') }}">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Save Transaction</button>
      </div>
    </form>
  </div>
</div>

@endcan
</div>
@endsection

@section('page-script')
@can('super-user')
<script>
$(function () {
  var addTxModal  = new bootstrap.Modal(document.getElementById('modal-wallet-add-tx'));

  @if($errors->any() && old('wallet_form') === 'add')
  addTxModal.show();
  @endif

  $('.btn-wallet-add-tx').on('click', function () {
    addTxModal.show();
  });




});
</script>
@endcan
@endsection
