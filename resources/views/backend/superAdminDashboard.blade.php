@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Super Admin Dashboard')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')

{{-- ═══════════════ HEADER BANNER ═══════════════ --}}
<div class="row mb-4">
  <div class="col-12">
    <div class="card mb-0" style="background: linear-gradient(135deg, #696cff 0%, #567bfb 100%);">
      <div class="card-body d-flex align-items-center justify-content-between py-4">
        <div>
          <h4 class="text-white mb-1">
            <i class="ti ti-shield-chevron me-2"></i>Super Admin Dashboard
          </h4>
          <p class="text-white mb-0 opacity-75">Manage Cape Tennis system settings, agreements, and users</p>
        </div>
        <div>
          <span class="badge bg-white text-primary fs-6 px-3 py-2">
            <i class="ti ti-user me-1"></i>Super User
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════ TOP 6 STAT CARDS ═══════════════ --}}
<div class="row mb-4">

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color:#ebe9ff;">
            <i class="ti ti-users text-primary" style="font-size:1.5rem;"></i>
          </span>
        </div>
        <h3 class="mb-0">{{ number_format($totalUsers) }}</h3>
        <small class="text-muted">Total Users</small>
        <div class="mt-2 d-flex justify-content-center gap-2">
          <span class="badge bg-label-success small">+{{ $newUsersThisWeek }} this week</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color:#e8f8f0;">
            <i class="ti ti-user-check" style="font-size:1.5rem;color:#28c76f;"></i>
          </span>
        </div>
        <h3 class="mb-0">{{ number_format($totalPlayers) }}</h3>
        <small class="text-muted">Total Players</small>
        <div class="mt-2">
          <span class="badge bg-label-success small">+{{ $newPlayersThisWeek }} this week</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color:#e0f4fd;">
            <i class="ti ti-calendar-event" style="font-size:1.5rem;color:#00cfe8;"></i>
          </span>
        </div>
        <h3 class="mb-0">{{ number_format($totalEvents) }}</h3>
        <small class="text-muted">Total Events</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color:#fff4e0;">
            <i class="ti ti-calendar-time" style="font-size:1.5rem;color:#ff9f43;"></i>
          </span>
        </div>
        <h3 class="mb-0">{{ number_format($activeEvents) }}</h3>
        <small class="text-muted">Active Events</small>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color:#ffe0e0;">
            <i class="ti ti-ticket" style="font-size:1.5rem;color:#ea5455;"></i>
          </span>
        </div>
        <h3 class="mb-0">{{ number_format($totalRegistrations) }}</h3>
        <small class="text-muted">Registrations</small>
        <div class="mt-2">
          <span class="badge bg-label-danger small">+{{ $recentRegistrations }} last 30d</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-2 col-sm-4 col-6 mb-3">
    <div class="card h-100 text-center">
      <div class="card-body py-4">
        <div class="mb-3">
          <span class="badge rounded-circle p-3" style="background-color:#f0f0f0;">
            <i class="ti ti-file-check" style="font-size:1.5rem;color:#a0aab4;"></i>
          </span>
        </div>
        <h3 class="mb-0">—</h3>
        <small class="text-muted">CoC Accepted</small>
      </div>
    </div>
  </div>

</div>

{{-- ═══════════════ PLAYER PROFILE STATUS ═══════════════ --}}
<div class="mb-3">
  <h6 class="text-muted d-flex align-items-center">
    <i class="ti ti-user-circle me-1"></i> Player Profile Status
  </h6>
</div>
<div class="row mb-4">

  <div class="col-md-3 col-sm-6 mb-3">
    <div class="card h-100" style="border:2px solid #28c76f;">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <span class="badge rounded-circle p-3" style="background-color:#e8f8f0;">
          <i class="ti ti-circle-check" style="font-size:1.3rem;color:#28c76f;"></i>
        </span>
        <div>
          <h4 class="mb-0">{{ number_format($profileUpToDate) }}</h4>
          <small class="text-muted">Up to Date</small>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6 mb-3">
    <div class="card h-100" style="border:2px solid #ff9f43;">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <span class="badge rounded-circle p-3" style="background-color:#fff4e0;">
          <i class="ti ti-clock" style="font-size:1.3rem;color:#ff9f43;"></i>
        </span>
        <div>
          <h4 class="mb-0">{{ number_format($profileNeedsUpdate) }}</h4>
          <small class="text-muted">Needs Update</small>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6 mb-3">
    <div class="card h-100" style="border:2px solid #ea5455;">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <span class="badge rounded-circle p-3" style="background-color:#ffe0e0;">
          <i class="ti ti-alert-circle" style="font-size:1.3rem;color:#ea5455;"></i>
        </span>
        <div>
          <h4 class="mb-0">{{ number_format($profileIncomplete) }}</h4>
          <small class="text-muted">Incomplete</small>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-3 col-sm-6 mb-3">
    <div class="card h-100" style="border:2px solid #b0b8c1;">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <span class="badge rounded-circle p-3" style="background-color:#f0f0f0;">
          <i class="ti ti-user-off" style="font-size:1.3rem;color:#a0aab4;"></i>
        </span>
        <div>
          <h4 class="mb-0">{{ number_format($profileNeverUpdated) }}</h4>
          <small class="text-muted">Never Updated</small>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- ═══════════════ CoC AGREEMENTS + QUICK ACTIONS ═══════════════ --}}
<div class="row mb-4">

  <div class="col-md-6 mb-3">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="ti ti-file-text me-2 text-primary"></i>
          <h5 class="mb-0">Code of Conduct Agreements</h5>
        </div>
        <a href="{{ url('backend/agreements') }}" class="btn btn-primary btn-sm">
          <i class="ti ti-plus me-1"></i>New Agreement
        </a>
      </div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-4">
        <i class="ti ti-file-off text-muted mb-2" style="font-size:2.5rem;"></i>
        <p class="text-muted mb-0">Agreement management coming soon.</p>
      </div>
    </div>
  </div>

  <div class="col-md-6 mb-3">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center">
        <i class="ti ti-bolt me-2 text-warning"></i>
        <h5 class="mb-0">Quick Actions</h5>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ url('backend/user') }}" class="btn btn-outline-primary btn-sm">
            <i class="ti ti-users me-1"></i>Manage Users
          </a>
          <a href="{{ url('backend/player') }}" class="btn btn-outline-success btn-sm">
            <i class="ti ti-user-check me-1"></i>Manage Players
          </a>
          <a href="{{ url('backend/event') }}" class="btn btn-outline-info btn-sm">
            <i class="ti ti-calendar-event me-1"></i>Events
          </a>
          <a href="{{ url('backend/series') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-timeline me-1"></i>Series
          </a>
          <a href="{{ url('backend/league') }}" class="btn btn-outline-warning btn-sm">
            <i class="ti ti-trophy me-1"></i>League
          </a>
          <a href="{{ url('backend/settings') }}" class="btn btn-outline-dark btn-sm">
            <i class="ti ti-settings me-1"></i>Site Settings
          </a>
          <a href="{{ url('backend/eventPhoto') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-photo me-1"></i>Photos
          </a>
          <a href="{{ url('backend/refunds/bank') }}" class="btn btn-outline-danger btn-sm">
            <i class="ti ti-cash-banknote me-1"></i>Refunds
          </a>
        </div>

        {{-- Login today mini-summary --}}
        <hr>
        <div class="d-flex gap-3 mt-2">
          <div class="text-center">
            <div class="fw-bold text-success fs-5">{{ $loginAuditTodayCount }}</div>
            <small class="text-muted">Logins Today</small>
          </div>
          <div class="text-center">
            <div class="fw-bold text-danger fs-5">{{ $loginAuditFailedToday }}</div>
            <small class="text-muted">Failed Today</small>
          </div>
          <div class="text-center">
            <div class="fw-bold text-warning fs-5">{{ $pendingWithdrawals }}</div>
            <small class="text-muted">Withdrawals</small>
          </div>
          <div class="text-center">
            <div class="fw-bold text-info fs-5">{{ $newUsersThisMonth }}</div>
            <small class="text-muted">New Users (mo.)</small>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- ═══════════════ LOGIN AUDIT ═══════════════ --}}
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="ti ti-lock me-2 text-danger"></i>
          <h5 class="mb-0">Login Audit <span class="badge bg-label-secondary ms-1">Last 50</span></h5>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped w-100 mb-0" id="dt-login-audit">
            <thead>
              <tr>
                <th>Date / Time</th>
                <th>User</th>
                <th>Email</th>
                <th>IP Address</th>
                <th>Status</th>
                <th>Logout At</th>
                <th>User Agent</th>
              </tr>
            </thead>
            <tbody>
              @forelse($loginAuditLogs as $log)
                <tr>
                  <td>{{ $log->login_at ? \Carbon\Carbon::parse($log->login_at)->format('d M Y H:i') : '—' }}</td>
                  <td>{{ $log->name ?? '—' }}</td>
                  <td>{{ $log->email ?? '—' }}</td>
                  <td><code>{{ $log->ip_address ?? '—' }}</code></td>
                  <td>
                    @if($log->login_successful)
                      <span class="badge bg-success">Success</span>
                    @else
                      <span class="badge bg-danger">Failed</span>
                    @endif
                  </td>
                  <td>{{ $log->logout_at ? \Carbon\Carbon::parse($log->logout_at)->format('d M Y H:i') : '—' }}</td>
                  <td>
                    <span class="text-truncate d-inline-block" style="max-width:180px;" title="{{ $log->user_agent }}">
                      {{ $log->user_agent ?? '—' }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-3">No login records found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ═══════════════ ACTIVITY LOG ═══════════════ --}}
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="ti ti-history me-2 text-primary"></i>
          <h5 class="mb-0">Activity Log <span class="badge bg-label-secondary ms-1">Last 100</span></h5>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="small mb-0">Filter</label>
          <select id="sa-activity-filter" class="form-select form-select-sm" style="width:auto;">
            <option value="">All</option>
            @foreach($logNames as $ln)
              <option value="{{ $ln }}">{{ $ln }}</option>
            @endforeach
          </select>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="sa-activity-toggle" checked>
            <label class="form-check-label small" for="sa-activity-toggle">Grouped</label>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">

          {{-- Grouped table --}}
          <table class="table table-hover table-striped w-100 mb-0" id="dt-activity">
            <thead>
              <tr>
                <th>Last Active</th>
                <th>User</th>
                <th>Actions</th>
                <th>Last Action</th>
                <th>Details</th>
                <th class="d-none">Log Names</th>
              </tr>
            </thead>
            <tbody>
              @foreach($activityByUser as $row)
                <tr>
                  <td>{{ optional($row->last_at)->format('d M Y H:i') ?? '—' }}</td>
                  <td>{{ $row->causer?->userName ?? $row->causer?->name ?? 'System' }}</td>
                  <td><span class="badge bg-label-primary">{{ $row->count }}</span></td>
                  <td>{{ $row->example_description ?? '—' }}</td>
                  <td>
                    @if(!empty($row->properties) && count((array)$row->properties))
                      <button class="btn btn-xs btn-outline-secondary" type="button"
                              data-bs-toggle="popover" data-bs-trigger="focus" data-bs-html="true"
                              data-bs-content="@foreach((array)$row->properties as $key => $val)<strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}<br>@endforeach">
                        <i class="ti ti-info-circle"></i>
                      </button>
                    @else —
                    @endif
                  </td>
                  <td class="d-none">{{ implode(',', $row->log_names ?? []) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>

          {{-- Raw table (hidden by default) --}}
          <table class="table table-hover table-striped w-100 mb-0 d-none" id="dt-activity-raw">
            <thead>
              <tr>
                <th>Date</th>
                <th>User</th>
                <th>Log</th>
                <th>Action</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              @foreach($activityLogs as $log)
                <tr>
                  <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                  <td>{{ $log->causer?->userName ?? $log->causer?->name ?? 'System' }}</td>
                  <td>{{ $log->log_name }}</td>
                  <td>{{ $log->description }}</td>
                  <td>
                    @if($log->properties && $log->properties->count())
                      <button class="btn btn-xs btn-outline-secondary" type="button"
                              data-bs-toggle="popover" data-bs-trigger="focus" data-bs-html="true"
                              data-bs-content="@foreach($log->properties as $key => $val)<strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}<br>@endforeach">
                        <i class="ti ti-info-circle"></i>
                      </button>
                    @else —
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
$(function () {

  // ── Login Audit DataTable ──────────────────────────────────────
  var dtLogin = $('#dt-login-audit');
  if (dtLogin.length) {
    dtLogin.DataTable({
      ordering: true,
      order: [[0, 'desc']],
      pageLength: 25,
      columnDefs: [
        { targets: 6, orderable: false, searchable: true }
      ]
    });
  }

  // ── Activity Log DataTables ────────────────────────────────────
  var dtAct = $('#dt-activity');
  var dtActRaw = null;

  if (dtAct.length) {
    dtAct.DataTable({
      ordering: true,
      order: [[0, 'desc']],
      pageLength: 25,
      columnDefs: [
        { targets: 4, orderable: false, searchable: false },
        { targets: 5, visible: false }
      ],
      drawCallback: function () {
        $(this.api().table().body()).find('[data-bs-toggle="popover"]').each(function () {
          if (typeof bootstrap !== 'undefined') new bootstrap.Popover(this);
        });
      }
    });
  }

  var dtActRawEl = $('#dt-activity-raw');
  if (dtActRawEl.length) {
    dtActRaw = dtActRawEl.DataTable({
      ordering: true,
      order: [[0, 'desc']],
      pageLength: 25,
      columnDefs: [
        { targets: 4, orderable: false, searchable: false }
      ],
      drawCallback: function () {
        $(this.api().table().body()).find('[data-bs-toggle="popover"]').each(function () {
          if (typeof bootstrap !== 'undefined') new bootstrap.Popover(this);
        });
      }
    });
  }

  // Filter by log name
  $('#sa-activity-filter').on('change', function () {
    var val = $(this).val();
    try {
      var t = $('#dt-activity').DataTable();
      val ? t.column(5).search(val).draw() : t.column(5).search('').draw();
    } catch(e) {}
    if (dtActRaw) {
      val ? dtActRaw.column(2).search('^' + val + '$', true, false).draw() : dtActRaw.column(2).search('').draw();
    }
  });

  // Toggle grouped / raw
  $('#sa-activity-toggle').on('change', function () {
    if ($(this).is(':checked')) {
      $('#dt-activity').removeClass('d-none');
      dtActRawEl.addClass('d-none');
    } else {
      $('#dt-activity').addClass('d-none');
      dtActRawEl.removeClass('d-none');
    }
  });

});
</script>
@endsection
