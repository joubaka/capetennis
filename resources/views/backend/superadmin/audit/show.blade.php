@extends('layouts.backend')

@section('title', 'Audit Event')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-shield-check me-2 text-primary"></i>{{ $auditEvent->action }}</h4>
      <p class="text-muted mb-0">{{ $auditEvent->event_uuid }}</p>
    </div>
    <a href="{{ route('superadmin.audit.index') }}" class="btn btn-outline-secondary btn-sm">Back to Audit Centre</a>
  </div>

  <div class="alert alert-{{ $integrityValid ? 'success' : 'danger' }} d-flex align-items-center gap-2">
    <i class="ti {{ $integrityValid ? 'ti-shield-check' : 'ti-shield-x' }}"></i>
    {{ $integrityValid ? 'Integrity hash verified for this record.' : 'Integrity verification failed. Treat this record as potentially altered and investigate immediately.' }}
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Context</h5></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5">When</dt><dd class="col-7">{{ $auditEvent->occurred_at?->timezone(config('app.timezone'))->format('d M Y H:i:s.u T') }}</dd>
            <dt class="col-5">User</dt><dd class="col-7">{{ $auditEvent->actor_name ?? 'System / anonymous' }} @if($auditEvent->actor_id)(#{{ $auditEvent->actor_id }})@endif</dd>
            <dt class="col-5">Email</dt><dd class="col-7">{{ $auditEvent->actor_email ?? '—' }}</dd>
            <dt class="col-5">Roles</dt><dd class="col-7">{{ implode(', ', $auditEvent->actor_roles ?? []) ?: '—' }}</dd>
            <dt class="col-5">Outcome</dt><dd class="col-7">{{ ucfirst($auditEvent->outcome) }}</dd>
            <dt class="col-5">Subject</dt><dd class="col-7">{{ $auditEvent->subject_type ? class_basename($auditEvent->subject_type).' #'.$auditEvent->subject_id : '—' }}<br><small class="text-muted">{{ $auditEvent->subject_label }}</small></dd>
            <dt class="col-5">Event ID</dt><dd class="col-7">{{ $auditEvent->event_id ?? '—' }}</dd>
            <dt class="col-5">Route</dt><dd class="col-7"><code>{{ $auditEvent->route_name ?? '—' }}</code></dd>
            <dt class="col-5">Request</dt><dd class="col-7"><code class="text-break">{{ $auditEvent->request_id ?? '—' }}</code></dd>
            <dt class="col-5">Page</dt><dd class="col-7" class="text-break">{{ $auditEvent->http_method }} {{ $auditEvent->path }}</dd>
            <dt class="col-5">Previous page</dt><dd class="col-7 text-break">{{ $auditEvent->referrer ?? '—' }}</dd>
            <dt class="col-5">IP address</dt><dd class="col-7"><code>{{ $auditEvent->ip_address ?? '—' }}</code></dd>
            <dt class="col-5">Device</dt><dd class="col-7"><small>{{ $auditEvent->user_agent ?? '—' }}</small></dd>
            <dt class="col-5">Reason</dt><dd class="col-7">{{ $auditEvent->reason ?? '—' }}</dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Before and after</h5></div>
        <div class="card-body row g-3">
          <div class="col-12 col-lg-6"><h6>Before</h6><pre class="bg-light border rounded p-3 small overflow-auto" style="max-height:420px">{{ json_encode($auditEvent->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre></div>
          <div class="col-12 col-lg-6"><h6>After</h6><pre class="bg-light border rounded p-3 small overflow-auto" style="max-height:420px">{{ json_encode($auditEvent->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre></div>
          <div class="col-12"><h6>Metadata</h6><pre class="bg-light border rounded p-3 small overflow-auto" style="max-height:320px">{{ json_encode($auditEvent->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre></div>
        </div>
      </div>
    </div>
  </div>

  @if($journey->isNotEmpty())
    <div class="card mt-4">
      <div class="card-header"><h5 class="mb-0">User journey</h5></div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Time</th><th>Action</th><th>Page</th><th>Outcome</th><th></th></tr></thead>
        <tbody>@foreach($journey as $item)<tr class="{{ $item->id === $auditEvent->id ? 'table-primary' : '' }}">
          <td>{{ $item->occurred_at?->timezone(config('app.timezone'))->format('H:i:s') }}</td><td>{{ $item->action }}</td><td>{{ $item->route_name ?? $item->path }}</td><td>{{ $item->outcome }}</td>
          <td><a href="{{ route('superadmin.audit.show', $item) }}" class="btn btn-sm btn-icon btn-outline-secondary"><i class="ti ti-eye"></i></a></td>
        </tr>@endforeach</tbody>
      </table></div>
    </div>
  @endif

  @if($subjectTimeline->count() > 1)
    <div class="card mt-4">
      <div class="card-header"><h5 class="mb-0">Subject history</h5></div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Outcome</th><th></th></tr></thead>
        <tbody>@foreach($subjectTimeline as $item)<tr>
          <td>{{ $item->occurred_at?->timezone(config('app.timezone'))->format('d M Y H:i:s') }}</td><td>{{ $item->actor_name ?? 'System' }}</td><td>{{ $item->action }}</td><td>{{ $item->outcome }}</td>
          <td><a href="{{ route('superadmin.audit.show', $item) }}" class="btn btn-sm btn-icon btn-outline-secondary"><i class="ti ti-eye"></i></a></td>
        </tr>@endforeach</tbody>
      </table></div>
    </div>
  @endif
</div>
@endsection
