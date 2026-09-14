@extends('layouts.backend')

@section('title', 'Super Admin Dashboard')

@section('page-style')
<style>
  .sa-home { --sa-border: rgba(75, 70, 92, .12); }
  .sa-home-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .sa-home-title { display: flex; align-items: center; gap: 1rem; }
  .sa-home-icon { width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .85rem; background: #1f304a; color: #fff; font-size: 1.45rem; }
  .sa-pulse { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border: 1px solid var(--sa-border); border-radius: .8rem; background: var(--bs-card-bg); overflow: hidden; }
  .sa-pulse-item { padding: 1rem 1.15rem; }
  .sa-pulse-item + .sa-pulse-item { border-left: 1px solid var(--sa-border); }
  .sa-pulse-value { color: var(--bs-heading-color); font-size: 1.25rem; font-weight: 700; line-height: 1.2; }
  .sa-pulse-label { margin-top: .25rem; color: var(--bs-secondary-color); font-size: .78rem; }
  .sa-section-title { font-size: 1rem; font-weight: 700; margin-bottom: .8rem; }
  .sa-attention-list { border: 1px solid var(--sa-border); border-radius: .8rem; background: var(--bs-card-bg); overflow: hidden; }
  .sa-attention-item { display: flex; align-items: center; gap: .8rem; padding: .9rem 1rem; color: inherit; text-decoration: none; }
  .sa-attention-item + .sa-attention-item { border-top: 1px solid var(--sa-border); }
  .sa-attention-item:hover { color: inherit; background: rgba(75, 70, 92, .025); }
  .sa-attention-icon { width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem; display: inline-flex; align-items: center; justify-content: center; border-radius: .65rem; }
  .sa-attention-copy { min-width: 0; flex: 1; }
  .sa-attention-title { color: var(--bs-heading-color); font-weight: 600; }
  .sa-attention-note { color: var(--bs-secondary-color); font-size: .78rem; }
  .sa-workspace-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
  .sa-workspace { border: 1px solid var(--sa-border); border-radius: .8rem; background: var(--bs-card-bg); padding: 1rem; }
  .sa-workspace-heading { display: flex; align-items: center; gap: .55rem; margin-bottom: .7rem; color: var(--bs-heading-color); font-weight: 700; }
  .sa-workspace-links { display: flex; flex-wrap: wrap; gap: .45rem; }
  .sa-workspace-link { display: inline-flex; align-items: center; min-height: 2.25rem; padding: .4rem .7rem; border: 1px solid var(--sa-border); border-radius: .55rem; color: var(--bs-body-color); background: transparent; text-decoration: none; font-size: .82rem; }
  .sa-workspace-link:hover { border-color: rgba(105,108,255,.35); color: var(--bs-primary); background: rgba(105,108,255,.04); }
  .sa-activity { border: 1px solid var(--sa-border); border-radius: .8rem; background: var(--bs-card-bg); overflow: hidden; }
  .sa-activity-row { display: flex; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; }
  .sa-activity-row + .sa-activity-row { border-top: 1px solid var(--sa-border); }
  .sa-activity-description { min-width: 0; }
  .sa-activity-meta { color: var(--bs-secondary-color); font-size: .75rem; white-space: nowrap; }
  @media (max-width: 767.98px) {
    .sa-home-header { display: block; }
    .sa-home-header .btn { margin-top: 1rem; width: 100%; }
    .sa-pulse { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .sa-pulse-item:nth-child(3) { border-left: 0; }
    .sa-pulse-item:nth-child(n+3) { border-top: 1px solid var(--sa-border); }
    .sa-workspace-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 419.98px) {
    .sa-home-title { align-items: flex-start; gap: .75rem; }
    .sa-home-icon { width: 2.75rem; height: 2.75rem; flex-basis: 2.75rem; }
    .sa-home-title h1 { font-size: 1.35rem; }
    .sa-pulse-item { padding: .85rem; }
    .sa-pulse-value { font-size: 1.05rem; }
    .sa-attention-item { align-items: flex-start; }
    .sa-activity-row { display: block; }
    .sa-activity-meta { margin-top: .25rem; white-space: normal; }
  }
</style>
@endsection

@section('content')
<div class="sa-home">
  <header class="sa-home-header mb-4">
    <div class="sa-home-title">
      <span class="sa-home-icon"><i class="ti ti-shield-check"></i></span>
      <div>
        <div class="text-uppercase text-primary fw-semibold small mb-1">Administration</div>
        <h1 class="h3 mb-1">Super Admin</h1>
        <p class="text-muted mb-0">What needs attention today, with platform tools one step away.</p>
      </div>
    </div>
  </header>

  <section class="mb-4" aria-labelledby="sa-pulse-heading">
    <h2 id="sa-pulse-heading" class="sa-section-title">Platform pulse</h2>
    <div class="sa-pulse">
      <div class="sa-pulse-item"><div class="sa-pulse-value">{{ number_format($activeEvents) }}</div><div class="sa-pulse-label">Active events</div></div>
      <div class="sa-pulse-item"><div class="sa-pulse-value">{{ number_format($recentRegistrations) }}</div><div class="sa-pulse-label">Registrations · 30 days</div></div>
      <div class="sa-pulse-item"><div class="sa-pulse-value">{{ number_format($newUsersThisWeek) }}</div><div class="sa-pulse-label">New users · this week</div></div>
      <div class="sa-pulse-item"><div class="sa-pulse-value {{ $currentBalance < 0 ? 'text-danger' : '' }}">R {{ number_format($currentBalance, 2) }}</div><div class="sa-pulse-label">{{ $currentYear }} event balance</div></div>
    </div>
  </section>

  <div class="row g-4">
    <div class="col-xl-7">
      <section class="mb-4" aria-labelledby="sa-today-heading">
        <h2 id="sa-today-heading" class="sa-section-title">Needs attention</h2>
        <div class="sa-attention-list">
          @if($pendingRefunds > 0)
            <a href="{{ route('admin.registration.refunds.bank.index') }}" class="sa-attention-item">
              <span class="sa-attention-icon bg-label-danger"><i class="ti ti-cash-banknote"></i></span>
              <span class="sa-attention-copy"><span class="sa-attention-title d-block">{{ $pendingRefunds }} bank refund{{ $pendingRefunds === 1 ? '' : 's' }} {{ $pendingRefunds === 1 ? 'requires' : 'require' }} processing</span><span class="sa-attention-note">Review bank details and complete the refund audit trail.</span></span>
              <i class="ti ti-chevron-right text-muted"></i>
            </a>
          @endif
          @if($activeSuspensions > 0)
            <a href="{{ route('backend.disciplinary.index') }}" class="sa-attention-item">
              <span class="sa-attention-icon bg-label-danger"><i class="ti ti-gavel"></i></span>
              <span class="sa-attention-copy"><span class="sa-attention-title d-block">{{ $activeSuspensions }} active suspension{{ $activeSuspensions === 1 ? '' : 's' }}</span><span class="sa-attention-note">Open the disciplinary workspace for details.</span></span>
              <i class="ti ti-chevron-right text-muted"></i>
            </a>
          @endif
          @if($playersNeedingAttention > 0)
            <a href="{{ url('backend/player') }}" class="sa-attention-item">
              <span class="sa-attention-icon bg-label-warning"><i class="ti ti-alert-triangle"></i></span>
              <span class="sa-attention-copy"><span class="sa-attention-title d-block">{{ number_format($playersNeedingAttention) }} player profiles need review</span><span class="sa-attention-note">Includes incomplete, stale and never-updated profiles.</span></span>
              <i class="ti ti-chevron-right text-muted"></i>
            </a>
          @endif
          @if($pendingRefunds === 0 && $activeSuspensions === 0 && $playersNeedingAttention === 0)
            <div class="sa-attention-item">
              <span class="sa-attention-icon bg-label-success"><i class="ti ti-circle-check"></i></span>
              <span class="sa-attention-copy"><span class="sa-attention-title d-block">No urgent operational items</span><span class="sa-attention-note">Refunds, suspensions and player-profile queues are clear.</span></span>
            </div>
          @endif
        </div>
      </section>

      <section aria-labelledby="sa-activity-heading">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <h2 id="sa-activity-heading" class="sa-section-title mb-0">Recent platform activity</h2>
          <a href="{{ route('superadmin.audit.index') }}" class="small">View audit</a>
        </div>
        <div class="sa-activity">
          @forelse($recentActivity as $activity)
            <div class="sa-activity-row">
              <div class="sa-activity-description">
                <span class="fw-semibold">{{ $activity->description ?: 'Administrative activity' }}</span>
                <span class="text-muted small d-block">{{ $activity->causer?->name ?? 'System' }}</span>
              </div>
              <div class="sa-activity-meta">{{ $activity->created_at?->diffForHumans() }}</div>
            </div>
          @empty
            <div class="p-3 text-muted small">No recent administrative activity.</div>
          @endforelse
        </div>
      </section>
    </div>

    <div class="col-xl-5">
      <section aria-labelledby="sa-workspaces-heading">
        <h2 id="sa-workspaces-heading" class="sa-section-title">Workspaces</h2>
        <div class="sa-workspace-grid">
          <div class="sa-workspace">
            <div class="sa-workspace-heading"><i class="ti ti-calendar-event text-primary"></i>Operations</div>
            <div class="sa-workspace-links">
              <a class="sa-workspace-link" href="{{ route('backend.dashboard') }}#tab-events">Events</a>
              <a class="sa-workspace-link" href="{{ url('backend/user') }}">Users</a>
              <a class="sa-workspace-link" href="{{ url('backend/player') }}">Players</a>
              <a class="sa-workspace-link" href="{{ url('backend/series') }}">Series</a>
              <a class="sa-workspace-link" href="{{ url('backend/league') }}">Leagues</a>
            </div>
          </div>
          <div class="sa-workspace">
            <div class="sa-workspace-heading"><i class="ti ti-report-money text-success"></i>Finance</div>
            <div class="sa-workspace-links">
              <a class="sa-workspace-link" href="{{ route('superadmin.finances') }}">Finance dashboard</a>
              <a class="sa-workspace-link" href="{{ route('admin.registration.refunds.bank.index') }}">Bank refunds @if($pendingRefunds > 0)<span class="badge bg-danger ms-1">{{ $pendingRefunds }}</span>@endif</a>
              <a class="sa-workspace-link" href="{{ route('backend.superadmin.workspace', ['tab' => 'wallets']) }}">Wallets</a>
            </div>
          </div>
          <div class="sa-workspace">
            <div class="sa-workspace-heading"><i class="ti ti-scale text-warning"></i>Governance</div>
            <div class="sa-workspace-links">
              <a class="sa-workspace-link" href="{{ route('backend.agreements.index') }}">Agreements</a>
              <a class="sa-workspace-link" href="{{ route('backend.disciplinary.index') }}">Disciplinary</a>
              <a class="sa-workspace-link" href="{{ route('superadmin.audit.index') }}">Audit trail</a>
            </div>
          </div>
          <div class="sa-workspace">
            <div class="sa-workspace-heading"><i class="ti ti-adjustments text-info"></i>Platform</div>
            <div class="sa-workspace-links">
              <a class="sa-workspace-link" href="{{ route('settings.index') }}">Settings</a>
              <a class="sa-workspace-link" href="{{ route('platform.health') }}">Platform health</a>
              <a class="sa-workspace-link" href="{{ route('superadmin.api-integrations.index') }}">API connections</a>
              <a class="sa-workspace-link" href="{{ url('backend/eventPhoto') }}">Photos</a>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
