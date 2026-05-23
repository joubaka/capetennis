@extends('layouts/layoutMaster')

@section('title', 'Platform Health')

@section('page-style')
<style>
  .health-section { margin-bottom: 1.5rem; }
  .health-section .card-header { font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: .5rem; }
  .badge-ok       { background-color: #28a745; color: #fff; font-size: .75rem; padding: .25em .55em; border-radius: 4px; }
  .badge-warn     { background-color: #fd7e14; color: #fff; font-size: .75rem; padding: .25em .55em; border-radius: 4px; }
  .badge-critical { background-color: #dc3545; color: #fff; font-size: .75rem; padding: .25em .55em; border-radius: 4px; }
  .health-row td  { vertical-align: middle; font-size: .875rem; }
  .health-value   { font-weight: 600; }
  .health-detail  { color: #666; font-size: .8rem; }
  .summary-bar    { border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; gap: 2rem; align-items: center; }
  .summary-ok     { background: #d4edda; border-left: 5px solid #28a745; }
  .summary-warn   { background: #fff3cd; border-left: 5px solid #ffc107; }
  .summary-crit   { background: #f8d7da; border-left: 5px solid #dc3545; }
  .stat-box       { text-align: center; }
  .stat-box .num  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
  .stat-box .lbl  { font-size: .75rem; color: #555; }
  .refresh-note   { font-size: .75rem; color: #888; }
  .section-icon   { font-size: 1.1rem; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Page header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">Platform Health Dashboard</h4>
      <small class="text-muted">Operational status snapshot &mdash; auto-refreshes every 60 s</small>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('platform.health.api') }}" class="btn btn-sm btn-outline-secondary" target="_blank">JSON API</a>
      <button onclick="location.reload()" class="btn btn-sm btn-outline-primary">↺ Refresh</button>
    </div>
  </div>

  {{-- Summary bar --}}
  @php
    $barClass = $summary['critical'] > 0 ? 'summary-crit'
              : ($summary['warn'] > 0 ? 'summary-warn' : 'summary-ok');
    $barIcon  = $summary['critical'] > 0 ? '🔴' : ($summary['warn'] > 0 ? '🟡' : '🟢');
    $barLabel = $summary['critical'] > 0
              ? "{$summary['critical']} critical issue(s) — action required"
              : ($summary['warn'] > 0 ? "{$summary['warn']} warning(s) — review recommended" : 'All systems healthy');
  @endphp
  <div class="summary-bar {{ $barClass }}">
    <span style="font-size:1.8rem">{{ $barIcon }}</span>
    <div class="flex-grow-1">
      <strong>{{ $barLabel }}</strong>
      <div class="refresh-note">Last checked: {{ now()->format('d M Y H:i:s') }}</div>
    </div>
    <div class="stat-box"><div class="num text-danger">{{ $summary['critical'] }}</div><div class="lbl">Critical</div></div>
    <div class="stat-box"><div class="num text-warning">{{ $summary['warn'] }}</div><div class="lbl">Warnings</div></div>
    <div class="stat-box"><div class="num text-success">{{ $summary['ok'] }}</div><div class="lbl">Passing</div></div>
  </div>

  <div class="row">

    {{-- ---- ENGINE HEALTH ---- --}}
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">⚙️</span> Engine Health
          @include('backend.platform._health_badge', ['items' => $engine])
        </div>
        <div class="card-body p-0">
          @include('backend.platform._health_table', ['items' => $engine])
        </div>
      </div>
    </div>

    {{-- ---- FINANCIAL HEALTH ---- --}}
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">💰</span> Financial Health
          @include('backend.platform._health_badge', ['items' => $financial])
        </div>
        <div class="card-body p-0">
          @include('backend.platform._health_table', ['items' => $financial])
        </div>
      </div>
    </div>

    {{-- ---- DRAW HEALTH ---- --}}
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">🎾</span> Draw Health
          @include('backend.platform._health_badge', ['items' => $draw])
        </div>
        <div class="card-body p-0">
          @include('backend.platform._health_table', ['items' => $draw])
        </div>
      </div>
    </div>

    {{-- ---- REGISTRATION HEALTH ---- --}}
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">📋</span> Registration Health
          @include('backend.platform._health_badge', ['items' => $registration])
        </div>
        <div class="card-body p-0">
          @include('backend.platform._health_table', ['items' => $registration])
        </div>
      </div>
    </div>

    {{-- ---- QUEUE HEALTH ---- --}}
    <div class="col-md-4 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">📬</span> Queue Health
          @include('backend.platform._health_badge', ['items' => $queue])
        </div>
        <div class="card-body p-0">
          @include('backend.platform._health_table', ['items' => $queue])
        </div>
      </div>
    </div>

    {{-- ---- SYSTEM HEALTH ---- --}}
    <div class="col-md-8 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">🖥️</span> System Health
          @include('backend.platform._health_badge', ['items' => $system])
        </div>
        <div class="card-body p-0">
          @include('backend.platform._health_table', ['items' => $system])
        </div>
      </div>
    </div>

  </div>

  {{-- Quick actions --}}
  <div class="card mt-2">
    <div class="card-header fw-semibold">🛠 Quick Integrity Actions</div>
    <div class="card-body">
      <p class="text-muted small mb-2">Run these commands on the server to investigate or resolve issues found above.</p>
      <div class="row g-2">
        @foreach([
          ['php artisan schema:integrity-check',                        'Full integrity check (read-only)'],
          ['php artisan draw:integrity-check',                          'Draw + fixture integrity'],
          ['php artisan finance:integrity-check',                       'Financial integrity'],
          ['php artisan data:cleanup-duplicate-payfast-ids --dry-run',  'PayFast duplicate review'],
          ['php artisan data:cleanup-duplicate-fixture-results --dry-run', 'Duplicate results preview'],
          ['php artisan data:cleanup-orphan-fixtures --dry-run',        'Orphan fixtures preview'],
          ['php artisan platform:preflight',                            'Pre-deploy safety check'],
          ['php artisan platform:health-check',                         'CLI health check'],
        ] as [$cmd, $label])
        <div class="col-md-6">
          <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
            <code class="flex-grow-1" style="font-size:.78rem">{{ $cmd }}</code>
            <small class="text-muted text-nowrap">{{ $label }}</small>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
  // Auto-refresh every 60 seconds
  setTimeout(() => location.reload(), 60000);
</script>
@endsection
