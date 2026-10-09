@extends('layouts.backend')

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
<div class="container-xxl flex-grow-1 container-p-y player-directory-admin">

  {{-- Page Header --}}
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-user-check me-2"></i> Manage Players</h4>
      <p class="text-muted mb-0">Search registered players, open a profile, or edit its details. Results load one page at a time.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
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
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-sm btn-outline-primary" id="refreshTable">
          <i class="ti ti-refresh"></i> Refresh
        </button>
      </div>
    </div>
    <div class="card-body">
      <p class="small text-muted">Search by name, surname, email or cell number. Use the page controls to load more results.</p>
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
<style>
.player-directory-admin :is(.btn,.page-link,input,select) { min-height:44px !important; }
.player-directory-admin .btn-icon { min-width:44px !important; }
.player-directory-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }
.player-directory-admin td { overflow-wrap:anywhere; white-space:normal; }
.player-directory-admin .dt-search { margin-bottom:1rem; }
@media(max-width:575px) { .player-directory-admin .dt-layout-row { flex-wrap:wrap; gap:.75rem; } .player-directory-admin .dt-search input { width:100%; margin-left:0; } }
</style>
@endsection

@section('page-script')
<script>
'use strict';

$(function () {
  const CSRF = $('meta[name="csrf-token"]').attr('content');

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

  const safeText = value => $('<div>').text(value == null ? '-' : String(value)).html().replaceAll('"', '&quot;').replaceAll("'", '&#39;');

  // Initialize DataTable
  var dtPlayers = $('.datatable-players').DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 300,
    ajax: {
      url: '{{ route("player.data") }}',
      dataSrc: 'data'
    },
    columns: [
      { data: 'id', width: '50px' },
      {
        data: 'name',
        render: function(data, type, row) {
          if (type !== 'display') return data;
          const safeName = $('<div>').text(data || '-').html();
          @if(auth()->user()->hasRole('super-user'))
          return '<a href="{{ url('backend/player-performance/players') }}/' + encodeURIComponent(row.id) + '"><strong>' + safeName + '</strong>' + (row.ability_badge_html || '') + '</a>';
          @else
          return '<strong>' + safeName + '</strong>';
          @endif
        }
      },
      { data: 'surname', render: $.fn.dataTable.render.text() },
      {
        data: 'email',
        render: function(data) {
          return data ? $('<a>').attr('href', 'mailto:' + String(data)).text(data)[0].outerHTML : '-';
        }
      },
      {
        data: 'cellNr',
        render: function(data) {
          return safeText(data || '-');
        }
      },
      {
        data: 'gender',
        render: function(data) {
          if (!data) return '-';
          var normalized = String(data).toLowerCase();
          var label = normalized === '1' || normalized === 'male' ? 'Male'
            : (normalized === '2' || normalized === 'female' ? 'Female' : String(data));
          var badgeClass = label === 'Male' ? 'bg-label-info'
            : (label === 'Female' ? 'bg-label-danger' : 'bg-label-secondary');
          return '<span class="badge ' + badgeClass + '">' + safeText(label) + '</span>';
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
        orderable: false,
        searchable: false,
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
        searchable: false,
        render: function(data) {
          const playerLabel = safeText(`${data.name || ''} ${data.surname || ''}`);
          const playerId = encodeURIComponent(data.id);
          return `
            <div class="d-flex gap-1">
              <a href="${APP_URL}/backend/player/${playerId}" class="btn btn-sm btn-icon btn-outline-primary" aria-label="View profile for ${playerLabel}" title="View Profile">
                <i class="ti ti-eye"></i>
              </a>
              <a href="${APP_URL}/backend/player/${playerId}/edit" class="btn btn-sm btn-icon btn-outline-warning" aria-label="Edit ${playerLabel}" title="Edit">
                <i class="ti ti-pencil"></i>
              </a>
              <button class="btn btn-sm btn-icon btn-outline-danger delete-player-btn"
                      data-id="${playerId}"
                      data-name="${playerLabel}"
                      aria-label="Delete ${playerLabel}" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          `;
        }
      }
    ],
    order: [[0, 'desc']],
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    responsive: true,
    language: {
      search: "Find a player:",
      searchPlaceholder: "Name, surname, email or cell",
      lengthMenu: "Show _MENU_ players",
      info: "Showing _START_–_END_ of _TOTAL_ players",
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
      text: 'Are you sure you want to delete "' + playerName + '"? This uses the existing player deletion process.',
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
