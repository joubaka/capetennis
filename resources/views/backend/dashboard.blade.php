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
         <div class="linked-player-row mb-3 pb-2 border-bottom d-flex justify-content-between">

            <div>
              <a href="{{ route('backend.player.profile', $player->id) }}"
                 class="btn btn-sm btn-outline-primary fw-semibold">
                {{ $player->name }} {{ $player->surname }}
              </a>
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
        @empty
          <div class="alert alert-info mb-0">
            <i class="ti ti-info-circle me-1"></i> No players linked yet.
          </div>
        @endforelse
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

@include('_partials/_modals/modal-upgrade-plan')
@include('_partials/_modals/modal-edit-user')
@endsection

  


@section('page-script')
<script>
'use strict';

$(function () {

  console.log('✅ Dashboard script loaded');

  const CSRF   = $('meta[name="csrf-token"]').attr('content');
  const userId = $('#user').val();
  const PLAYER_URL = APP_URL + '/backend/player';

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF }
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

});
</script>
@endsection


