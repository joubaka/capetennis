@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

{{-- ================= VENDOR CSS ================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
@endsection

{{-- ================= PAGE CSS ================= --}}
@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}">
<style>
  .card-header h3 { font-size: 1.25rem; font-weight: 600; }
  .list-unstyled li span.fw-semibold { min-width: 120px; display: inline-block; }
  .addPlayerToProfile { float: right; }
</style>
@endsection

{{-- ================= VENDOR JS ================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
@endsection

@section('content')

<input type="hidden" value="{{ $user->id }}" id="user">

<div class="row">

  {{-- ================= USER SIDEBAR ================= --}}
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <div class="card mb-4">
      <div class="card-body text-center">

        <img src="{{ asset('assets/img/avatars/1.png') }}"
             class="rounded-circle mb-3"
             width="100" height="100">

        <h4 class="mb-0">{{ $user->userName ?? $user->name }}</h4>
        <small class="text-muted">{{ $user->email }}</small>

        @can('admin')
        <div class="d-flex justify-content-center mt-3">
          <div class="text-center">
            <span class="badge bg-label-primary p-2 mb-1">
              <i class="ti ti-calendar ti-sm"></i>
            </span>
            <p class="fw-semibold mb-0">{{ $user->events->count() }}</p>
            <small class="text-muted">Events</small>
          </div>
        </div>
        @endcan

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
              R {{ number_format($user->wallet?->balance ?? 0, 2) }}
            </span>
          </li>
        </ul>

        <div class="d-flex justify-content-center gap-2 mt-3">
          <a href="{{ route('wallet.show', $user->id) }}" class="btn btn-outline-info btn-sm">
            <i class="ti ti-wallet me-1"></i> Wallet
          </a>
          <button class="btn btn-primary btn-sm"
                  data-bs-toggle="modal"
                  data-bs-target="#editUser">
            <i class="ti ti-user-edit me-1"></i> Edit Profile
          </button>
          @can('super-user')
          <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-settings me-1"></i> Settings
          </a>
          @endcan
        </div>
      </div>
    </div>

    {{-- ================= PLAYERS ================= --}}
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h3 class="m-0"><i class="ti ti-users me-1"></i> Players Linked</h3>
        <button class="btn btn-sm btn-secondary addPlayerToProfile"
                data-bs-toggle="modal"
                data-bs-target="#addProfileModal">
          <i class="ti ti-link me-1"></i> Link Player
        </button>
      </div>

      <div class="card-body">
        @forelse($user->players as $player)
          @php
            $profileStatus = $player->getProfileStatus();
            $agreementStatus = $player->hasAcceptedLatestAgreement();
          @endphp
          <div class="linked-player-row mb-3 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <a href="{{ route('backend.player.profile', $player->id) }}"
                   class="btn btn-sm btn-outline-primary fw-semibold">
                  {{ $player->name }} {{ $player->surname }}
                </a>
                @if($player->isMinor())
                  <span class="badge bg-info ms-1">Minor</span>
                @endif
                <div class="text-muted small">{{ $player->email }}</div>
              </div>

              <div class="btn-group btn-group-sm">
                <button
                  class="btn btn-danger btn-sm unlink-player"
                  data-user="{{ $user->id }}"
                  data-player="{{ $player->id }}">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </div>

            {{-- Status Badges --}}
            <div class="mt-2 d-flex flex-wrap gap-2">
              {{-- Profile Status --}}
              <a href="{{ route('player.profile.edit', $player) }}" 
                 class="badge bg-{{ $profileStatus['badge'] }} text-decoration-none"
                 title="Click to update profile">
                <i class="ti {{ $profileStatus['icon'] }} me-1"></i>
                Profile: {{ ucfirst($profileStatus['status']) }}
              </a>

              {{-- Agreement Status --}}
              @if($agreementStatus)
                <span class="badge bg-success">
                  <i class="ti ti-file-check me-1"></i> CoC Accepted
                </span>
              @else
                <a href="{{ route('agreements.show') }}" class="badge bg-warning text-decoration-none">
                  <i class="ti ti-file-alert me-1"></i> CoC Pending
                </a>
              @endif

              {{-- Last Updated --}}
              @if($player->profile_updated_at)
                <span class="badge bg-label-secondary" title="Last profile update">
                  <i class="ti ti-clock me-1"></i>
                  {{ $player->profile_updated_at->diffForHumans() }}
                </span>
              @else
                <span class="badge bg-label-danger">
                  <i class="ti ti-alert-circle me-1"></i> Never updated
                </span>
              @endif
            </div>
          </div>
        @empty
          <div class="alert alert-info mb-0">
            <i class="ti ti-info-circle me-1"></i> No players linked yet.
          </div>
        @endforelse
      </div>
    </div>

    {{-- ================= WALLET TRANSACTIONS ================= --}}
    <div class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0"><i class="ti ti-wallet me-1"></i> Wallet Transactions</h3>
        @can('super-user')
        <button class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#walletTransactionModal">
          <i class="ti ti-plus me-1"></i> Credit / Debit
        </button>
        @endcan
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Reference</th>
              </tr>
            </thead>
            <tbody id="walletTransactionsBody">
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

  @can('admin')
  <div class="col-xl-8 col-lg-7 col-md-7">
    @include('templates.adminDashboardTemplate')
  </div>
  @endcan

</div>

<div class="modal fade" id="addProfileModal">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Link Player</h5>
      </div>

      <div class="modal-body">
        <select id="player-select" class="form-select">
          <option></option>
          @foreach($players as $player)
            <option value="{{ $player->id }}">
              {{ $player->name }} {{ $player->surname }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" id="linkPlayerBtn">
          Link Player
        </button>
      </div>

    </div>
  </div>
</div>

{{-- ================= WALLET CREDIT/DEBIT MODAL ================= --}}
@can('super-user')
<div class="modal fade" id="walletTransactionModal" tabindex="-1" aria-hidden="true">
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
          <input type="number" id="txn-amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
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

@include('_partials/_modals/modal-upgrade-plan')
@include('_partials/_modals/modal-edit-user')
@can('superUser')
  @include('_partials/_modals/modal-add-event')
@endcan
@endsection

  


@section('page-script')
<script>
'use strict';

$(function () {

  console.log('✅ Dashboard script loaded');

  const CSRF   = $('meta[name="csrf-token"]').attr('content');
  const userId = $('#user').val();
  const PLAYER_URL = APP_URL + '/backend/player';

  // ==========================================================
  // 🟦 TAB NAVIGATION FROM URL HASH OR LOCALSTORAGE
  // ==========================================================
  function activateTabFromHash() {
    var hash = window.location.hash;
    var storedTab = localStorage.getItem('dashboardTab');

    // Clear stored tab after use
    if (storedTab) {
      localStorage.removeItem('dashboardTab');
      hash = '#' + storedTab;
    }

    if (hash) {
      var tabId = hash.replace('#', '');
      var tabButton = $('[data-bs-target="#' + tabId + '"]');
      if (tabButton.length) {
        // Deactivate all tabs
        $('.nav-pills .nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');

        // Activate the target tab
        tabButton.addClass('active').attr('aria-selected', 'true');
        $(hash).addClass('show active');

        console.log('✅ Activated tab: ' + tabId);
      }
    }
  }

  // Run on page load
  activateTabFromHash();

  // Also handle hash changes
  $(window).on('hashchange', activateTabFromHash);

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

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
      data: {
        type: type,
        amount: amount,
        reference: reference
      },
      headers: { 'Accept': 'application/json' },
      success: res => {
        Swal.close();
        toastr.success(res.message);
        $('#walletTransactionModal').modal('hide');
        $('#txn-amount').val('');
        $('#txn-reference').val('');
        location.reload();
      },
      error: xhr => {
        Swal.close();
        toastr.error(xhr.responseJSON?.message || 'Transaction failed');
      }
    });
  });

  // ==========================================================
  // 🟦 EVENTS DATATABLE
  // ==========================================================
  var dtEvents = $('.datatable-events');
  if (dtEvents.length) {
    dtEvents.DataTable({
      ordering: true,
      order: [[1, 'desc']],
      pageLength: 25,
      ajax: APP_URL + '/events/ajax/userEvents/' + userId,
      columns: [
        { data: 'name' },
        { data: 'start_date' },
        { data: 'entryFee' },
        { data: 'registrations' },
        { data: null, orderable: false },
      ],
      columnDefs: [
        {
          targets: 0,
          render: function (data, type, full) {
            var link = APP_URL + '/events/' + full.id;
            var label = '<a href="' + link + '" class="btn btn-warning btn-sm text-white">' + full.name + '</a>';
            var isUpcoming = full.start_date && new Date(full.start_date) > new Date()
              ? ' <span class="badge rounded-pill bg-label-success ms-1">Upcoming</span>'
              : '';
            return label + isUpcoming;
          }
        },
        {
          targets: 2,
          render: function (data, type, full) { return 'R' + (full.entryFee || 0); }
        },
        {
          targets: 3,
          render: function (data, type, full) { return full.registrations || 0; }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            var admin = '<a href="' + APP_URL + '/backend/event/' + full.id + '/overview" class="btn btn-sm btn-secondary me-1">Dashboard</a>';
            var copy = '<form method="POST" action="' + APP_URL + '/backend/event/' + full.id + '/copy" class="d-inline" onsubmit="return confirm(\'Copy this event?\')">' 
              + '<input type="hidden" name="_token" value="' + CSRF + '">'
              + '<button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-copy me-1"></i>Copy</button>'
              + '</form>';
            return admin + copy;
          }
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 SERIES DATATABLE
  // ==========================================================
  var dtSeries = $('.datatable-series');
  if (dtSeries.length) {
    dtSeries.DataTable({
      ordering: false,
      paging: false,
      ajax: APP_URL + '/events/ajax/series',
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: null },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 2,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/ranking/settings/' + full.id + '" class="btn btn-sm btn-warning">Settings</a>';
          }
        },
        {
          targets: 3,
          render: function (data, type, full) {
            var btnClass = full.leaderboard_published ? 'btn-success' : 'btn-danger';
            var text = full.leaderboard_published ? 'Published' : 'Not Published';
            return '<div data-id="' + full.id + '" class="btn ' + btnClass + ' btn-sm publishLeaderboard">' + text + '</div>';
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/ranking/' + full.id + '" class="btn btn-sm btn-secondary">Show</a>';
          }
        },
      ],
      initComplete: function () {
        $(document).on('click', '.publishLeaderboard', function () {
          var id = $(this).data('id');
          var $btn = $(this);
          $.get(APP_URL + '/backend/series/publishLeaderboard/' + id, function (data) {
            if (data.leaderboard_published == 1) {
              $btn.removeClass('btn-danger').addClass('btn-success').text('Published');
            } else {
              $btn.removeClass('btn-success').addClass('btn-danger').text('Not Published');
            }
          });
        });
      }
    });
  }

  // ==========================================================
  // 🟦 USERS DATATABLE
  // ==========================================================
  var dtUsers = $('.datatable-users');
  if (dtUsers.length) {
    dtUsers.DataTable({
      ordering: true,
      pageLength: 25,
      ajax: APP_URL + '/backend/user',
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'email' },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 3,
          render: function (data, type, full) {
            if (full.roles && full.roles.length) {
              return full.roles.map(function (r) {
                return '<span class="badge bg-label-primary me-1">' + r.name + '</span>';
              }).join('');
            }
            return '<span class="text-muted">—</span>';
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/user/' + full.id + '" class="btn btn-sm btn-secondary">View</a>';
          }
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 PLAYERS DATATABLE
  // ==========================================================
  var dtPlayers = $('.datatable-players');
  if (dtPlayers.length) {
    dtPlayers.DataTable({
      ordering: true,
      pageLength: 25,
      ajax: APP_URL + '/backend/player',
      columns: [
        { data: 'id' },
        { data: null },
        { data: null },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 1,
          render: function (data, type, full) {
            return (full.name || '') + ' ' + (full.surname || '');
          }
        },
        {
          targets: 2,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/player/profile/' + full.id + '" class="btn btn-primary btn-sm">Profile</a>';
          }
        },
        {
          targets: 3,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/player/results/' + full.id + '" class="btn btn-secondary btn-sm">Results</a>';
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/player/details/' + full.id + '" class="btn btn-info btn-sm">Details</a>';
          }
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 ACTIVITY LOG DATATABLE
  // ==========================================================
  var dtActivity = $('#datatable-activity');
  if (dtActivity.length) {
    dtActivity.DataTable({
      ordering: true,
      order: [[0, 'asc']],
      pageLength: 50,
      columnDefs: [
        { targets: 4, orderable: false, searchable: false },
        { targets: 5, visible: false }
      ],
      drawCallback: function () {
        var body = this.api().table().body();
        $(body).find('[data-bs-toggle="popover"]').each(function () {
          if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            new bootstrap.Popover(this);
          }
        });
      }
    });
  }

  // Raw activity table
  var dtActivityRawEl = $('#datatable-activity-raw');
  var dtActivityRaw = null;
  if (dtActivityRawEl.length) {
    dtActivityRaw = dtActivityRawEl.DataTable({
      ordering: true,
      order: [[0, 'desc']],
      pageLength: 50,
      columnDefs: [
        { targets: 4, orderable: false, searchable: false }
      ],
      drawCallback: function () {
        var body = this.api().table().body();
        $(body).find('[data-bs-toggle="popover"]').each(function () {
          if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            new bootstrap.Popover(this);
          }
        });
      }
    });
  }

  // Activity filter by log name
  $('#activity-filter-log').on('change', function () {
    var val = $(this).val();
    // Filter grouped table by hidden log-names column (index 5)
    try {
      var table = $('#datatable-activity').DataTable();
      if (val) table.column(5).search(val).draw(); else table.column(5).search('').draw();
    } catch (e) {}

    // Filter raw table by log column (index 2)
    if (dtActivityRaw) {
      if (val) dtActivityRaw.column(2).search('^' + val + '$', true, false).draw(); else dtActivityRaw.column(2).search('').draw();
    }
  });

  // Toggle grouped / raw view
  $('#activity-toggle-view').on('change', function () {
    var checked = $(this).is(':checked');
    if (checked) {
      $('#datatable-activity').removeClass('d-none');
      $('#datatable-activity-raw').addClass('d-none');
    } else {
      $('#datatable-activity').addClass('d-none');
      $('#datatable-activity-raw').removeClass('d-none');
    }
  });

  // Adjust DataTable columns when Activity tab is first shown (hidden tabs issue)
  $('button[data-bs-target="#tab-activity"]').on('shown.bs.tab', function () {
    $('#datatable-activity').DataTable().columns.adjust().draw(false);
    if (dtActivityRaw) dtActivityRaw.columns.adjust().draw(false);
  });

  // ==========================================================
  // 🟦 SELECT2 (modal-safe)
  // ==========================================================
 $('#addProfileModal').on('shown.bs.modal', function () {
  $('#player-select').select2({
    dropdownParent: $('#addProfileModal'),
    placeholder: 'Select a player',
    width: '100%',
    allowClear: true
  });
});

$('#linkPlayerBtn').on('click', function () {

  const playerId = $('#player-select').val();

  if (!playerId) {
    toastr.warning('Select a player');
    return;
  }

  Swal.fire({
    title: 'Linking player...',
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  $.post(
    APP_URL + '/backend/user/' + $('#user').val() + '/players',
    { player_id: playerId }
  )
  .done(res => {
    Swal.close();
    toastr.success(res.message);

    // 🔁 update UI dynamically (or reload if you prefer)
    location.reload();
  })
  .fail(xhr => {
    Swal.close();
    toastr.error(xhr.responseJSON?.message || 'Failed');
  });
});


  $('#addProfileModal').on('hidden.bs.modal', function () {
    $('#add-player-select').val(null).trigger('change');
  });

  // ==========================================================
  // 🟦 ADD PLAYER TO PROFILE
  // ==========================================================
  $('#addPlayerToProfileButton').on('click', function () {

    const playerId = $('#add-player-select').val();

    if (!playerId) {
      toastr.warning('Please select a player');
      return;
    }

    Swal.fire({
      title: 'Linking player...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.post(
      APP_URL + '/backend/user/' + userId + '/players',
      { player_id: playerId }
    )
    .done(res => {
      Swal.close();
      toastr.success(res.message);
      $('#addProfileModal').modal('hide');
      location.reload();
    })
    .fail(xhr => {
      Swal.close();
      toastr.error(xhr.responseJSON?.message || 'Failed to link player');
    });
  });



  // ==========================================================
  // 🟦 EDIT USER PROFILE
  // ==========================================================
  $(document).on('submit', '#editUserForm', function (e) {
    e.preventDefault();

    Swal.fire({
      title: 'Saving...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.ajax({
      url: APP_URL + '/backend/user/' + userId,
      type: 'PUT',
      data: $(this).serialize(),
      success: res => {
        Swal.close();
        toastr.success(res.message);
        $('#editUser').modal('hide');
        location.reload();
      },
      error: xhr => {
        Swal.close();
        toastr.error(xhr.responseJSON?.message || 'Update failed');
      }
    });
  });
  $(document).on('click', '.unlink-player', function () {

  const btn     = $(this);
  const userId  = btn.data('user');
  const playerId = btn.data('player');
  const row     = btn.closest('.linked-player-row');

  Swal.fire({
    title: 'Unlink player?',
    text: 'This will remove the player from this profile.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, unlink'
  }).then(result => {

    if (!result.isConfirmed) return;

    $.ajax({
      url: APP_URL + `/backend/user/${userId}/players/${playerId}`,
      type: 'DELETE',
      success: res => {
        toastr.success(res.message);
        row.slideUp(200, () => row.remove());
      },
      error: xhr => {
        toastr.error(xhr.responseJSON?.message || 'Failed to unlink');
      }
    });

  });
});

  // ==========================================================
  // 🟦 CREATE EVENT (super-admin)
  // ==========================================================
  if ($('#addEvent').length) {

    // Select2 for admin picker inside modal
    $('#addEvent').on('shown.bs.modal', function () {
      $('.select2user').select2({
        dropdownParent: $('#addEvent'),
        placeholder: 'Select admin',
        width: '100%',
        allowClear: true
      });
    });

    // Quill editor for information field
    if ($('#full-editor').length) {
      var quillEditor = new Quill('#full-editor', {
        bounds: '#full-editor',
        placeholder: 'Type Something...',
        modules: {
          formula: true,
          toolbar: [
            [{ font: [] }, { size: [] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ color: [] }, { background: [] }],
            [{ header: '1' }, { header: '2' }, 'blockquote'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link', 'image'],
            ['clean']
          ]
        },
        theme: 'snow'
      });

      // AJAX submit
      $('#createEventButton').on('click', function () {
        var information = quillEditor.root.innerHTML;
        var data = $('#addEvent form').serialize() + '&info=' + encodeURIComponent(information);

        Swal.fire({
          title: 'Creating event...',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading()
        });

        $.ajax({
          url: APP_URL + '/events',
          method: 'POST',
          data: data,
          success: function (res) {
            Swal.close();
            toastr.success('Event created successfully');
            $('#addEvent').modal('hide');
            location.reload();
          },
          error: function (xhr) {
            Swal.close();
            toastr.error(xhr.responseJSON?.message || 'Failed to create event');
          }
        });
      });
    }
  }

});
</script>
@endsection


