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

  @if(session('wallet_success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="ti ti-check-circle me-1"></i>{{ session('wallet_success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <!-- Wallet Summary Card -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">{{ $user->name }}'s Wallet</h5>
      <div class="d-flex align-items-center gap-3">
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
            <th>#</th>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Reference</th>
            <th>Source</th>
            @can('super-user')
            <th class="text-center">Actions</th>
            @endcan
          </tr>
        </thead>
        <tbody>
          @forelse($transactions as $tx)
            <tr>
              <td><small class="text-muted">{{ $tx->id }}</small></td>
              <td>{{ $tx->created_at->format('d M Y H:i') }}</td>
              <td>
                <span class="badge {{ $tx->type === 'credit' ? 'bg-success' : 'bg-danger' }}">
                  {{ ucfirst($tx->type) }}
                </span>
              </td>
              <td class="fw-bold {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}">
                R{{ number_format($tx->amount, 2) }}
              </td>
              <td>{{ $tx->meta['reference'] ?? '-' }}</td>
              <td><small class="text-muted">{{ $tx->source_type ?? '-' }}</small></td>
              @can('super-user')
              <td class="text-center">
                <div class="d-flex gap-1 justify-content-center">
                  <button type="button"
                          class="btn btn-icon btn-sm btn-outline-primary btn-wallet-edit-tx"
                          title="Edit Transaction"
                          data-tx-id="{{ $tx->id }}"
                          data-tx-type="{{ $tx->type }}"
                          data-tx-amount="{{ $tx->amount }}"
                          data-tx-reference="{{ $tx->meta['reference'] ?? '' }}">
                    <i class="ti ti-pencil"></i>
                  </button>
                  <form method="POST"
                        action="{{ route('superadmin.wallets.transaction.destroy', $tx) }}"
                        onsubmit="return confirm('Delete this transaction? The wallet balance will be recalculated.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-icon btn-sm btn-outline-danger" title="Delete Transaction">
                      <i class="ti ti-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
              @endcan
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted">No transactions found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@can('super-user')
{{-- Add Transaction Modal --}}
<div class="modal fade" id="modal-wallet-add-tx" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="form-wallet-add-tx" method="POST" action="{{ route('superadmin.wallets.transaction.store', $user) }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-plus-circle me-1 text-success"></i>Add Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
              <input class="form-check-input" type="radio" name="type" value="credit" id="tx-type-credit" checked>
              <label class="form-check-label text-success" for="tx-type-credit"><i class="ti ti-arrow-up me-1"></i>Credit</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="debit" id="tx-type-debit">
              <label class="form-check-label text-danger" for="tx-type-debit"><i class="ti ti-arrow-down me-1"></i>Debit</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Amount (R)</label>
          <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
        </div>
        <div class="mb-3">
          <label class="form-label">Reference <small class="text-muted">(optional)</small></label>
          <input type="text" name="reference" class="form-control" placeholder="e.g. Admin top-up">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Save Transaction</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Transaction Modal --}}
<div class="modal fade" id="modal-wallet-edit-tx" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="form-wallet-edit-tx" method="POST" action="" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-pencil me-1 text-primary"></i>Edit Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-1">Transaction <strong>#<span id="edit-tx-id-label"></span></strong></p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Type</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="credit" id="edit-tx-type-credit">
              <label class="form-check-label text-success" for="edit-tx-type-credit"><i class="ti ti-arrow-up me-1"></i>Credit</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="type" value="debit" id="edit-tx-type-debit">
              <label class="form-check-label text-danger" for="edit-tx-type-debit"><i class="ti ti-arrow-down me-1"></i>Debit</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Amount (R)</label>
          <input type="number" name="amount" id="edit-tx-amount" step="0.01" min="0.01" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Reference <small class="text-muted">(optional)</small></label>
          <input type="text" name="reference" id="edit-tx-reference" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Update Transaction</button>
      </div>
    </form>
  </div>
</div>

@section('page-script')
<script>
$(function () {
  var addTxModal  = new bootstrap.Modal(document.getElementById('modal-wallet-add-tx'));
  var editTxModal = new bootstrap.Modal(document.getElementById('modal-wallet-edit-tx'));

  $('.btn-wallet-add-tx').on('click', function () {
    addTxModal.show();
  });

  $(document).on('click', '.btn-wallet-edit-tx', function () {
    var txId  = $(this).data('tx-id');
    var type  = $(this).data('tx-type');
    var amt   = $(this).data('tx-amount');
    var ref   = $(this).data('tx-reference');
    var url   = '{{ url("backend/superadmin/wallets/transactions") }}/' + txId;

    $('#form-wallet-edit-tx').attr('action', url);
    $('#edit-tx-id-label').text(txId);
    $('input[name="type"][value="' + type + '"]', '#form-wallet-edit-tx').prop('checked', true);
    $('#edit-tx-amount').val(amt);
    $('#edit-tx-reference').val(ref);
    editTxModal.show();
  });
});
</script>
@endsection
@endcan

@endsection
