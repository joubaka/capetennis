@extends('layouts/layoutMaster')

@section('title', ($user->userName ?? $user->name) . ' – Profile')

{{-- ================= VENDOR CSS ================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

{{-- ================= PAGE CSS ================= --}}
@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}">
<style>
  .card-header h3 { font-size: 1.25rem; font-weight: 600; }
  .list-unstyled li span.fw-semibold { min-width: 120px; display: inline-block; }
</style>
@endsection

{{-- ================= VENDOR JS ================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')

<input type="hidden" value="{{ $user->id }}" id="viewUserId">

<div class="mb-3">
  <a href="{{ url()->previous() }}" class="btn btn-outline-primary btn-sm">
    <i class="ti ti-arrow-left me-1"></i> Back
  </a>
</div>

<div class="row">

  {{-- ================= USER SIDEBAR ================= --}}
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <div class="card mb-4">
      <div class="card-body text-center">

        <img src="{{ $user->profile_photo_url ?? asset('assets/img/avatars/default.svg') }}"
             class="rounded-circle mb-3"
             width="100" height="100">

        <h4 class="mb-0">{{ $user->userName ?? $user->name }}</h4>
        <small class="text-muted">{{ $user->email }}</small>

        @if($user->roles->count())
        <div class="mt-2">
          @foreach($user->roles as $role)
            <span class="badge bg-label-primary me-1">{{ $role->name }}</span>
          @endforeach
        </div>
        @endif

        <hr class="my-4">

        <ul class="list-unstyled text-start ps-2">
          <li class="mb-2"><span class="fw-semibold">Username:</span> {{ $user->name }}</li>
          <li class="mb-2"><span class="fw-semibold">Name:</span> {{ $user->userName ?? '-' }}</li>
          <li class="mb-2"><span class="fw-semibold">Surname:</span> {{ $user->userSurname ?? '-' }}</li>
          <li class="mb-2"><span class="fw-semibold">Email:</span> {{ $user->email }}</li>
          <li class="mb-2"><span class="fw-semibold">Contact:</span> {{ $user->cell_nr ?? '-' }}</li>

          <li class="mb-2">
            <span class="fw-semibold">Wallet Balance:</span>
            <span class="badge bg-label-success">
              R {{ number_format($wallet?->balance ?? 0, 2) }}
            </span>
          </li>
        </ul>
      </div>
    </div>

    {{-- ================= PLAYERS LINKED ================= --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0"><i class="ti ti-users me-1"></i> Players Linked</h3>
        <button class="btn btn-sm btn-secondary"
                data-bs-toggle="modal"
                data-bs-target="#linkPlayerModal">
          <i class="ti ti-link me-1"></i> Link Player
        </button>
      </div>

      <div class="card-body">
        @forelse($user->players as $player)
          <div class="linked-player-row mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
            <div>
              <a href="{{ route('backend.player.profile', $player->id) }}"
                 class="btn btn-sm btn-outline-primary fw-semibold">
                {{ $player->name }} {{ $player->surname }}
              </a>
              <div class="text-muted small">{{ $player->email }}</div>
            </div>
            <button class="btn btn-danger btn-sm unlink-player"
                    data-user="{{ $user->id }}"
                    data-player="{{ $player->id }}">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        @empty
          <div class="alert alert-info mb-0">
            <i class="ti ti-info-circle me-1"></i> No players linked yet.
          </div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- ================= WALLET TRANSACTIONS ================= --}}
  <div class="col-xl-8 col-lg-7 col-md-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0"><i class="ti ti-wallet me-1"></i> Wallet Transactions</h3>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-success fs-6 p-2">
            Balance: R {{ number_format($wallet?->balance ?? 0, 2) }}
          </span>
          @can('super-user')
          <button class="btn btn-sm btn-primary"
                  data-bs-toggle="modal"
                  data-bs-target="#walletTxnModal">
            <i class="ti ti-plus me-1"></i> Credit / Debit
          </button>
          @endcan
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0 datatable-transactions">
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
                  <td>{{ $tx->created_at->format('d M Y H:i') }}</td>
                  <td>
                    <span class="badge {{ $tx->type === 'credit' ? 'bg-success' : 'bg-danger' }}">
                      {{ ucfirst($tx->type) }}
                    </span>
                  </td>
                  <td class="fw-bold {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}">
                    R {{ number_format($tx->amount, 2) }}
                  </td>
                  <td>{{ $tx->meta['reference'] ?? '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">No transactions found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- ================= LINK PLAYER MODAL ================= --}}
<div class="modal fade" id="linkPlayerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Link Player</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <select id="link-player-select" class="form-select">
          <option></option>
          @foreach($players as $p)
            <option value="{{ $p->id }}">{{ $p->name }} {{ $p->surname }}</option>
          @endforeach
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" id="linkPlayerBtn">Link Player</button>
      </div>
    </div>
  </div>
</div>

{{-- ================= WALLET CREDIT/DEBIT MODAL ================= --}}
@can('super-user')
<div class="modal fade" id="walletTxnModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-wallet me-1"></i> Credit / Debit Wallet</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label for="txn-type" class="form-label">Transaction Type</label>
          <select id="txn-type" class="form-select">
            <option value="credit">Credit (Add funds)</option>
            <option value="debit">Debit (Deduct funds)</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="txn-amount" class="form-label">Amount (R)</label>
          <input type="number" id="txn-amount" class="form-control" step="0.01" min="0.01" placeholder="0.00">
        </div>
        <div class="mb-3">
          <label for="txn-reference" class="form-label">Reference (optional)</label>
          <input type="text" id="txn-reference" class="form-control" placeholder="e.g. Manual top-up">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitWalletTxnBtn">
          <i class="ti ti-check me-1"></i> Submit
        </button>
      </div>

    </div>
  </div>
</div>
@endcan

@endsection

@section('page-script')
<script>
'use strict';

$(function () {

  const CSRF   = $('meta[name="csrf-token"]').attr('content');
  const userId = $('#viewUserId').val();

  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });

  // DataTable for transactions (client-side, already loaded)
  if ($('.datatable-transactions').length && $('.datatable-transactions tbody tr').length > 1) {
    $('.datatable-transactions').DataTable({
      ordering: true,
      order: [[0, 'desc']],
      pageLength: 25,
      paging: true,
      searching: true,
    });
  }

  // ==========================================================
  // 🟦 WALLET CREDIT / DEBIT
  // ==========================================================
  $('#submitWalletTxnBtn').on('click', function () {

    const type      = $('#txn-type').val();
    const amount    = $('#txn-amount').val();
    const reference = $('#txn-reference').val();

    if (!amount || parseFloat(amount) <= 0) {
      toastr.warning('Please enter a valid amount');
      return;
    }

    Swal.fire({
      title: type === 'credit' ? 'Crediting wallet...' : 'Debiting wallet...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.ajax({
      url: APP_URL + '/backend/wallet/' + userId + '/transaction',
      type: 'POST',
      data: { type: type, amount: amount, reference: reference },
      headers: { 'Accept': 'application/json' },
      success: res => {
        Swal.close();
        toastr.success(res.message);
        $('#walletTxnModal').modal('hide');
        location.reload();
      },
      error: xhr => {
        Swal.close();
        toastr.error(xhr.responseJSON?.message || 'Transaction failed');
      }
    });
  });

  // ==========================================================
  // 🟦 LINK PLAYER
  // ==========================================================
  $('#linkPlayerModal').on('shown.bs.modal', function () {
    $('#link-player-select').select2({
      dropdownParent: $('#linkPlayerModal'),
      placeholder: 'Select a player',
      width: '100%',
      allowClear: true
    });
  });

  $('#linkPlayerBtn').on('click', function () {
    const playerId = $('#link-player-select').val();
    if (!playerId) { toastr.warning('Select a player'); return; }

    Swal.fire({ title: 'Linking player...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.post(APP_URL + '/backend/user/' + userId + '/players', { player_id: playerId })
      .done(res => { Swal.close(); toastr.success(res.message); location.reload(); })
      .fail(xhr => { Swal.close(); toastr.error(xhr.responseJSON?.message || 'Failed'); });
  });

  // ==========================================================
  // 🟦 UNLINK PLAYER
  // ==========================================================
  $(document).on('click', '.unlink-player', function () {
    const btn      = $(this);
    const uId      = btn.data('user');
    const playerId = btn.data('player');
    const row      = btn.closest('.linked-player-row');

    Swal.fire({
      title: 'Unlink player?',
      text: 'This will remove the player from this profile.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, unlink'
    }).then(result => {
      if (!result.isConfirmed) return;

      $.ajax({
        url: APP_URL + '/backend/user/' + uId + '/players/' + playerId,
        type: 'DELETE',
        success: res => { toastr.success(res.message); row.slideUp(200, () => row.remove()); },
        error: xhr => { toastr.error(xhr.responseJSON?.message || 'Failed to unlink'); }
      });
    });
  });

});
</script>
@endsection
