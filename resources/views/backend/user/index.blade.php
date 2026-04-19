@extends('layouts/layoutMaster')

@section('title', 'Manage Users')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Page Header --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-users me-2"></i> Manage Users</h4>
      <p class="text-muted mb-0">View and manage all registered users</p>
    </div>
    <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary">
      <i class="ti ti-arrow-left me-1"></i> Back to Dashboard
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Users Table Card --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">All Users</h5>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" id="refreshTable">
          <i class="ti ti-refresh"></i> Refresh
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover datatable-users w-100">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Roles</th>
              <th>Created</th>
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

{{-- Add Role Modal --}}
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-user-plus me-2"></i> Add Role to User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="addRoleUserId">
        <p>Adding role to: <strong id="addRoleUserName"></strong></p>
        <div class="mb-3">
          <label class="form-label">Select Role</label>
          <select id="roleToAdd" class="form-select">
            <option value="">-- Select Role --</option>
            @foreach($roles as $role)
              <option value="{{ $role->name }}">{{ $role->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmAddRole">Add Role</button>
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
  var dtUsers = $('.datatable-users').DataTable({
    processing: true,
    ajax: {
      url: '{{ route("user.index") }}',
      dataSrc: 'data'
    },
    columns: [
      { data: 'id', width: '50px' },
      { 
        data: null,
        render: function(data) {
          var name = data.userName || data.name || '';
          var surname = data.userSurname || '';
          return '<strong>' + name + ' ' + surname + '</strong>';
        }
      },
      { data: 'email' },
      { 
        data: null,
        render: function(data) {
          if (data.roles && data.roles.length) {
            return data.roles.map(function(r) {
              var badgeClass = 'bg-label-primary';
              if (r.name === 'super-user') badgeClass = 'bg-label-danger';
              if (r.name === 'admin') badgeClass = 'bg-label-warning';
              return '<span class="badge ' + badgeClass + ' me-1">' + r.name + '</span>';
            }).join('');
          }
          return '<span class="text-muted">No roles</span>';
        }
      },
      {
        data: 'created_at',
        render: function(data) {
          if (!data) return '-';
          var date = new Date(data);
          return date.toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' });
        }
      },
      {
        data: null,
        orderable: false,
        render: function(data) {
          return `
            <div class="d-flex gap-1">
              <a href="${APP_URL}/backend/user/${data.id}" class="btn btn-sm btn-icon btn-outline-primary" title="View">
                <i class="ti ti-eye"></i>
              </a>
              <button class="btn btn-sm btn-icon btn-outline-success add-role-btn" 
                      data-id="${data.id}" 
                      data-name="${data.name || ''}" 
                      title="Add Role">
                <i class="ti ti-user-plus"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-outline-danger delete-user-btn" 
                      data-id="${data.id}" 
                      data-name="${data.name || ''}" 
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
      emptyTable: "No users found",
      zeroRecords: "No matching users found"
    }
  });

  // Refresh button
  $('#refreshTable').on('click', function() {
    dtUsers.ajax.reload();
  });

  // Add Role button click
  $(document).on('click', '.add-role-btn', function() {
    var userId = $(this).data('id');
    var userName = $(this).data('name');
    $('#addRoleUserId').val(userId);
    $('#addRoleUserName').text(userName);
    $('#roleToAdd').val('');
    $('#addRoleModal').modal('show');
  });

  // Confirm Add Role
  $('#confirmAddRole').on('click', function() {
    var userId = $('#addRoleUserId').val();
    var role = $('#roleToAdd').val();

    if (!role) {
      Swal.fire('Error', 'Please select a role', 'warning');
      return;
    }

    $.ajax({
      url: APP_URL + '/backend/user/' + userId + '/add-role',
      method: 'POST',
      data: { role: role },
      success: function(res) {
        $('#addRoleModal').modal('hide');
        Swal.fire('Success', res.message, 'success');
        dtUsers.ajax.reload();
      },
      error: function(xhr) {
        Swal.fire('Error', xhr.responseJSON?.message || 'Failed to add role', 'error');
      }
    });
  });

  // Delete user
  $(document).on('click', '.delete-user-btn', function() {
    var userId = $(this).data('id');
    var userName = $(this).data('name');

    Swal.fire({
      title: 'Delete User?',
      text: 'Are you sure you want to delete "' + userName + '"? This cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      confirmButtonText: 'Yes, delete'
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: APP_URL + '/backend/user/' + userId,
          method: 'DELETE',
          success: function(res) {
            Swal.fire('Deleted', res.message, 'success');
            dtUsers.ajax.reload();
          },
          error: function(xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete user', 'error');
          }
        });
      }
    });
  });
});
</script>
@endsection
