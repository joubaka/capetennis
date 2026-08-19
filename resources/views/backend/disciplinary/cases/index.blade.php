@extends('layouts/layoutMaster')
@section('title', 'Disciplinary Cases')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-scale me-2"></i>{{ isset($event) ? $event->name.' Discipline' : 'Disciplinary Cases' }}</h4>
      <p class="text-muted mb-0">Incident, panel decision, sanction and appeal workflow</p>
    </div>
    @if(isset($event) && \App\Models\SiteSetting::disciplinarySystemEnabled())
      <a class="btn btn-primary" href="{{ route('backend.events.disciplinary.create', $event) }}"><i class="ti ti-plus me-1"></i>Report incident</a>
    @endif
  </div>

  @unless(\App\Models\SiteSetting::disciplinarySystemEnabled())
    <div class="alert alert-warning"><i class="ti ti-lock me-1"></i>The disciplinary case system is disabled. Historical cases remain available for audit, but no workflow actions can be performed.</div>
  @endunless

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        @unless(isset($event))
        <div class="col-md-5"><label class="form-label">Event</label><select name="event_id" class="form-select"><option value="">All permitted events</option>@foreach($events as $item)<option value="{{ $item->id }}" @selected(request('event_id') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
        @endunless
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['submitted','triage','awaiting_response','panel_review','decided','appealed','final','dismissed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
        <div class="col-md-3"><button class="btn btn-outline-primary w-100">Filter</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Case</th><th>Incident</th><th>Event</th><th>Player</th><th>Charge</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($cases as $case)
          <tr>
            <td><strong>{{ $case->case_number }}</strong><br><small class="text-muted">{{ ucfirst($case->severity) }}</small></td>
            <td>{{ $case->incident_at->format('d M Y H:i') }}</td>
            <td>{{ $case->event?->name }}</td>
            <td>{{ $case->player?->full_name }}</td>
            <td>{{ $case->charges->pluck('rule_title')->join(', ') }}</td>
            <td><span class="badge bg-label-{{ in_array($case->status, ['decided','final']) ? 'success' : ($case->status === 'dismissed' ? 'secondary' : 'warning') }}">{{ str($case->status)->replace('_', ' ')->title() }}</span></td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('backend.disciplinary.cases.show', $case) }}">Open</a></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-5">No disciplinary cases found.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer">{{ $cases->links() }}</div>
  </div>
</div>
@endsection
