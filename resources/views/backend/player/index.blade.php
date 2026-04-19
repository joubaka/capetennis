@extends('layouts/layoutMaster')

@section('title', 'Manage Players')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Page Header --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-user-check me-2"></i> Manage Players</h4>
      <p class="text-muted mb-0">View and manage all registered players</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('player.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Add Player
      </a>
      <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i> Back to Dashboard
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Players Table Card --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">All Players</h5>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" id="refreshTable">
          <i class="ti ti-refresh"></i> Refresh
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover datatable-players w-100">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Surname</th>
              <th>Email</th>
              <th>Cell</th>
              <th>Gender</th>
              <th>DOB</th>
              <th>Profile Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {{-- DataTables will populate --}}
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
'use strict';

$(function () {
  const CSRF = $('meta[name="csrf-token"]').attr('content');

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

  // Initialize DataTable
  var dtPlayers = $('.datatable-players').DataTable({
    processing: true,
    ajax: {
      url: '{{ route("player.index") }}',
      dataSrc: 'data'
    },
    columns: [
      { data: 'id', width: '50px' },
      { 
        data: 'name',
        render: function(data) {
          return '<strong>' + (data || '-') + '</strong>';
        }
      },
      { data: 'surname' },
      { 
        data: 'email',
        render: function(data) {
          return data ? '<a href="mailto:' + data + '">' + data + '</a>' : '-';
        }
      },
      { 
        data: 'cellNr',
        render: function(data) {
          return data || '-';
        }
      },
      { 
        data: 'gender',
        render: function(data) {
          if (!data) return '-';
          var badgeClass = data.toLowerCase() === 'male' ? 'bg-label-info' : 'bg-label-pink';
          if (data.toLowerCase() === 'female') badgeClass = 'bg-label-danger';
          return '<span class="badge ' + badgeClass + '">' + data + '</span>';
        }
      },
      {
        data: 'dateOfBirth',
        render: function(data) {
          if (!data) return '-';
          var date = new Date(data);
          var age = Math.floor((new Date() - date) / (365.25 * 24 * 60 * 60 * 1000));
          var ageLabel = age < 18 ? ' <span class="badge bg-info">Minor</span>' : '';
          return date.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' }) + 
                 ' <small class="text-muted">(' + age + 'y)</small>' + ageLabel;
        }
      },
      {
        data: 'profile_status',
        render: function(data, type, row) {
          if (!data) return '-';
          var icon = data.icon || 'ti-help';
          var badge = data.badge || 'secondary';
          var status = data.status || 'unknown';
          var lastUpdate = row.profile_updated_at ? new Date(row.profile_updated_at).toLocaleDateString('en-ZA') : 'Never';
          return `<span class="badge bg-${badge}" title="Last updated: ${lastUpdate}">
                    <i class="ti ${icon} me-1"></i>${status.charAt(0).toUpperCase() + status.slice(1)}
                  </span>`;
        }
      },
      {
        data: null,
        orderable: false,
        render: function(data) {
          return `
            <div class="d-flex gap-1">
              <a href="${APP_URL}/backend/player/${data.id}" class="btn btn-sm btn-icon btn-outline-primary" title="View Profile">
                <i class="ti ti-eye"></i>
              </a>
              <a href="${APP_URL}/backend/player/${data.id}/edit" class="btn btn-sm btn-icon btn-outline-warning" title="Edit">
                <i class="ti ti-pencil"></i>
              </a>
              <button class="btn btn-sm btn-icon btn-outline-danger delete-player-btn" 
                      data-id="${data.id}" 
                      data-name="${data.name || ''} ${data.surname || ''}" 
                      title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          `;
        }
      }
    ],
    order: [[0, 'desc']],
    pageLength: 25,
    responsive: true,
    language: {
      emptyTable: "No players found",
      zeroRecords: "No matching players found"
    }
  });

  // Refresh button
  $('#refreshTable').on('click', function() {
    dtPlayers.ajax.reload();
  });

  // Delete player
  $(document).on('click', '.delete-player-btn', function() {
    var playerId = $(this).data('id');
    var playerName = $(this).data('name');

    Swal.fire({
      title: 'Delete Player?',
      text: 'Are you sure you want to delete "' + playerName + '"? This will remove all associated data.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Yes, delete'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: APP_URL + '/backend/player/' + playerId,
          method: 'DELETE',
          success: function(res) {
            Swal.fire('Deleted', 'Player has been deleted', 'success');
            dtPlayers.ajax.reload();
          },
          error: function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete player', 'error');
          }
        });
      }
    });
  });
});
</script>
@endsection
