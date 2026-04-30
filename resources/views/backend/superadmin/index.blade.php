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
            <i class="ti ti-user me-1"></i>{{ auth()->user()->name }}
          </span>
        </div>
      </div>
    </div>
  </div>
</div>
@php $totalPending = $withdrawalPendingRefunds->count() + $withdrawalPendingTeamRefunds->count(); @endphp

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
          <span class="badge rounded-circle p-3" style="background-color:#e8f8f0;">
            <i class="ti ti-file-check" style="font-size:1.5rem;color:#28c76f;"></i>
          </span>
        </div>
        <h3 class="mb-0">{{ number_format($agreementStats['total_acceptances']) }}</h3>
        <small class="text-muted">CoC Accepted</small>
        @if($agreementStats['active_agreement'])
          <div class="mt-2">
            <span class="badge bg-label-warning small">{{ $agreementStats['pending_players'] }} pending</span>
          </div>
        @endif
      </div>
    </div>
  </div>

</div>


{{-- ═══════════════ MAIN TABS ═══════════════ --}}
<div class="card">
  <div class="card-header p-0 border-bottom-0">
    <ul class="nav nav-tabs card-header-tabs px-3 pt-2 flex-wrap" id="sa-main-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="sa-tab-overview" data-bs-toggle="tab"
                data-bs-target="#sa-pane-overview" type="button" role="tab">
          <i class="ti ti-layout-dashboard me-1 text-primary"></i>Overview
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-finance" data-bs-toggle="tab"
                data-bs-target="#sa-pane-finance" type="button" role="tab">
          <i class="ti ti-report-money me-1 text-warning"></i>Finance
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-withdrawals" data-bs-toggle="tab"
                data-bs-target="#sa-pane-withdrawals" type="button" role="tab">
          <i class="ti ti-cash-banknote me-1 text-danger"></i>Withdrawals
          @if($totalPending > 0)
            <span class="badge bg-danger ms-1">{{ $totalPending }}</span>
          @endif
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-agreements" data-bs-toggle="tab"
                data-bs-target="#sa-pane-agreements" type="button" role="tab">
          <i class="ti ti-file-certificate me-1 text-primary"></i>Agreements
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-players" data-bs-toggle="tab"
                data-bs-target="#sa-pane-players" type="button" role="tab">
          <i class="ti ti-alert-triangle me-1 text-warning"></i>Players
          @if($playersNeedingAttention->count() > 0)
            <span class="badge bg-warning text-dark ms-1">{{ $playersNeedingAttention->count() }}</span>
          @endif
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-audit" data-bs-toggle="tab"
                data-bs-target="#sa-pane-audit" type="button" role="tab">
          <i class="ti ti-history me-1 text-secondary"></i>Audit &amp; Activity
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-settings" data-bs-toggle="tab"
                data-bs-target="#sa-pane-settings" type="button" role="tab">
          <i class="ti ti-settings me-1 text-secondary"></i>Settings
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sa-tab-wallets" data-bs-toggle="tab"
                data-bs-target="#sa-pane-wallets" type="button" role="tab">
          <i class="ti ti-wallet me-1 text-success"></i>Wallets
        </button>
      </li>
    </ul>
  </div>

  <div class="tab-content">

    {{-- ══ TAB: OVERVIEW ══ --}}
    <div class="tab-pane fade show active p-3" id="sa-pane-overview" role="tabpanel">

      <h6 class="text-muted d-flex align-items-center mb-3">
        <i class="ti ti-user-circle me-1"></i> Player Profile Status
      </h6>
      <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
          <div class="card h-100" style="border:2px solid #28c76f;">
            <div class="card-body d-flex align-items-center gap-3 py-3">
              <span class="badge rounded-circle p-3" style="background-color:#e8f8f0;">
                <i class="ti ti-circle-check" style="font-size:1.3rem;color:#28c76f;"></i>
              </span>
              <div>
                <h4 class="mb-0">{{ number_format($profileStats['up_to_date']) }}</h4>
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
                <h4 class="mb-0">{{ number_format($profileStats['needs_update']) }}</h4>
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
                <h4 class="mb-0">{{ number_format($profileStats['incomplete']) }}</h4>
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
                <h4 class="mb-0">{{ number_format($profileStats['never_updated']) }}</h4>
                <small class="text-muted">Never Updated</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
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
                <button type="button" class="btn btn-outline-dark btn-sm"
                        onclick="bootstrap.Tab.getOrCreateInstance(document.getElementById('sa-tab-settings')).show()">
                  <i class="ti ti-settings me-1"></i>Site Settings
                </button>
                <a href="{{ url('backend/eventPhoto') }}" class="btn btn-outline-secondary btn-sm">
                  <i class="ti ti-photo me-1"></i>Photos
                </a>
                <a href="{{ route('admin.refunds.bank.index') }}" class="btn btn-outline-danger btn-sm">
                  <i class="ti ti-cash-banknote me-1"></i>Refunds
                </a>
              </div>
              <hr>
              <div class="d-flex gap-3 mt-2 flex-wrap">
                <div class="text-center">
                  <div class="fw-bold text-success fs-5">{{ $loginAuditTodayCount }}</div>
                  <small class="text-muted">Logins Today</small>
                </div>
                <div class="text-center">
                  <div class="fw-bold text-danger fs-5">{{ $loginAuditFailedToday }}</div>
                  <small class="text-muted">Failed Today</small>
                </div>
                <div class="text-center">
                  <button type="button" class="btn btn-link p-0 text-decoration-none"
                          onclick="bootstrap.Tab.getOrCreateInstance(document.getElementById('sa-tab-withdrawals')).show()">
                    <div class="fw-bold text-warning fs-5">{{ $totalPending }}</div>
                    <small class="text-muted">Pending Withdrawals</small>
                  </button>
                </div>
                <div class="text-center">
                  <div class="fw-bold text-info fs-5">{{ $newUsersThisMonth }}</div>
                  <small class="text-muted">New Users (mo.)</small>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <h6 class="mb-1"><i class="ti ti-headset me-2"></i>Support</h6>
              <a href="mailto:support@capetennis.co.za" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-mail me-1"></i>support@capetennis.co.za
              </a>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <i class="ti ti-user-plus me-2"></i>
                <h5 class="mb-0">Recent Users</h5>
              </div>
              <a href="{{ url('backend/user') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <ul class="list-group list-group-flush">
              @forelse($recentUsers as $user)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <a href="{{ route('user.show', $user) }}" class="fw-bold text-primary">{{ $user->name }}</a>
                    <small class="d-block text-muted">{{ $user->email }}</small>
                  </div>
                  <small class="text-muted text-nowrap">{{ $user->created_at->diffForHumans() }}</small>
                </li>
              @empty
                <li class="list-group-item text-center text-muted py-3">No users yet.</li>
              @endforelse
            </ul>
          </div>
        </div>
      </div>

    </div>{{-- /overview --}}


    {{-- ══ TAB: FINANCE ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-finance" role="tabpanel">
      {{-- Year filter --}}
      <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="text-muted small"><i class="ti ti-filter me-1"></i>Year:</span>
        @foreach($financeYears as $yr)
          <a href="{{ request()->fullUrlWithQuery(['finance_year' => $yr, 'tab' => 'finance']) }}"
             class="btn btn-sm {{ $yr == $financeYear ? 'btn-warning' : 'btn-outline-secondary' }}">
            {{ $yr }}
          </a>
        @endforeach
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
          <div class="card border-start border-success border-3 h-100">
            <div class="card-body">
              <small class="text-muted d-block mb-1"><i class="ti ti-cash me-1 text-success"></i>Total Gross Income</small>
              <h5 class="mb-0 text-success">R {{ number_format($financeSummary['total_gross'], 2) }}</h5>
              <small class="text-muted">All registration payments in {{ $financeYear }}</small>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card border-start border-primary border-3 h-100">
            <div class="card-body">
              <small class="text-muted d-block mb-1"><i class="ti ti-coin me-1 text-primary"></i>Net Income</small>
              <h5 class="mb-0">R {{ number_format($financeSummary['total_income'], 2) }}</h5>
              <small class="text-muted">After PayFast &amp; Cape Tennis fees</small>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="card border-start border-secondary border-3 h-100">
            <div class="card-body">
              <small class="text-muted d-block mb-1"><i class="ti ti-users me-1 text-secondary"></i>Total Entries</small>
              <h5 class="mb-0">{{ number_format($financeSummary['total_entries']) }}</h5>
              <small class="text-muted">Across all {{ $financeYear }} events</small>
            </div>
          </div>
        </div>
      </div>
      <div class="card mb-0">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <i class="ti ti-calendar-stats me-2 text-warning"></i>
            <h5 class="mb-0">Per-Event Financial Summary – {{ $financeYear }}</h5>
          </div>
          <a href="{{ route('superadmin.finances') }}" class="btn btn-sm btn-outline-warning">
            <i class="ti ti-report-money me-1"></i>Full Financial Dashboard
          </a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Event</th>
                <th>Date</th>
                <th class="text-end">Gross Income</th>
                <th class="text-end">Net Income</th>
                <th class="text-center">Entries</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse($financeByEvent as $row)
                <tr>
                  <td>
                    <a href="{{ route('superadmin.finances.event', $row['event']) }}" class="fw-semibold text-primary">
                      {{ $row['event']->name }}
                    </a>
                  </td>
                  <td>
                    <small class="text-muted">
                      {{ $row['event']->start_date ? \Carbon\Carbon::parse($row['event']->start_date)->format('d M Y') : '—' }}
                    </small>
                  </td>
                  <td class="text-end text-success">R {{ number_format($row['total_gross'], 2) }}</td>
                  <td class="text-end">R {{ number_format($row['total_income'], 2) }}</td>
                  <td class="text-center">{{ number_format($row['total_entries']) }}</td>
                  <td>
                    <a href="{{ route('superadmin.finances.event', $row['event']) }}" class="btn btn-icon btn-sm btn-outline-warning" title="View Finances">
                      <i class="ti ti-report-money"></i>
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted py-3">No events found.</td>
                </tr>
              @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="2">Totals</td>
                <td class="text-end text-success">R {{ number_format($financeSummary['total_gross'], 2) }}</td>
                <td class="text-end">R {{ number_format($financeSummary['total_income'], 2) }}</td>
                <td class="text-center">{{ number_format($financeSummary['total_entries']) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>{{-- /finance --}}


    {{-- ══ TAB: WITHDRAWALS ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-withdrawals" role="tabpanel">

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif
      @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          @foreach($errors->all() as $error) {{ $error }}<br>@endforeach
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <div class="d-flex gap-2 flex-wrap mb-4">
        <span class="badge bg-danger fs-6 px-3 py-2">
          <i class="ti ti-clock me-1"></i>
          {{ $withdrawalPendingRefunds->count() + $withdrawalPendingTeamRefunds->count() }} Pending Bank
        </span>
        <span class="badge bg-success fs-6 px-3 py-2">
          <i class="ti ti-check me-1"></i>
          {{ $withdrawalCompletedRefunds->count() + $withdrawalCompletedTeamRefunds->count() }} Completed Bank
        </span>
        <span class="badge bg-info fs-6 px-3 py-2">
          <i class="ti ti-wallet me-1"></i>
          {{ $withdrawalWalletRefunds->count() }} Wallet Withdrawals
        </span>
        <a href="{{ route('admin.refunds.bank.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="ti ti-external-link me-1"></i>Full Refunds Page
        </a>
        <a href="{{ route('superadmin.orphans.index') }}" class="btn btn-sm btn-outline-danger ms-2">
          <i class="ti ti-alert-triangle me-1"></i>Orphaned Registrations
        </a>
      </div>

      <ul class="nav nav-pills mb-3" id="sa-withdrawal-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="sa-wtab-pending" data-bs-toggle="pill"
                  data-bs-target="#sa-wpane-pending" type="button" role="tab">
            <i class="ti ti-clock me-1"></i>Pending Bank
            @if($withdrawalPendingRefunds->count() + $withdrawalPendingTeamRefunds->count() > 0)
              <span class="badge bg-danger ms-1">
                {{ $withdrawalPendingRefunds->count() + $withdrawalPendingTeamRefunds->count() }}
              </span>
            @endif
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="sa-wtab-completed" data-bs-toggle="pill"
                  data-bs-target="#sa-wpane-completed" type="button" role="tab">
            <i class="ti ti-check me-1"></i>Completed Bank
            <span class="badge bg-label-success ms-1">
              {{ $withdrawalCompletedRefunds->count() + $withdrawalCompletedTeamRefunds->count() }}
            </span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="sa-wtab-wallet" data-bs-toggle="pill"
                  data-bs-target="#sa-wpane-wallet" type="button" role="tab">
            <i class="ti ti-wallet me-1"></i>Wallet Withdrawals
            <span class="badge bg-label-info ms-1">{{ $withdrawalWalletRefunds->count() }}</span>
          </button>
        </li>
      </ul>

      <div class="tab-content">

        <div class="tab-pane fade show active" id="sa-wpane-pending" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="dt-w-pending">
              <thead class="table-light">
                <tr>
                  <th>Ref</th>
                  <th>Player</th>
                  <th>Event</th>
                  <th>Amount</th>
                  <th>Account Name</th>
                  <th>Bank</th>
                  <th>Submitted</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($withdrawalPendingRefunds as $refund)
                  <tr>
                    <td><span class="badge bg-label-secondary">REG-{{ $refund->id }}</span></td>
                    <td>{{ $refund->display_name }}</td>
                    <td><small>{{ optional(optional($refund->categoryEvent)->event)->name ?? '—' }}</small></td>
                    <td class="text-danger fw-semibold">R{{ number_format($refund->refund_net, 2) }}</td>
                    <td>{{ $refund->refund_account_name ?? '—' }}</td>
                    <td>{{ $refund->refund_bank_name ?? '—' }}</td>
                    <td><small class="text-muted">{{ $refund->updated_at?->format('d M Y') }}</small></td>
                    <td>
                      <div class="d-flex gap-1">
                        <a href="{{ route('admin.registration.refunds.bank.show', $refund) }}"
                           class="btn btn-icon btn-sm btn-outline-primary" title="View">
                          <i class="ti ti-eye"></i>
                        </a>
                        <form method="POST" action="{{ route('admin.refunds.bank.complete', $refund) }}"
                              onsubmit="return confirm('Mark this bank refund as paid?');">
                          @csrf
                          <button class="btn btn-icon btn-sm btn-success" title="Mark Paid">
                            <i class="ti ti-check"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                @endforelse
                @foreach($withdrawalPendingTeamRefunds as $t)
                  <tr>
                    <td><span class="badge bg-label-warning">TEAM-{{ $t->id }}</span></td>
                    <td>{{ optional($t->player)->name ?? 'Unknown Player' }}</td>
                    <td><small>{{ optional($t->event)->name ?? '—' }}</small></td>
                    <td class="text-danger fw-semibold">R{{ number_format($t->refund_net, 2) }}</td>
                    <td>{{ $t->refund_account_name ?? '—' }}</td>
                    <td>{{ $t->refund_bank_name ?? '—' }}</td>
                    <td><small class="text-muted">{{ $t->updated_at?->format('d M Y') }}</small></td>
                    <td>
                      <form method="POST" action="{{ route('admin.refunds.bank.complete.team', $t) }}"
                            onsubmit="return confirm('Mark this team bank refund as paid?');">
                        @csrf
                        <button class="btn btn-icon btn-sm btn-success" title="Mark Paid">
                          <i class="ti ti-check"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach

              </tbody>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="sa-wpane-completed" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="dt-w-completed">
              <thead class="table-light">
                <tr>
                  <th>Ref</th>
                  <th>Player</th>
                  <th>Event</th>
                  <th>Amount</th>
                  <th>Bank</th>
                  <th>Completed</th>
                </tr>
              </thead>
              <tbody>
                @forelse($withdrawalCompletedRefunds as $refund)
                  <tr>
                    <td><span class="badge bg-label-secondary">REG-{{ $refund->id }}</span></td>
                    <td>{{ $refund->display_name }}</td>
                    <td><small>{{ optional(optional($refund->categoryEvent)->event)->name ?? '—' }}</small></td>
                    <td class="text-success fw-semibold">R{{ number_format($refund->refund_net, 2) }}</td>
                    <td>{{ $refund->refund_bank_name ?? '—' }}</td>
                    <td><small class="text-muted">{{ $refund->refunded_at?->format('d M Y H:i') ?? '—' }}</small></td>
                  </tr>
                @empty
                @endforelse
                @foreach($withdrawalCompletedTeamRefunds as $t)
                  <tr>
                    <td><span class="badge bg-label-warning">TEAM-{{ $t->id }}</span></td>
                    <td>{{ optional($t->player)->name ?? 'Unknown Player' }}</td>
                    <td><small>{{ optional($t->event)->name ?? '—' }}</small></td>
                    <td class="text-success fw-semibold">R{{ number_format($t->refund_net, 2) }}</td>
                    <td>{{ $t->refund_bank_name ?? '—' }}</td>
                    <td><small class="text-muted">{{ optional($t->refunded_at)->format('d M Y H:i') ?? '—' }}</small></td>
                  </tr>
                @endforeach

              </tbody>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="sa-wpane-wallet" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover mb-0" id="dt-w-wallet">
              <thead class="table-light">
                <tr>
                  <th>Ref</th>
                  <th>Player</th>
                  <th>Event</th>
                  <th>Refund Method</th>
                  <th>Status</th>
                  <th>Reason</th>
                  <th>Withdrawn At</th>
                </tr>
              </thead>
              <tbody>
                @foreach($withdrawalWalletRefunds as $refund)
                  @php
                    $refundEvent = optional($refund->categoryEvent)->event;
                    $withdrawnAt = $refund->withdrawn_at;
                    if ($refund->refund_status === 'completed') {
                      $reason = '<span class="badge bg-success">Refunded to wallet</span>';
                    } elseif (!$refund->is_paid) {
                      $reason = '<span class="badge bg-label-secondary">Not paid</span>';
                    } elseif ($refundEvent && $withdrawnAt && $withdrawnAt->gt($refundEvent->withdrawalCloseAt())) {
                      $deadline = $refundEvent->withdrawalCloseAt()->format('d M Y H:i');
                      $reason = '<span class="badge bg-danger" title="Deadline: ' . $deadline . '">Late withdrawal</span>';
                    } elseif ($refund->refund_status === 'pending') {
                      $reason = '<span class="badge bg-warning text-dark">Awaiting processing</span>';
                    } elseif (in_array($refund->refund_status, [null, '', 'not_refunded'])) {
                      $reason = '<span class="badge bg-label-warning">Refund not chosen</span>';
                    } else {
                      $reason = '—';
                    }
                  @endphp
                  <tr>
                    <td><span class="badge bg-label-info">REG-{{ $refund->id }}</span></td>
                    <td>{{ $refund->display_name }}</td>
                    <td><small>{{ $refundEvent->name ?? '—' }}</small></td>
                    <td><span class="badge bg-label-info">{{ $refund->refund_method ?? 'wallet' }}</span></td>
                    <td>
                      @if($refund->refund_status === 'completed')
                        <span class="badge bg-success">Completed</span>
                      @elseif($refund->refund_status === 'pending')
                        <span class="badge bg-warning text-dark">Pending</span>
                      @else
                        <span class="badge bg-label-secondary">{{ $refund->refund_status ?? 'N/A' }}</span>
                      @endif
                    </td>
                    <td>{!! $reason !!}</td>
                    <td><small class="text-muted">{{ $withdrawnAt?->format('d M Y H:i') ?? '—' }}</small></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>{{-- /withdrawals --}}


    {{-- ══ TAB: AGREEMENTS ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-agreements" role="tabpanel">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="ti ti-file-certificate me-2 text-primary"></i>Code of Conduct Agreements</h5>
        <a href="{{ route('backend.agreements.create') }}" class="btn btn-primary btn-sm">
          <i class="ti ti-plus me-1"></i>New Agreement
        </a>
      </div>
      @if($agreementStats['active_agreement'])
        <div class="card border-success mb-3">
          <div class="card-body py-3">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h6 class="text-primary mb-1"><i class="ti ti-check-circle me-1"></i>Active Agreement</h6>
                <p class="mb-0">
                  <strong>{{ $agreementStats['active_agreement']->title }}</strong>
                  <span class="badge bg-success ms-2">{{ $agreementStats['active_agreement']->version }}</span>
                </p>
              </div>
              <div class="col-md-6 text-md-end mt-2 mt-md-0">
                <span class="badge bg-label-success me-2">
                  <i class="ti ti-check me-1"></i>{{ $agreementStats['total_acceptances'] }} Accepted
                </span>
                <span class="badge bg-label-warning">
                  <i class="ti ti-clock me-1"></i>{{ $agreementStats['pending_players'] }} Pending
                </span>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="alert alert-warning mb-3">
          <i class="ti ti-alert-triangle me-2"></i>
          No active agreement. Players can register without accepting a Code of Conduct.
          <a href="{{ route('backend.agreements.create') }}" class="alert-link">Create one now</a>.
        </div>
      @endif
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Title</th><th>Version</th><th>Status</th><th>Acceptances</th><th>Created</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($agreements as $agreement)
              <tr>
                <td>{{ $agreement->title }}</td>
                <td><span class="badge bg-label-secondary">{{ $agreement->version }}</span></td>
                <td>
                  @if($agreement->is_active)
                    <span class="badge bg-success">Active</span>
                  @else
                    <span class="badge bg-label-secondary">Inactive</span>
                  @endif
                </td>
                <td>{{ number_format($agreement->player_agreements_count) }}</td>
                <td>{{ $agreement->created_at->format('d M Y') }}</td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="{{ route('backend.agreements.show', $agreement) }}" class="btn btn-icon btn-sm btn-outline-info" title="View">
                      <i class="ti ti-eye"></i>
                    </a>
                    @unless($agreement->is_active)
                      <a href="{{ route('backend.agreements.edit', $agreement) }}" class="btn btn-icon btn-sm btn-outline-primary" title="Edit">
                        <i class="ti ti-pencil"></i>
                      </a>
                      <form action="{{ route('backend.agreements.setActive', $agreement) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-icon btn-sm btn-outline-success" title="Set Active">
                          <i class="ti ti-check"></i>
                        </button>
                      </form>
                    @endunless
                    <form action="{{ route('backend.agreements.duplicate', $agreement) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-icon btn-sm btn-outline-secondary" title="Duplicate">
                        <i class="ti ti-copy"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-3">
                  No agreements found. <a href="{{ route('backend.agreements.create') }}">Create one</a>.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>{{-- /agreements --}}


    {{-- ══ TAB: PLAYERS ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-players" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="ti ti-alert-triangle me-2 text-warning"></i>Players Needing Profile Update</h5>
        <a href="{{ url('backend/player') }}" class="btn btn-sm btn-outline-warning">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Player</th><th>Parent / Guardian</th><th>Profile Status</th><th>Last Updated</th><th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($playersNeedingAttention as $player)
              @php $status = $player->getProfileStatus(); @endphp
              <tr>
                <td>
                  <a href="{{ route('player.show', $player->id) }}" class="fw-bold text-primary">
                    {{ $player->name }} {{ $player->surname }}
                  </a>
                  @if($player->isMinor())
                    <span class="badge bg-label-info ms-1" title="Minor"><i class="ti ti-user-heart"></i></span>
                  @endif
                </td>
                <td>
                  @if($player->user)
                    <a href="{{ route('user.show', $player->user->id) }}" class="text-muted">{{ $player->user->name }}</a>
                    <small class="d-block text-muted">{{ $player->user->email }}</small>
                  @else
                    <span class="text-muted">N/A</span>
                  @endif
                </td>
                <td>
                  <span class="badge bg-{{ $status['badge'] }}">
                    <i class="ti {{ $status['icon'] }} me-1"></i>{{ $status['message'] }}
                  </span>
                </td>
                <td>
                  @if($player->profile_updated_at)
                    {{ $player->profile_updated_at->format('d M Y') }}
                    <small class="d-block text-muted">{{ $player->profile_updated_at->diffForHumans() }}</small>
                  @else
                    <span class="text-danger">Never</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('player.edit', $player->id) }}" class="btn btn-icon btn-sm btn-outline-primary" title="Edit">
                    <i class="ti ti-pencil"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-4">
                  <i class="ti ti-check-circle ti-lg text-success d-block mb-2"></i>
                  <span class="text-muted">All player profiles are up to date!</span>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($playersNeedingAttention->count() >= 15)
        <div class="text-center mt-2">
          <a href="{{ url('backend/player') }}" class="text-warning">
            <i class="ti ti-alert-triangle me-1"></i>More players may need attention — View All
          </a>
        </div>
      @endif
    </div>{{-- /players --}}

    {{-- ══ TAB: AUDIT & ACTIVITY ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-audit" role="tabpanel">
      <ul class="nav nav-tabs px-3 pt-2" id="sa-audit-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="sa-tab-login" data-bs-toggle="tab"
                  data-bs-target="#sa-pane-login" type="button" role="tab">
            <i class="ti ti-lock me-1 text-danger"></i>Login Audit
            <span class="badge bg-label-secondary ms-1">{{ count($loginAuditLogs) }}</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="sa-tab-activity" data-bs-toggle="tab"
                  data-bs-target="#sa-pane-activity" type="button" role="tab">
            <i class="ti ti-history me-1 text-primary"></i>Activity Log
            <span class="badge bg-label-secondary ms-1">{{ count($activityLogs) }}</span>
          </button>
        </li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="sa-pane-login" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover table-striped w-100 mb-0" id="dt-login-audit">
              <thead>
                <tr>
                  <th>Date / Time</th><th>User</th><th>Email</th>
                  <th>IP Address</th><th>Status</th><th>Logout At</th><th>User Agent</th>
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
        <div class="tab-pane fade" id="sa-pane-activity" role="tabpanel">
          <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
            <label class="small mb-0">Filter</label>
            <select id="sa-activity-filter" class="form-select form-select-sm" style="width:auto;">
              <option value="">All</option>
              @foreach($logNames as $ln)
                <option value="{{ $ln }}">{{ $ln }}</option>
              @endforeach
            </select>
            <div class="form-check form-switch mb-0 ms-2">
              <input class="form-check-input" type="checkbox" id="sa-activity-toggle" checked>
              <label class="form-check-label small" for="sa-activity-toggle">Grouped</label>
            </div>
          </div>
          <div id="sa-activity-grouped-wrap" class="table-responsive">
            <table class="table table-hover table-striped w-100 mb-0" id="dt-activity">
              <thead>
                <tr>
                  <th>Last Active</th><th>User</th><th>Actions</th><th>Last Action</th>
                  <th class="d-none">Log Names</th><th class="no-sort"></th>
                </tr>
              </thead>
              <tbody>
                @foreach($activityByUser as $row)
                  @php
                    $showGrpBtn = in_array($row->last_log_name ?? '', ['withdrawal','refund','wallet']);
                  @endphp
                  <tr>
                    <td>{{ optional($row->last_at)->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $row->causer?->userName ?? $row->causer?->name ?? 'System' }}</td>
                    <td><span class="badge bg-label-primary">{{ $row->count }}</span></td>
                    <td>{{ $row->example_description ?? '—' }}</td>
                    <td class="d-none">{{ implode(',', $row->log_names ?? []) }}</td>
                    <td class="text-center">
                      @if($showGrpBtn)
                        <button type="button" class="btn btn-sm btn-icon btn-outline-info btn-activity-detail"
                          data-log="{{ $row->last_log_name }}"
                          data-desc="{{ $row->example_description }}"
                          data-user="{{ $row->causer?->userName ?? $row->causer?->name ?? 'System' }}"
                          data-date="{{ optional($row->last_at)->format('d M Y H:i') }}"
                          data-props='@json($row->last_properties ?? [])'
                          title="View detail"><i class="ti ti-info-circle"></i></button>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div id="sa-activity-raw-wrap" class="table-responsive d-none">
            <table class="table table-hover table-striped w-100 mb-0" id="dt-activity-raw">
              <thead>
                <tr><th>Date</th><th>User</th><th>Log</th><th>Action</th><th class="no-sort"></th></tr>
              </thead>
              <tbody>
                @foreach($activityLogs as $log)
                  @php $showRawBtn = in_array($log->log_name, ['withdrawal','refund','wallet']); @endphp
                  <tr>
                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $log->causer?->userName ?? $log->causer?->name ?? 'System' }}</td>
                    <td>{{ $log->log_name }}</td>
                    <td>{{ $log->description }}</td>
                    <td class="text-center">
                      @if($showRawBtn)
                        <button type="button" class="btn btn-sm btn-icon btn-outline-info btn-activity-detail"
                          data-log="{{ $log->log_name }}"
                          data-desc="{{ $log->description }}"
                          data-user="{{ $log->causer?->userName ?? $log->causer?->name ?? 'System' }}"
                          data-date="{{ $log->created_at->format('d M Y H:i') }}"
                          data-props='@json($log->properties)'
                          title="View detail"><i class="ti ti-info-circle"></i></button>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>{{-- /audit --}}


    {{-- ══ TAB: SETTINGS ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-settings" role="tabpanel">

      <div id="sa-settings-toast" class="alert alert-dismissible fade d-none mb-3" role="alert">
        <span id="sa-settings-toast-msg"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>

      <form action="{{ route('settings.store') }}" method="POST" id="sa-settings-form">
        @csrf
        <input type="hidden" name="_settings_origin" value="superadmin">

        {{-- ════ Settings sub-tabs ════ --}}
        <ul class="nav nav-pills mb-3 gap-1" id="sa-settings-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="sa-stab-email" data-bs-toggle="pill"
                    data-bs-target="#sa-spane-email" type="button" role="tab">
              <i class="ti ti-mail me-1"></i>Email
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="sa-stab-registration" data-bs-toggle="pill"
                    data-bs-target="#sa-spane-registration" type="button" role="tab">
              <i class="ti ti-ticket me-1"></i>Registration
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="sa-stab-payfast" data-bs-toggle="pill"
                    data-bs-target="#sa-spane-payfast" type="button" role="tab">
              <i class="ti ti-credit-card me-1"></i>PayFast Fees
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="sa-stab-access" data-bs-toggle="pill"
                    data-bs-target="#sa-spane-access" type="button" role="tab">
              <i class="ti ti-file-check me-1"></i>Access &amp; Conduct
            </button>
          </li>
        </ul>

        <div class="tab-content" id="sa-settings-tab-content">

          {{-- ════ A: EMAIL NOTIFICATIONS ════ --}}
          <div class="tab-pane fade show active" id="sa-spane-email" role="tabpanel">
            <div class="card mb-3">
              <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-mail me-1 text-primary"></i> Email Notifications</h5>
                <small class="text-muted">Control which events trigger an admin notification email.</small>
              </div>
              <div class="card-body">
                <div class="row g-3">

                  <div class="col-md-12 mb-2">
                    <label class="form-label" for="sa-admin-notification-email">Admin Notification Email</label>
                    <input type="email" class="form-control" id="sa-admin-notification-email"
                           name="admin_notification_email"
                           value="{{ old('admin_notification_email', $emailSettings['admin_notification_email'] ?? 'support@capetennis.co.za') }}">
                    <small class="text-muted">All system notification emails are sent to this address.</small>
                  </div>

                  @php
                    $emailToggles = [
                      'email_on_registration'        => 'Player Registration',
                      'email_on_withdrawal'          => 'Player Withdrawal',
                      'email_on_team_withdrawal'     => 'Team Player Withdrawal',
                      'email_on_wallet_topup'        => 'Wallet Top-Up',
                      'email_on_bank_refund_request' => 'Bank Refund Request',
                    ];
                  @endphp

                  @foreach($emailToggles as $key => $label)
                    <div class="col-md-6">
                      <div class="d-flex align-items-center justify-content-between border rounded p-3">
                        <div>
                          <label class="form-label mb-0" for="sa-{{ $key }}">{{ $label }}</label>
                          <br><small class="text-muted">Send admin email when a {{ strtolower($label) }} occurs.</small>
                        </div>
                        <div class="form-check form-switch ms-3">
                          <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                                 id="sa-{{ $key }}" name="{{ $key }}" value="1"
                                 data-setting-key="{{ $key }}"
                                 {{ old($key, $emailSettings[$key] ?? '1') == '1' ? 'checked' : '' }}>
                        </div>
                      </div>
                    </div>
                  @endforeach

                </div>
              </div>
            </div>

            {{-- Player confirmation emails card --}}
            <div class="card mb-3">
              <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-user-check me-1 text-success"></i> Player Confirmation Emails</h5>
                <small class="text-muted">Control which events trigger a confirmation email sent directly to the player. Click <strong>Edit Template</strong> to customise the subject and body.</small>
              </div>
              <div class="card-body">

                @php
                  $playerEmailDefs = [
                    'registration' => [
                      'toggle_key'      => 'player_email_on_registration',
                      'label'           => 'Registration Confirmation',
                      'desc'            => 'Sent when the player\'s registration payment is confirmed.',
                      'placeholders'    => ['{user_name}', '{event_name}', '{app_name}'],
                      'default_subject' => 'Registration Confirmation – {event_name}',
                      'default_body'    => "Hi {user_name},\n\nYour registration for **{event_name}** has been confirmed.\n\nIf you have any questions, please contact us at support@capetennis.co.za.",
                    ],
                    'withdrawal' => [
                      'toggle_key'      => 'player_email_on_withdrawal',
                      'label'           => 'Withdrawal Confirmation',
                      'desc'            => 'Sent when a player withdraws from an event.',
                      'placeholders'    => ['{player_name}', '{event_name}', '{category_name}', '{withdrawn_at}', '{initiated_by}', '{app_name}'],
                      'default_subject' => 'Withdrawal Confirmation – {event_name}',
                      'default_body'    => "Hi {player_name},\n\nYour withdrawal from **{event_name}** ({category_name}) has been confirmed.\n\n**Withdrawn on:** {withdrawn_at}  \n**Initiated by:** {initiated_by}\n\nIf you have any questions, please contact us at support@capetennis.co.za.",
                    ],
                    'move' => [
                      'toggle_key'      => 'player_email_on_move',
                      'label'           => 'Category Move Confirmation',
                      'desc'            => 'Sent when a player\'s category is changed.',
                      'placeholders'    => ['{player_name}', '{event_name}', '{old_category}', '{new_category}', '{changed_by}', '{app_name}'],
                      'default_subject' => 'Category Changed – {event_name}',
                      'default_body'    => "Hi {player_name},\n\nYour category for **{event_name}** has been changed.\n\n- **Previous Category:** {old_category}\n- **New Category:** {new_category}\n\nThis change was made by {changed_by}.\n\nIf you did not request this change, please contact support at support@capetennis.co.za.",
                    ],
                  ];
                @endphp

                @foreach($playerEmailDefs as $type => $def)
                  @php
                    $toggleKey   = $def['toggle_key'];
                    $subjectKey  = "player_email_subject_{$type}";
                    $bodyKey     = "player_email_body_{$type}";
                    $collapseId  = "sa-template-{$type}";
                  @endphp
                  <div class="border rounded p-3 mb-3">
                    {{-- Toggle row --}}
                    <div class="d-flex align-items-center justify-content-between">
                      <div>
                        <label class="form-label mb-0 fw-semibold" for="sa-{{ $toggleKey }}">{{ $def['label'] }}</label>
                        <br><small class="text-muted">{{ $def['desc'] }}</small>
                      </div>
                      <div class="d-flex align-items-center gap-2 ms-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                aria-expanded="false" aria-controls="{{ $collapseId }}">
                          <i class="ti ti-pencil me-1"></i>Edit Template
                        </button>
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                                 id="sa-{{ $toggleKey }}" name="{{ $toggleKey }}" value="1"
                                 data-setting-key="{{ $toggleKey }}"
                                 {{ old($toggleKey, $emailSettings[$toggleKey] ?? '1') == '1' ? 'checked' : '' }}>
                        </div>
                      </div>
                    </div>

                    {{-- Collapsible template editor --}}
                    <div class="collapse mt-3" id="{{ $collapseId }}">
                      <hr class="mt-0">

                      <div class="mb-3">
                        <label class="form-label" for="{{ $subjectKey }}">Subject</label>
                        <input type="text" class="form-control" id="{{ $subjectKey }}"
                               name="{{ $subjectKey }}" maxlength="255"
                               value="{{ old($subjectKey, $emailSettings[$subjectKey] ?? $def['default_subject']) }}"
                               placeholder="Email subject…">
                      </div>

                      <div class="mb-2">
                        <label class="form-label" for="{{ $bodyKey }}">
                          Body
                          <small class="text-muted fw-normal ms-1">(Markdown supported)</small>
                        </label>
                        <textarea class="form-control font-monospace" id="{{ $bodyKey }}"
                                  name="{{ $bodyKey }}" rows="8"
                                  placeholder="Email body…">{{ old($bodyKey, $emailSettings[$bodyKey] ?? $def['default_body']) }}</textarea>
                      </div>

                      <div class="mb-3">
                        <small class="text-muted">Available placeholders (click to copy):</small><br>
                        @foreach($def['placeholders'] as $ph)
                          <code class="sa-placeholder-badge badge bg-light text-dark border me-1 mt-1"
                                style="cursor:pointer" title="Click to copy">{{ $ph }}</code>
                        @endforeach
                      </div>

                      <button type="button" class="btn btn-primary btn-sm sa-save-template"
                              data-type="{{ $type }}"
                              data-subject-id="{{ $subjectKey }}"
                              data-body-id="{{ $bodyKey }}">
                        <i class="ti ti-device-floppy me-1"></i>Save Template
                      </button>
                    </div>
                  </div>
                @endforeach

              </div>
            </div>
          </div>{{-- /email --}}

          {{-- ════ B: REGISTRATION & WITHDRAWAL ════ --}}
          <div class="tab-pane fade" id="sa-spane-registration" role="tabpanel">
            <div class="card mb-3">
              <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-ticket me-1 text-warning"></i> Registration &amp; Withdrawal</h5>
                <small class="text-muted">Global switches that override per-event settings when disabled.</small>
              </div>
              <div class="card-body">
                <div class="row g-3">

                  <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between border rounded p-3">
                      <div>
                        <label class="form-label mb-0" for="sa-registration-open">Registrations Open</label>
                        <br><small class="text-muted">When off, all new event registrations are blocked site-wide.</small>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                               id="sa-registration-open" name="registration_open" value="1"
                               data-setting-key="registration_open"
                               {{ old('registration_open', $registrationSettings['registration_open'] ?? '1') == '1' ? 'checked' : '' }}>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between border rounded p-3">
                      <div>
                        <label class="form-label mb-0" for="sa-withdrawal-allowed">Withdrawals Allowed</label>
                        <br><small class="text-muted">When off, all withdrawal requests are blocked site-wide.</small>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                               id="sa-withdrawal-allowed" name="withdrawal_allowed" value="1"
                               data-setting-key="withdrawal_allowed"
                               {{ old('withdrawal_allowed', $registrationSettings['withdrawal_allowed'] ?? '1') == '1' ? 'checked' : '' }}>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="sa-withdrawal-deadline-days">Withdrawal Deadline (days before event start)</label>
                    <div class="input-group">
                      <input type="number" min="0" max="365" class="form-control"
                             id="sa-withdrawal-deadline-days" name="withdrawal_deadline_days"
                             value="{{ old('withdrawal_deadline_days', $registrationSettings['withdrawal_deadline_days'] ?? '7') }}">
                      <span class="input-group-text">days</span>
                    </div>
                    <small class="text-muted">Players cannot withdraw after this many days before the event starts.</small>
                  </div>

                  <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between border rounded p-3">
                      <div>
                        <label class="form-label mb-0" for="sa-profile-required">Require Complete Profile to Register</label>
                        <br><small class="text-muted">Players must have a complete profile before registering for any event.</small>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                               id="sa-profile-required" name="profile_required_for_registration" value="1"
                               data-setting-key="profile_required_for_registration"
                               {{ old('profile_required_for_registration', $registrationSettings['profile_required_for_registration'] ?? '1') == '1' ? 'checked' : '' }}>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>{{-- /registration --}}

          {{-- ════ C: PAYFAST FEE SETTINGS ════ --}}
          <div class="tab-pane fade" id="sa-spane-payfast" role="tabpanel">
            <div class="card mb-3">
              <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-credit-card me-1"></i> PayFast Fee Settings</h5>
                <small class="text-muted">
                  These defaults apply when the payment method is unknown. The negotiated discount from PayFast benefits Cape Tennis – the convenor is charged at the rates set below.
                </small>
              </div>
              <div class="card-body">
                <div class="row g-3 mb-3">

                  <div class="col-md-4">
                    <label class="form-label" for="sa-payfast-fee-percentage">Default Fee Percentage (%)</label>
                    <div class="input-group">
                      <input type="number" step="0.01" min="0" max="100" class="form-control"
                             id="sa-payfast-fee-percentage" name="payfast_fee_percentage"
                             value="{{ old('payfast_fee_percentage', $payfastSettings['payfast_fee_percentage']->value ?? '3.2') }}">
                      <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted">Fallback percentage when payment method is not detected.</small>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label" for="sa-payfast-fee-flat">Flat Fee per Transaction (R)</label>
                    <div class="input-group">
                      <span class="input-group-text">R</span>
                      <input type="number" step="0.01" min="0" class="form-control"
                             id="sa-payfast-fee-flat" name="payfast_fee_flat"
                             value="{{ old('payfast_fee_flat', $payfastSettings['payfast_fee_flat']->value ?? '2.00') }}">
                    </div>
                    <small class="text-muted">Applied to all payment methods.</small>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label" for="sa-payfast-vat-rate">VAT Rate (%)</label>
                    <div class="input-group">
                      <input type="number" step="0.01" min="0" max="100" class="form-control"
                             id="sa-payfast-vat-rate" name="payfast_vat_rate"
                             value="{{ old('payfast_vat_rate', $payfastSettings['payfast_vat_rate']->value ?? '14') }}">
                      <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted">VAT applied on top of the fee.</small>
                  </div>

                </div>

                <h6 class="text-muted mt-3 mb-2"><i class="ti ti-list me-1"></i> Fee Percentage per Payment Method</h6>
                <div class="table-responsive">
                  <table class="table table-striped mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width:200px;">Payment Method</th>
                        <th style="width:180px;">Fee Percentage</th>
                        <th>Example Fee on R200</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($paymentMethods as $methodKey => $methodLabel)
                        @php
                          $settingKey = "payfast_fee_pct_{$methodKey}";
                          $currentPct = old($settingKey, $payfastSettings[$settingKey]->value ?? '3.20');
                        @endphp
                        <tr>
                          <td class="align-middle fw-semibold">{{ $methodLabel }}</td>
                          <td>
                            <div class="input-group input-group-sm">
                              <input type="number" step="0.01" min="0" max="100"
                                     class="form-control sa-method-pct-input"
                                     name="{{ $settingKey }}"
                                     data-method="{{ $methodKey }}"
                                     value="{{ $currentPct }}">
                              <span class="input-group-text">%</span>
                            </div>
                          </td>
                          <td class="align-middle text-muted">
                            R <span class="sa-method-preview" data-method="{{ $methodKey }}">—</span>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>

                <div class="card bg-light mt-3 mb-0">
                  <div class="card-body py-2">
                    <strong>Fee Formula:</strong>
                    <code>((amount × percentage%) + R<span id="sa-previewFlat">{{ $payfastSettings['payfast_fee_flat']->value ?? '2.00' }}</span>) × (1 + <span id="sa-previewVat">{{ $payfastSettings['payfast_vat_rate']->value ?? '14' }}</span>%)</code>
                  </div>
                </div>
              </div>
            </div>
          </div>{{-- /payfast --}}

          {{-- ════ D: ACCESS & CONDUCT ════ --}}
          <div class="tab-pane fade" id="sa-spane-access" role="tabpanel">
            <div class="card mb-3">
              <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-file-check me-1 text-success"></i> Code of Conduct &amp; Terms</h5>
                <small class="text-muted">
                  Enable or disable the Code of Conduct and Terms requirements site-wide. When enabled, players must accept these before registering.
                </small>
              </div>
              <div class="card-body">
                <div class="row g-3">

                  <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between border rounded p-3">
                      <div>
                        <label class="form-label mb-0" for="sa-require-coc">Require Code of Conduct</label>
                        <br><small class="text-muted">Players must accept the Code of Conduct.</small>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                               id="sa-require-coc" name="require_code_of_conduct" value="1"
                               data-setting-key="require_code_of_conduct"
                               {{ old('require_code_of_conduct', $generalSettings['require_code_of_conduct'] ?? '0') == '1' ? 'checked' : '' }}>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between border rounded p-3">
                      <div>
                        <label class="form-label mb-0" for="sa-require-terms">Require Terms &amp; Conditions</label>
                        <br><small class="text-muted">Players must accept the Terms &amp; Conditions.</small>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                               id="sa-require-terms" name="require_terms" value="1"
                               data-setting-key="require_terms"
                               {{ old('require_terms', $generalSettings['require_terms'] ?? '0') == '1' ? 'checked' : '' }}>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="d-flex align-items-center justify-content-between border rounded p-3">
                      <div>
                        <label class="form-label mb-0" for="sa-require-profile-update">Require Profile Update on Login</label>
                        <br><small class="text-muted">Players must update their profile details when logging in if incomplete or outdated.</small>
                      </div>
                      <div class="form-check form-switch ms-3">
                        <input class="form-check-input sa-toggle-setting" type="checkbox" role="switch"
                               id="sa-require-profile-update" name="require_profile_update" value="1"
                               data-setting-key="require_profile_update"
                               {{ old('require_profile_update', $generalSettings['require_profile_update'] ?? '1') == '1' ? 'checked' : '' }}>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>{{-- /access --}}

        </div>{{-- /settings tab-content --}}

        <div class="mb-4 pt-2">
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> Save All Settings
          </button>
        </div>

      </form>

    </div>{{-- /settings --}}

    {{-- ══ TAB: WALLETS ══ --}}
    <div class="tab-pane fade p-3" id="sa-pane-wallets" role="tabpanel">

      @if(session('wallet_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          {{ session('wallet_success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0"><i class="ti ti-wallet me-2 text-success"></i>All User Wallets</h5>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0" id="dt-wallets">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Email</th>
              <th class="text-end">Balance</th>
              <th class="text-center">Transactions</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($wallets as $wallet)
              @php $walletOwner = $wallet->payable; @endphp
              <tr>
                <td>
                  @if($walletOwner)
                    <a href="{{ route('user.show', $walletOwner->id) }}" class="fw-semibold text-primary">
                      {{ $walletOwner->name }}
                    </a>
                  @else
                    <span class="text-muted">Unknown</span>
                  @endif
                </td>
                <td><small class="text-muted">{{ $walletOwner->email ?? '—' }}</small></td>
                <td class="text-end fw-bold {{ $wallet->balance > 0 ? 'text-success' : ($wallet->balance < 0 ? 'text-danger' : 'text-muted') }}">
                  R {{ number_format($wallet->balance, 2) }}
                </td>
                <td class="text-center">
                  <span class="badge bg-label-secondary">{{ $wallet->transactions->count() }}</span>
                </td>
                <td class="text-center">
                  <div class="d-flex gap-1 justify-content-center">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-wallet-add-tx"
                      data-user-id="{{ $wallet->payable_id }}"
                      data-user-name="{{ $walletOwner->name ?? '' }}"
                      data-wallet-balance="R {{ number_format($wallet->balance, 2) }}"
                      title="Add Transaction">
                      <i class="ti ti-plus me-1"></i>Transact
                    </button>
                    <form method="POST" action="{{ route('superadmin.wallets.destroy', $wallet) }}"
                          class="form-wallet-delete d-inline"
                          data-wallet-owner="{{ $walletOwner->name ?? '' }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Wallet">
                        <i class="ti ti-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-3">No wallets found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

    </div>{{-- /wallets --}}

  </div>{{-- /tab-content --}}
</div>{{-- /main tabs card --}}

{{-- ── Withdrawal / Refund Detail Modal ───────────────────────────── --}}
<div class="modal fade" id="modal-activity-detail" tabindex="-1" aria-labelledby="modal-activity-detail-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-activity-detail-label">
          <i class="ti ti-file-description me-1 text-info"></i> Activity Detail
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="modal-activity-detail-body">
        {{-- populated via JS --}}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>{{-- /#modal-activity-detail --}}

{{-- ── Wallet Add Transaction Modal ──────────────────────────────── --}}
<div class="modal fade" id="modal-wallet-add-tx" tabindex="-1" aria-labelledby="modal-wallet-add-tx-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-wallet-add-tx-label">
          <i class="ti ti-wallet me-1 text-primary"></i> Add Wallet Transaction
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-wallet-add-tx" method="POST">
        @csrf
        <div class="modal-body">
          <p id="wallet-add-tx-user-label" class="mb-3 text-muted"></p>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" id="tx-type-credit" value="credit" checked>
                <label class="form-check-label text-success fw-semibold" for="tx-type-credit">Credit (add funds)</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="type" id="tx-type-debit" value="debit">
                <label class="form-check-label text-danger fw-semibold" for="tx-type-debit">Debit (deduct funds)</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="tx-amount">Amount (R)</label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" step="0.01" min="0.01" class="form-control" id="tx-amount" name="amount" required placeholder="0.00">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="tx-reference">Reference</label>
            <input type="text" class="form-control" id="tx-reference" name="reference" maxlength="255" placeholder="Optional note">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="ti ti-device-floppy me-1"></i> Save Transaction
          </button>
        </div>
      </form>
    </div>
  </div>
</div>{{-- /#modal-wallet-add-tx --}}

@endsection

@section('page-script')
<script>
$(function () {

  // ── Withdrawal DataTables (lazy init on tab show) ──────────────
  var dtWPending = null, dtWCompleted = null, dtWWallet = null;

  $('#sa-tab-withdrawals').on('shown.bs.tab', function () {
    if (!dtWPending) {
      dtWPending = $('#dt-w-pending').DataTable({ ordering: true, order: [[6,'asc']], pageLength: 25, language: { emptyTable: '<i class="ti ti-check-circle text-success me-1"></i> No pending bank refunds.' } });
    }
  });
  $('#sa-wtab-completed').on('shown.bs.tab', function () {
    if (!dtWCompleted) {
      dtWCompleted = $('#dt-w-completed').DataTable({ ordering: true, order: [[5,'desc']], pageLength: 25, language: { emptyTable: 'No completed bank refunds.' } });
    } else { dtWCompleted.columns.adjust().draw(false); }
  });
  $('#sa-wtab-wallet').on('shown.bs.tab', function () {
    if (!dtWWallet) {
      dtWWallet = $('#dt-w-wallet').DataTable({ ordering: true, order: [[5,'desc']], pageLength: 25, language: { emptyTable: 'No wallet withdrawals found.' } });
    } else { dtWWallet.columns.adjust().draw(false); }
  });

  // ── Login Audit DataTable (lazy init) ──────────────────────────
  var dtLoginInit = false;
  function initLoginAudit() {
    if (!dtLoginInit && !$.fn.DataTable.isDataTable('#dt-login-audit')) {
      $('#dt-login-audit').DataTable({
        ordering: true, order: [[0,'desc']], pageLength: 25,
        columnDefs: [{ targets: 6, orderable: false }]
      });
      dtLoginInit = true;
    }
  }

  // ── Activity Log DataTables ────────────────────────────────────
  var dtGrouped = null, dtRaw = null;
  function initGrouped() {
    if (!dtGrouped) {
      dtGrouped = $('#dt-activity').DataTable({
        ordering: true, order: [[0,'desc']], pageLength: 25,
        columnDefs: [
          { targets: 4, visible: false },
          { targets: 5, orderable: false, searchable: false }
        ]
      });
    } else { dtGrouped.columns.adjust().draw(false); }
  }
  function initRaw() {
    if (!dtRaw) {
      dtRaw = $('#dt-activity-raw').DataTable({
        ordering: true, order: [[0,'desc']], pageLength: 25,
        columnDefs: [{ targets: 4, orderable: false, searchable: false }]
      });
    } else { dtRaw.columns.adjust().draw(false); }
  }

  // ── Activity Detail Modal ───────────────────────────────────────
  var $detailModal = new bootstrap.Modal(document.getElementById('modal-activity-detail'));
  $(document).on('click', '.btn-activity-detail', function () {
    var log   = $(this).data('log');
    var desc  = $(this).data('desc');
    var user  = $(this).data('user');
    var date  = $(this).data('date');
    var props = $(this).data('props') || {};

    var labelMap = {
      player: 'Player',
      event: 'Event',
      category: 'Category',
      method: 'Refund Method',
      refund_allowed: 'Refund Allowed',
      gross: 'Gross',
      fee: 'Fee',
      net: 'Net',
      bank: 'Bank Name',
      pf_payment_id: 'PayFast Payment ID',
      registration_id: 'Registration #',
      order_id: 'Order #',
      amount: 'Amount',
      reference: 'Reference',
      type: 'Type',
    };

    var badgeClass = { withdrawal: 'bg-label-warning', refund: 'bg-label-success', wallet: 'bg-label-info' };
    var badge = '<span class="badge ' + (badgeClass[log] || 'bg-label-secondary') + ' me-1">' + log + '</span>';

    var html = '<p class="mb-2">' + badge + '<strong>' + $('<span>').text(desc).html() + '</strong></p>';
    html += '<table class="table table-sm table-bordered mb-0"><tbody>';
    html += '<tr><th class="text-muted" style="width:40%">User</th><td>' + $('<span>').text(user).html() + '</td></tr>';
    html += '<tr><th class="text-muted">Date</th><td>' + $('<span>').text(date).html() + '</td></tr>';

    $.each(props, function (key, val) {
      if (key === 'attributes' || key === 'old') return; // skip spatie internals
      var label = labelMap[key] || key.replace(/_/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
      var display = val;
      if (key === 'method') {
        var methodLabels = { wallet: 'Wallet', bank: 'Bank EFT', payfast: 'PayFast' };
        display = methodLabels[val] || val;
      }
      if (typeof val === 'boolean') display = val ? 'Yes' : 'No';
      if ((key === 'gross' || key === 'fee' || key === 'net' || key === 'amount') && val !== null && val !== undefined) {
        display = 'R' + parseFloat(val).toFixed(2);
      }
      html += '<tr><th class="text-muted">' + $('<span>').text(label).html() + '</th><td>' + $('<span>').text(display).html() + '</td></tr>';
    });

    html += '</tbody></table>';
    $('#modal-activity-detail-body').html(html);
    $detailModal.show();
  });

  $('#sa-tab-audit').on('shown.bs.tab', function () {
    if ($('#sa-pane-login').hasClass('active')) initLoginAudit();
    if ($('#sa-pane-activity').hasClass('active')) initGrouped();
  });
  $('#sa-tab-login').on('shown.bs.tab', function () { initLoginAudit(); });
  $('#sa-tab-activity').on('shown.bs.tab', function () { initGrouped(); });

  // ── Grouped / Raw toggle ────────────────────────────────────────
  $('#sa-activity-toggle').on('change', function () {
    if ($(this).is(':checked')) {
      $('#sa-activity-raw-wrap').addClass('d-none');
      $('#sa-activity-grouped-wrap').removeClass('d-none');
      if (dtGrouped) dtGrouped.columns.adjust().draw(false);
    } else {
      $('#sa-activity-grouped-wrap').addClass('d-none');
      $('#sa-activity-raw-wrap').removeClass('d-none');
      initRaw();
    }
  });

  // ── Filter by log name ──────────────────────────────────────────
  $('#sa-activity-filter').on('change', function () {
    var val = $(this).val();
    if (dtGrouped) { val ? dtGrouped.column(4).search(val).draw() : dtGrouped.column(4).search('').draw(); }
    if (dtRaw)     { val ? dtRaw.column(2).search('^'+val+'$',true,false).draw() : dtRaw.column(2).search('').draw(); }
  });

  // ── PayFast live fee preview (Settings tab) ──────────────────────
  function saCalcFee(pct, flat, vat, amount) {
    return ((amount * pct / 100) + flat) * (1 + vat / 100);
  }

  function saUpdatePreviews() {
    var flat = parseFloat($('#sa-payfast-fee-flat').val()) || 0;
    var vat  = parseFloat($('#sa-payfast-vat-rate').val()) || 0;

    $('#sa-previewFlat').text(flat.toFixed(2));
    $('#sa-previewVat').text(vat);

    $('.sa-method-pct-input').each(function () {
      var method = $(this).data('method');
      var pct    = parseFloat($(this).val()) || 0;
      var fee    = saCalcFee(pct, flat, vat, 200);
      $('.sa-method-preview[data-method="' + method + '"]').text(fee.toFixed(2));
    });
  }

  saUpdatePreviews();
  $(document).on('input', '.sa-method-pct-input, #sa-payfast-fee-flat, #sa-payfast-vat-rate', saUpdatePreviews);

  // ── Settings toggles — auto-save on change ───────────────────
  var saToggleUrl = '{{ route('settings.store.single') }}';
  var saToggleToken = $('meta[name="csrf-token"]').attr('content')
                   || $('input[name="_token"]').first().val();

  function saShowToast(message, isError) {
    var $toast = $('#sa-settings-toast');
    var $msg   = $('#sa-settings-toast-msg');
    $toast.removeClass('d-none alert-success alert-danger show');
    $toast.addClass(isError ? 'alert-danger' : 'alert-success').addClass('show').removeClass('d-none');
    $msg.text(message);
    clearTimeout($toast.data('hideTimer'));
    if (!isError) {
      $toast.data('hideTimer', setTimeout(function () {
        $toast.removeClass('show').addClass('d-none');
      }, 3000));
    }
  }

  $(document).on('change', '.sa-toggle-setting', function () {
    var key   = $(this).data('setting-key');
    var value = $(this).is(':checked') ? '1' : '0';
    var $el   = $(this);

    $el.prop('disabled', true);

    $.ajax({
      url:     saToggleUrl,
      method:  'POST',
      data:    { _token: saToggleToken, key: key, value: value },
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      success: function (res) {
        saShowToast(res.message || 'Setting saved.', false);
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Save failed. Please try again.';
        saShowToast(msg, true);
        $el.prop('checked', value === '0');
      },
      complete: function () {
        $el.prop('disabled', false);
      }
    });
  });

  // ── Email template save ───────────────────────────────────────
  var saTemplateUrl = '{{ route('settings.store.template') }}';

  $(document).on('click', '.sa-save-template', function () {
    var $btn       = $(this);
    var type       = $btn.data('type');
    var subjectId  = $btn.data('subject-id');
    var bodyId     = $btn.data('body-id');
    var subject    = $('#' + subjectId).val();
    var body       = $('#' + bodyId).val();

    $btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Saving…');

    $.ajax({
      url:     saTemplateUrl,
      method:  'POST',
      data:    { _token: saToggleToken, type: type, subject: subject, body: body },
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      success: function (res) {
        saShowToast(res.message || 'Template saved.', false);
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Save failed. Please try again.';
        saShowToast(msg, true);
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Save Template');
      }
    });
  });

  // ── Placeholder badges — click to insert at cursor ───────────
  $(document).on('click', '.sa-placeholder-badge', function () {
    var ph       = $(this).text().trim();
    var $collapse = $(this).closest('.collapse');
    var $textarea = $collapse.find('textarea');

    if ($textarea.length) {
      var el    = $textarea[0];
      var start = el.selectionStart;
      var end   = el.selectionEnd;
      var val   = el.value;
      el.value = val.substring(0, start) + ph + val.substring(end);
      el.selectionStart = el.selectionEnd = start + ph.length;
      el.focus();
    } else {
      // Subject field
      var $input = $collapse.find('input[type="text"]:focus');
      if (!$input.length) $input = $collapse.find('input[type="text"]');
      if ($input.length) {
        var iEl    = $input[0];
        var iStart = iEl.selectionStart;
        var iEnd   = iEl.selectionEnd;
        var iVal   = iEl.value;
        iEl.value = iVal.substring(0, iStart) + ph + iVal.substring(iEnd);
        iEl.selectionStart = iEl.selectionEnd = iStart + ph.length;
        iEl.focus();
      }
    }
  });

  // ── Settings form — AJAX save ─────────────────────────────────
  $('#sa-settings-form').on('submit', function (e) {
    e.preventDefault();
    var $form   = $(this);
    var $btn    = $form.find('button[type="submit"]');
    var $toast  = $('#sa-settings-toast');
    var $msg    = $('#sa-settings-toast-msg');

    $btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Saving…');

    $.ajax({
      url:     $form.attr('action'),
      method:  'POST',
      data:    $form.serialize(),
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      success: function (res) {
        $toast.removeClass('d-none alert-danger').addClass('alert-success show');
        $msg.text(res.message || 'Settings saved successfully.');
        setTimeout(function () { $toast.removeClass('show').addClass('d-none'); }, 4000);
      },
      error: function (xhr) {
        var errs = '';
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
          errs = Object.values(xhr.responseJSON.errors).flat().join(' ');
        } else {
          errs = (xhr.responseJSON && xhr.responseJSON.message) || 'An error occurred. Please try again.';
        }
        $toast.removeClass('d-none alert-success').addClass('alert-danger show');
        $msg.text(errs);
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Save All Settings');
      }
    });
  });

  // Open Settings tab if URL hash is #settings
  if (window.location.hash === '#settings') {
    var el = document.getElementById('sa-tab-settings');
    if (el) bootstrap.Tab.getOrCreateInstance(el).show();
  }

  // Open Finance tab if ?tab=finance is in the URL
  (function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'finance') {
      var el = document.getElementById('sa-tab-finance');
      if (el) bootstrap.Tab.getOrCreateInstance(el).show();
    }
  })();

  // ── Wallets DataTable (lazy init on tab show) ─────────────────
  var dtWallets = null;
  $('#sa-tab-wallets').on('shown.bs.tab', function () {
    if (!dtWallets) {
      dtWallets = $('#dt-wallets').DataTable({
        ordering:    true,
        order:       [[2, 'desc']],
        pageLength:  25,
        columnDefs:  [{ targets: 4, orderable: false, searchable: false }],
        language:    { emptyTable: 'No wallets found.' }
      });
    } else { dtWallets.columns.adjust().draw(false); }
  });

  // ── Add Transaction Modal ──────────────────────────────────────
  var addTxModal = new bootstrap.Modal(document.getElementById('modal-wallet-add-tx'));

  $(document).on('click', '.btn-wallet-add-tx', function () {
    var userId  = $(this).data('user-id');
    var name    = $(this).data('user-name');
    var balance = $(this).data('wallet-balance');
    var url     = '{{ url("backend/superadmin/wallets/users") }}/' + userId + '/transaction';

    $('#form-wallet-add-tx').attr('action', url);
    $('#wallet-add-tx-user-label').html(
      '<i class="ti ti-user me-1"></i><strong>' + $('<span>').text(name).html() + '</strong>' +
      ' &mdash; Current balance: <span class="text-success fw-bold">' + $('<span>').text(balance).html() + '</span>'
    );
    $('#tx-type-credit').prop('checked', true);
    $('#form-wallet-add-tx input[name="amount"]').val('');
    $('#form-wallet-add-tx input[name="reference"]').val('');
    addTxModal.show();
  });

  // ── Wallet delete confirmation ──────────────────────────────────
  $(document).on('submit', '.form-wallet-delete', function (e) {
    var owner = $(this).data('wallet-owner') || 'this user';
    if (!confirm('Delete entire wallet for "' + owner + '" and ALL its transactions? This cannot be undone.')) {
      e.preventDefault();
    }
  });

});
</script>
@endsection
