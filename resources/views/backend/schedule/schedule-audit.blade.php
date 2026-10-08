@extends('layouts.backend')
@section('title', 'Schedule audit – '.$event->name)
@section('content')
<style>[data-audit-issue][hidden],[data-audit-severity-group][hidden]{display:none!important;}</style>
<div class="container-xxl py-3">
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div><h3>Schedule audit</h3><p class="text-muted mb-0">{{ $event->name }} · {{ $scope['date'] === 'all' ? 'All saved days' : \Carbon\Carbon::parse($scope['date'])->format('D j M Y') }}{{ !empty($scope['draw_id']) ? ' · Selected draw' : ' · All draws' }}{{ !empty($scope['venue_id']) ? ' · Selected venue' : ' · All venues' }}</p></div><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id]+$scope) }}">Back to saved schedule</a></div>
  <p class="small text-muted">This audit reads saved matches. It does not save, reschedule, publish or hide anything.</p>
  <div class="card card-body mb-3"><div class="d-flex flex-wrap gap-4"><strong>{{ $report['checked'] }} saved matches checked in this view</strong><span class="text-danger">{{ $report['errors'] }} errors</span><span class="text-warning">{{ $report['warnings'] }} warnings</span></div><p class="small text-muted mt-2 mb-0">{{ $report['compared_event_matches'] }} event bookings compared · {{ $report['external_bookings'] }} nearby outside-event bookings compared · Rest {{ $report['rest_minutes'] }} minutes · Court turnaround {{ $report['court_gap_minutes'] }} minutes.</p></div>
  @if($report['unscheduled'])<div class="alert alert-warning">{{ $report['unscheduled'] }} eligible unfinished matches remain undated in the selected draw or event. Undated matches cannot be assigned to a day or venue filter.</div>@endif
  @if($report['incomplete'])<div class="alert alert-warning">The audit is inconclusive. {{ $report['coverage'] }}</div>@endif
  @if(!$report['incomplete'] && !$report['errors'] && !$report['warnings'])<div class="alert alert-info">No issues were detected among the checked saved bookings within this audit's coverage.</div>@endif
  <div class="card card-body mb-3 no-print">
    <div class="row g-3">
      <div class="col-md-6"><label for="audit-severity" class="form-label">Severity</label><select id="audit-severity" class="form-select"><option value="">All severities</option><option value="error">Errors</option><option value="warning">Warnings</option></select></div>
      <div class="col-md-6"><label for="audit-type" class="form-label">Finding type</label><select id="audit-type" class="form-select"><option value="">All finding types</option>@foreach(collect($report['issues'])->pluck('code')->unique() as $code)<option value="{{ $code }}">{{ ucwords(str_replace('_', ' ', $code)) }}</option>@endforeach</select></div>
    </div>
    <p class="small text-muted mt-2 mb-0">Filters change the displayed findings only. Audit totals and coverage remain unchanged.</p>
    <p class="small mt-2 mb-0" data-audit-filter-count role="status" aria-live="polite"></p>
  </div>
  @foreach(collect($report['issues'])->groupBy('severity') as $severity => $severityIssues)
    <section data-audit-severity-group><h4 class="h5">{{ ucfirst($severity) }} findings</h4>
    @foreach($severityIssues as $issue)<div data-audit-issue data-severity="{{ $issue['severity'] }}" data-type="{{ $issue['code'] }}" class="card mb-2 border-start border-3 {{ $issue['severity']==='error'?'border-danger':'border-warning' }}"><div class="card-body"><strong>{{ ucfirst($issue['severity']) }} · {{ $issue['message'] }}</strong><div class="d-flex flex-wrap gap-2 mt-2">@foreach($issue['matches'] as $match)<a class="btn btn-sm btn-outline-primary" href="{{ $match['url'] }}">{{ $match['label'] }} · {{ $match['time'] }}</a>@endforeach</div>@if($issue['external'])<p class="small text-muted mt-2 mb-0">This finding also involves an occupied slot outside this event. Its private match details are omitted.</p>@endif</div></div>@endforeach
    </section>
  @endforeach
  @if($report['omitted_issues'])<div class="alert alert-warning">{{ $report['omitted_issues'] }} additional findings are included in the totals. Only the first 200 findings are displayed; narrow the day, venue or draw filter to review them.</div>@endif
  <p class="small text-muted mt-3">{{ $report['coverage'] }}</p>
</div>
@endsection
@section('page-script')
<script>
const auditFindings = Array.from(document.querySelectorAll('[data-audit-issue]'));
function filterAuditFindings() {
  const severity = document.getElementById('audit-severity').value, type = document.getElementById('audit-type').value;
  let shown = 0;
  auditFindings.forEach(finding => { finding.hidden = Boolean((severity && finding.dataset.severity !== severity) || (type && finding.dataset.type !== type)); if (!finding.hidden) shown++; });
  document.querySelectorAll('[data-audit-severity-group]').forEach(group => { group.hidden = !Array.from(group.querySelectorAll('[data-audit-issue]')).some(finding => !finding.hidden); });
  document.querySelector('[data-audit-filter-count]').textContent = `${shown} of ${auditFindings.length} displayed findings match these filters.`;
}
document.getElementById('audit-severity').addEventListener('change', filterAuditFindings);
document.getElementById('audit-type').addEventListener('change', filterAuditFindings);
filterAuditFindings();
</script>
@endsection
