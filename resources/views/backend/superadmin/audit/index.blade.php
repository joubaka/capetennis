@extends('layouts.backend')

@section('title', 'Audit Centre')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-shield-search me-2 text-primary"></i>Audit Centre</h4>
      <p class="text-muted mb-0">Append-only user, system, data-change and navigation history.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('superadmin.audit.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
        <i class="ti ti-download me-1"></i>Export filtered CSV
      </a>
      <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">Super Admin</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    @foreach([
      ['Today', $stats['today'], 'ti-activity', 'primary'],
      ['Denied · 7 days', $stats['denied_7d'], 'ti-shield-x', 'danger'],
      ['Deletions · 30 days', $stats['deletions_30d'], 'ti-trash', 'warning'],
      ['Active users · 30 days', $stats['users_30d'], 'ti-users', 'info'],
    ] as [$label, $value, $icon, $colour])
      <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-center">
            <div><small class="text-muted">{{ $label }}</small><div class="fs-4 fw-semibold">{{ number_format($value) }}</div></div>
            <i class="ti {{ $icon }} ti-lg text-{{ $colour }}"></i>
          </div>
        </div></div>
      </div>
    @endforeach
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Filters</h5></div>
    <div class="card-body">
      <form method="GET" action="{{ route('superadmin.audit.index') }}" class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label" for="audit-search">Search</label>
          <input id="audit-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="User, email, action, page or request ID">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-from">From</label>
          <input id="audit-from" type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-to">To</label>
          <input id="audit-to" type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-category">Category</label>
          <select id="audit-category" name="category" class="form-select">
            <option value="">All</option>
            @foreach($categories as $category)
              <option value="{{ $category }}" @selected(request('category') === $category)>{{ ucfirst($category) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-outcome">Outcome</label>
          <select id="audit-outcome" name="outcome" class="form-select">
            <option value="">All</option>
            @foreach(['succeeded', 'denied', 'failed', 'attempted'] as $outcome)
              <option value="{{ $outcome }}" @selected(request('outcome') === $outcome)>{{ ucfirst($outcome) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="audit-action">Action</label>
          <select id="audit-action" name="action" class="form-select">
            <option value="">All actions</option>
            @foreach($actions as $action)
              <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-actor">User ID</label>
          <input id="audit-actor" type="number" min="1" name="actor_id" value="{{ request('actor_id') }}" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-event">Event ID</label>
          <input id="audit-event" type="number" min="1" name="event_id" value="{{ request('event_id') }}" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-subject-type">Subject type</label>
          <input id="audit-subject-type" name="subject_type" value="{{ request('subject_type') }}" class="form-control" placeholder="Player">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-subject-id">Subject ID</label>
          <input id="audit-subject-id" name="subject_id" value="{{ request('subject_id') }}" class="form-control">
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary"><i class="ti ti-filter me-1"></i>Apply filters</button>
          <a href="{{ route('superadmin.audit.index') }}" class="btn btn-outline-secondary">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Audit events</h5>
      <span class="badge bg-label-secondary">{{ number_format($events->total()) }} records</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Date / time</th><th>User</th><th>Action</th><th>Subject</th><th>Page</th><th>Outcome</th><th></th>
        </tr></thead>
        <tbody>
          @forelse($events as $event)
            @php
              $badge = match($event->outcome) {'succeeded' => 'success', 'denied' => 'danger', 'failed' => 'warning', default => 'secondary'};
              $subjectName = $event->subject_type ? class_basename($event->subject_type) : null;
            @endphp
            <tr>
              <td class="text-nowrap">
                {{ $event->occurred_at?->timezone(config('app.timezone'))->format('d M Y H:i:s') }}
                <small class="d-block text-muted">{{ $event->occurred_at?->diffForHumans() }}</small>
              </td>
              <td>
                <span class="fw-medium">{{ $event->actor_name ?? ($event->actor_type === 'system' ? 'System' : 'Anonymous') }}</span>
                @if($event->actor_email)<small class="d-block text-muted">{{ $event->actor_email }}</small>@endif
              </td>
              <td><span class="badge bg-label-primary">{{ $event->category }}</span><code class="d-block mt-1">{{ $event->action }}</code></td>
              <td>
                @if($subjectName)
                  {{ $subjectName }} #{{ $event->subject_id }}
                  @if($event->subject_label)<small class="d-block text-muted text-truncate" style="max-width:220px">{{ $event->subject_label }}</small>@endif
                @else — @endif
              </td>
              <td>
                <span class="text-truncate d-block" style="max-width:240px" title="{{ $event->path }}">{{ $event->route_name ?? $event->path ?? '—' }}</span>
                @if($event->http_method)<small class="text-muted">{{ $event->http_method }} · HTTP {{ $event->status_code ?? '—' }}</small>@endif
              </td>
              <td><span class="badge bg-{{ $badge }}">{{ ucfirst($event->outcome) }}</span></td>
              <td><a href="{{ route('superadmin.audit.show', $event) }}" class="btn btn-sm btn-icon btn-outline-primary" title="View detail"><i class="ti ti-eye"></i></a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No audit events match these filters.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($events->hasPages())
      <div class="card-footer">{{ $events->links('pagination::bootstrap-5') }}</div>
    @endif
  </div>
</div>
@endsection
