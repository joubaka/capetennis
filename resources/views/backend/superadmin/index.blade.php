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

{{-- ═══════════════ FINANCIAL DASHBOARD ═══════════════ --}}
<div class="mb-3">
  <h6 class="text-muted d-flex align-items-center">
    <i class="ti ti-report-money me-1 text-warning"></i> Financial Dashboard — All Events
  </h6>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-3">
  <div class="col-6 col-md-4">
    <div class="card border-start border-success border-3 h-100">
      <div class="card-body">
        <small class="text-muted d-block mb-1"><i class="ti ti-cash me-1 text-success"></i>Total Gross Income</small>
        <h5 class="mb-0 text-success">R {{ number_format($financeSummary['total_gross'], 2) }}</h5>
        <small class="text-muted">All registration payments</small>
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
        <small class="text-muted">Across all events</small>
      </div>
    </div>
  </div>
</div>

{{-- Per-event breakdown table --}}
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
      <i class="ti ti-calendar-stats me-2 text-warning"></i>
      <h5 class="mb-0">Per-Event Financial Summary</h5>
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

{{-- ═══════════════ COC AGREEMENTS + QUICK ACTIONS ═══════════════ --}}
<div class="row mb-4">

  {{-- CoC Agreements card --}}
  <div class="col-md-8 mb-3">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="ti ti-file-certificate me-2 text-primary"></i>
          <h5 class="mb-0">Code of Conduct Agreements</h5>
        </div>
        <a href="{{ route('backend.agreements.create') }}" class="btn btn-primary btn-sm">
          <i class="ti ti-plus me-1"></i>New Agreement
        </a>
      </div>

      {{-- Active agreement status banner --}}
      @if($agreementStats['active_agreement'])
        <div class="card-body border-bottom py-3">
          <div class="row align-items-center">
            <div class="col-md-6">
              <h6 class="text-primary mb-1">
                <i class="ti ti-check-circle me-1"></i>Active Agreement
              </h6>
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
      @else
        <div class="card-body border-bottom py-3">
          <div class="alert alert-warning mb-0">
            <i class="ti ti-alert-triangle me-2"></i>
            No active agreement. Players can register without accepting a Code of Conduct.
            <a href="{{ route('backend.agreements.create') }}" class="alert-link">Create one now</a>.
          </div>
        </div>
      @endif

      {{-- Agreements table --}}
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Title</th>
              <th>Version</th>
              <th>Status</th>
              <th>Acceptances</th>
              <th>Created</th>
              <th>Actions</th>
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
                <td colspan="6" class="text-center text-muted py-3">No agreements found. <a href="{{ route('backend.agreements.create') }}">Create one</a>.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Quick Actions card --}}
  <div class="col-md-4 mb-3">
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
          <a href="{{ route('admin.refunds.bank.index') }}" class="btn btn-outline-danger btn-sm">
            <i class="ti ti-cash-banknote me-1"></i>Refunds
          </a>
        </div>

        <hr>

        {{-- Live mini counters --}}
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
            <a href="{{ route('admin.refunds.bank.index') }}" class="text-decoration-none">
              <div class="fw-bold text-warning fs-5">{{ $pendingWithdrawals }}</div>
              <small class="text-muted">Withdrawals</small>
            </a>
          </div>
          <div class="text-center">
            <a href="{{ route('admin.refunds.bank.index') }}" class="text-decoration-none">
              <div class="fw-bold {{ $pendingBankRefunds->count() > 0 ? 'text-danger' : 'text-success' }} fs-5">
                {{ $pendingBankRefunds->count() }}
              </div>
              <small class="text-muted">Bank Refunds</small>
            </a>
          </div>
          <div class="text-center">
            <div class="fw-bold text-info fs-5">{{ $newUsersThisMonth }}</div>
            <small class="text-muted">New Users (mo.)</small>
          </div>
        </div>
      </div>

      {{-- Support info --}}
      <div class="card-footer">
        <h6 class="mb-1"><i class="ti ti-headset me-2"></i>Support</h6>
        <a href="mailto:support@capetennis.co.za" class="btn btn-sm btn-outline-primary">
          <i class="ti ti-mail me-1"></i>support@capetennis.co.za
        </a>
      </div>
    </div>
  </div>

</div>

{{-- ═══════════════ PENDING BANK REFUNDS ═══════════════ --}}
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="ti ti-cash-banknote me-2 text-danger"></i>
          <h5 class="mb-0">Pending Bank Refunds</h5>
          @if($pendingBankRefunds->count() > 0)
            <span class="badge bg-danger ms-2">{{ $pendingBankRefunds->count() }}</span>
          @else
            <span class="badge bg-success ms-2">All clear</span>
          @endif
        </div>
        <a href="{{ route('admin.refunds.bank.index') }}" class="btn btn-sm btn-outline-danger">
          View Full Refunds Page
        </a>
      </div>

      @if(session('pf_query_result'))
        <div class="alert alert-info alert-dismissible mx-3 mt-3 mb-0" role="alert">
          <i class="ti ti-info-circle me-1"></i>{{ session('pf_query_result') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if($errors->any())
        <div class="alert alert-danger alert-dismissible mx-3 mt-3 mb-0" role="alert">
          @foreach($errors->all() as $error)
            <div><i class="ti ti-alert-triangle me-1"></i>{{ $error }}</div>
          @endforeach
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if($pendingBankRefunds->isEmpty())
        <div class="card-body text-center py-4 text-muted">
          <i class="ti ti-check-circle ti-lg text-success d-block mb-2"></i>
          No pending bank refunds.
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Registration</th>
                <th>Event / Category</th>
                <th>Player / User</th>
                <th>Bank Details</th>
                <th class="text-end">Refund (R)</th>
                <th>Withdrawn</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($pendingBankRefunds as $refund)
                <tr>
                  <td>
                    <small class="text-muted">#{{ $refund->id }}</small>
                  </td>
                  <td>
                    <div class="fw-semibold">
                      {{ optional(optional($refund->categoryEvent)->event)->name ?? '—' }}
                    </div>
                    <small class="text-muted">
                      {{ optional($refund->categoryEvent)->category->name ?? '' }}
                    </small>
                  </td>
                  <td>
                    @if($refund->user)
                      <a href="{{ route('user.show', $refund->user) }}" class="fw-bold text-primary">
                        {{ $refund->user->name }}
                      </a>
                      <small class="d-block text-muted">{{ $refund->user->email }}</small>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td>
                    <small>
                      <span class="fw-semibold">{{ $refund->refund_account_name ?? '—' }}</span><br>
                      {{ $refund->refund_bank_name ?? '' }}
                      @if($refund->refund_account_number)
                        · Acc: {{ $refund->refund_account_number }}
                      @endif
                      @if($refund->refund_branch_code)
                        · Branch: {{ $refund->refund_branch_code }}
                      @endif
                    </small>
                  </td>
                  <td class="text-end">
                    <span class="fw-bold text-success">
                      R {{ number_format($refund->refund_net ?? $refund->refund_gross ?? 0, 2) }}
                    </span>
                    @if($refund->refund_gross > 0 && $refund->refund_fee > 0)
                      <small class="d-block text-muted">
                        gross R{{ number_format($refund->refund_gross, 2) }}
                        − fee R{{ number_format($refund->refund_fee, 2) }}
                      </small>
                    @endif
                  </td>
                  <td>
                    @if($refund->withdrawn_at)
                      {{ $refund->withdrawn_at->format('d M Y') }}
                      <small class="d-block text-muted">{{ $refund->withdrawn_at->diffForHumans() }}</small>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-center" style="white-space:nowrap;">
                    {{-- Mark bank transfer complete --}}
                    <form method="POST"
                          action="{{ route('admin.refunds.bank.complete', $refund) }}"
                          class="d-inline"
                          onsubmit="return confirm('Mark this bank refund as completed?')">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success" title="Mark Complete">
                        <i class="ti ti-check me-1"></i>Complete
                      </button>
                    </form>

                    {{-- PayFast query (only when pf_transaction_id is present) --}}
                    @if($refund->pf_transaction_id)
                      <a href="{{ route('admin.refunds.bank.payfast-query', $refund) }}"
                         class="btn btn-sm btn-outline-info ms-1"
                         title="Query PayFast refund status">
                        <i class="ti ti-search me-1"></i>PF Status
                      </a>
                    @endif

                    {{-- View full refund record --}}
                    @if(\Route::has('admin.registration.refunds.bank.show'))
                      <a href="{{ route('admin.registration.refunds.bank.show', $refund) }}"
                         class="btn btn-sm btn-outline-secondary ms-1"
                         title="View details">
                        <i class="ti ti-eye"></i>
                      </a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ═══════════════ AUDIT & ACTIVITY (tabbed) ═══════════════ --}}
<div class="row mb-4">
  <div class="col-12">
    <div class="card">

      <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs card-header-tabs px-3 pt-2" id="sa-audit-tabs" role="tablist">
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
      </div>

      <div class="tab-content">

        {{-- ── LOGIN AUDIT pane ── --}}
        <div class="tab-pane fade show active" id="sa-pane-login" role="tabpanel">
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

        {{-- ── ACTIVITY LOG pane ── --}}
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

          {{-- Grouped table --}}
          <div id="sa-activity-grouped-wrap" class="table-responsive">
            <table class="table table-hover table-striped w-100 mb-0" id="dt-activity">
              <thead>
                <tr>
                  <th>Last Active</th>
                  <th>User</th>
                  <th>Actions</th>
                  <th>Last Action</th>
                  <th class="d-none">Log Names{{-- hidden; used by DataTables column(4) for log-name filter --}}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($activityByUser as $row)
                  <tr>
                    <td>{{ optional($row->last_at)->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $row->causer?->userName ?? $row->causer?->name ?? 'System' }}</td>
                    <td><span class="badge bg-label-primary">{{ $row->count }}</span></td>
                    <td>{{ $row->example_description ?? '—' }}</td>
                    <td class="d-none">{{ implode(',', $row->log_names ?? []) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          {{-- Raw table (hidden until toggled) --}}
          <div id="sa-activity-raw-wrap" class="table-responsive d-none">
            <table class="table table-hover table-striped w-100 mb-0" id="dt-activity-raw">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User</th>
                  <th>Log</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($activityLogs as $log)
                  <tr>
                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $log->causer?->userName ?? $log->causer?->name ?? 'System' }}</td>
                    <td>{{ $log->log_name }}</td>
                    <td>{{ $log->description }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- ═══════════════ PLAYERS NEEDING ATTENTION + RECENT USERS ═══════════════ --}}
<div class="row mb-4">

  {{-- Players needing profile attention --}}
  <div class="col-xl-8 mb-3">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
          <i class="ti ti-alert-triangle me-2 text-warning"></i>
          <h5 class="mb-0">Players Needing Profile Update</h5>
        </div>
        <a href="{{ url('backend/player') }}" class="btn btn-sm btn-outline-warning">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Player</th>
              <th>Parent / Guardian</th>
              <th>Profile Status</th>
              <th>Last Updated</th>
              <th></th>
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
                    <a href="{{ route('user.show', $player->user->id) }}" class="text-muted">
                      {{ $player->user->name }}
                    </a>
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
        <div class="card-footer text-center">
          <a href="{{ url('backend/player') }}" class="text-warning">
            <i class="ti ti-alert-triangle me-1"></i>More players may need attention — View All
          </a>
        </div>
      @endif
    </div>
  </div>

  {{-- Recent users + support --}}
  <div class="col-xl-4 mb-3">

    <div class="card mb-3">
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

@endsection

@section('page-script')
<script>
$(function () {

  // ── Login Audit DataTable ──────────────────────────────────────
  $('#dt-login-audit').DataTable({
    ordering:   true,
    order:      [[0, 'desc']],
    pageLength: 25,
    columnDefs: [{ targets: 6, orderable: false }]
  });

  $('#sa-tab-login').on('shown.bs.tab', function () {
    var t = $.fn.dataTable.isDataTable('#dt-login-audit')
              ? $('#dt-login-audit').DataTable() : null;
    if (t) t.columns.adjust().draw(false);
  });

  // ── Activity Log: Grouped DataTable ────────────────────────────
  var dtGrouped = $('#dt-activity').DataTable({
    ordering:   true,
    order:      [[0, 'desc']],
    pageLength: 25,
    columnDefs: [{ targets: 4, visible: false }]
  });

  $('#sa-tab-activity').on('shown.bs.tab', function () {
    dtGrouped.columns.adjust().draw(false);
  });

  // ── Raw DataTable: lazy-init on first toggle ────────────────────
  var dtRaw = null;

  function initRawIfNeeded() {
    if (!dtRaw) {
      dtRaw = $('#dt-activity-raw').DataTable({
        ordering:   true,
        order:      [[0, 'desc']],
        pageLength: 25
      });
    } else {
      dtRaw.columns.adjust().draw(false);
    }
  }

  // ── Grouped / Raw toggle ────────────────────────────────────────
  $('#sa-activity-toggle').on('change', function () {
    if ($(this).is(':checked')) {
      $('#sa-activity-raw-wrap').addClass('d-none');
      $('#sa-activity-grouped-wrap').removeClass('d-none');
      dtGrouped.columns.adjust().draw(false);
    } else {
      $('#sa-activity-grouped-wrap').addClass('d-none');
      $('#sa-activity-raw-wrap').removeClass('d-none');
      initRawIfNeeded();
    }
  });

  // ── Filter by log name ──────────────────────────────────────────
  $('#sa-activity-filter').on('change', function () {
    var val = $(this).val();
    val ? dtGrouped.column(4).search(val).draw()
        : dtGrouped.column(4).search('').draw();
    if (dtRaw) {
      val ? dtRaw.column(2).search('^' + val + '$', true, false).draw()
          : dtRaw.column(2).search('').draw();
    }
  });

});
</script>
@endsection
