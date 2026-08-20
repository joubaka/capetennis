@extends('layouts.layoutMaster')

@section('title', 'API Connections')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="mb-1"><i class="ti ti-plug-connected me-2 text-primary"></i>API Connections</h4>
    <p class="text-muted mb-0">Academies and websites with Cape Tennis API access, based on their key and actual API traffic.</p>
  </div>
  <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm align-self-start">
    <i class="ti ti-arrow-left me-1"></i>Super Admin
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Configured</div><div class="fs-3 fw-bold">{{ $summary['total'] }}</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100 border-success"><div class="card-body"><div class="text-success small">Connected</div><div class="fs-3 fw-bold text-success">{{ $summary['active'] }}</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100 border-warning"><div class="card-body"><div class="text-warning small">Connecting</div><div class="fs-3 fw-bold text-warning">{{ $summary['connecting'] }}</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100 border-danger"><div class="card-body"><div class="text-danger small">Needs review</div><div class="fs-3 fw-bold text-danger">{{ $summary['attention'] }}</div></div></div></div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('superadmin.api-integrations.index') }}" class="row g-2 align-items-end">
      <div class="col-12 col-md-5 col-lg-3">
        <label for="status" class="form-label">Filter by status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All connections</option>
          @foreach([
            'active' => 'Active', 'connected' => 'Connected', 'connecting' => 'Trying to connect',
            'awaiting_connection' => 'Awaiting first connection', 'needs_attention' => 'Needs attention',
            'rate_limited' => 'Rate limited', 'inactive' => 'No recent activity', 'expired' => 'Expired'
          ] as $value => $label)
            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto"><button class="btn btn-primary" type="submit">Apply</button></div>
      @if($selectedStatus !== '')
        <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('superadmin.api-integrations.index') }}">Clear</a></div>
      @endif
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Academy / website</th>
          <th>Status</th>
          <th>Last successful link</th>
          <th>Latest attempt</th>
          <th class="text-end">Requests (24h)</th>
          <th>Key expiry</th>
        </tr>
      </thead>
      <tbody>
        @forelse($integrations as $integration)
          <tr>
            <td>
              <div class="fw-semibold">{{ $integration['name'] }}</div>
              <div class="small text-muted">Managed by {{ $integration['owner']?->name ?? 'Cape Tennis' }}</div>
            </td>
            <td style="min-width: 220px">
              <span class="badge bg-label-{{ $integration['status_colour'] }}">{{ $integration['status_label'] }}</span>
              <div class="small text-muted mt-1">{{ $integration['status_detail'] }}</div>
            </td>
            <td>
              @if($integration['latest_success_at'])
                <div>{{ $integration['latest_success_at']->format('d M Y, H:i') }}</div>
                <div class="small text-muted">{{ $integration['latest_success_at']->diffForHumans() }}</div>
              @else
                <span class="text-muted">Never</span>
              @endif
            </td>
            <td>
              @if($integration['latest_attempt_at'])
                <div>{{ $integration['latest_attempt_at']->format('d M Y, H:i') }}</div>
                <div class="small text-muted">
                  HTTP {{ $integration['latest_status_code'] ?? '—' }}
                  @if($integration['latest_endpoint']) · {{ str($integration['latest_endpoint'])->afterLast('.')->replace('_', ' ')->title() }} @endif
                </div>
              @elseif($integration['last_used_at'])
                <div>{{ $integration['last_used_at']->format('d M Y, H:i') }}</div>
                <div class="small text-muted">Key use detected</div>
              @else
                <span class="text-muted">No attempt detected</span>
              @endif
            </td>
            <td class="text-end fw-semibold">{{ number_format($integration['requests_last_24_hours']) }}</td>
            <td>
              @if($integration['expires_at'])
                <div>{{ $integration['expires_at']->format('d M Y') }}</div>
                <div class="small text-muted">{{ $integration['expires_at']->diffForHumans() }}</div>
              @else
                <span class="text-warning">No expiry set</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center py-5">
            <i class="ti ti-plug-off fs-1 text-muted d-block mb-2"></i>
            <div class="fw-semibold">No API connections found</div>
            <div class="small text-muted">No integration key matches this filter.</div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-4 mb-0">
  <i class="ti ti-info-circle me-1"></i>
  A connection is only marked active after Cape Tennis records a successful API request. API keys and secrets are never displayed here.
</div>
@endsection
