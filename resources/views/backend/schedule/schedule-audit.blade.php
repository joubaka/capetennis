@extends('layouts.backend')
@section('title', 'Schedule audit – '.$event->name)
@section('content')
<div class="container-xxl py-3">
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div><h3>Schedule audit</h3><p class="text-muted mb-0">{{ $event->name }} · {{ $scope['date'] === 'all' ? 'All saved days' : \Carbon\Carbon::parse($scope['date'])->format('D j M Y') }}{{ !empty($scope['draw_id']) ? ' · Selected draw' : ' · All draws' }}{{ !empty($scope['venue_id']) ? ' · Selected venue' : ' · All venues' }}</p></div><a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id]+$scope) }}">Back to saved schedule</a></div>
  <p class="small text-muted">This audit reads saved matches. It does not save, reschedule, publish or hide anything.</p>
  <div class="card card-body mb-3"><div class="d-flex flex-wrap gap-4"><strong>{{ $report['checked'] }} saved matches checked in this view</strong><span class="text-danger">{{ $report['errors'] }} errors</span><span class="text-warning">{{ $report['warnings'] }} warnings</span></div><p class="small text-muted mt-2 mb-0">{{ $report['compared_event_matches'] }} event bookings compared · {{ $report['external_bookings'] }} nearby outside-event bookings compared · Rest {{ $report['rest_minutes'] }} minutes · Court turnaround {{ $report['court_gap_minutes'] }} minutes.</p></div>
  @if($report['unscheduled'])<div class="alert alert-warning">{{ $report['unscheduled'] }} eligible unfinished matches remain undated in the selected draw or event. Undated matches cannot be assigned to a day or venue filter.</div>@endif
  @if($report['incomplete'])<div class="alert alert-warning">The audit is inconclusive. {{ $report['coverage'] }}</div>@endif
  @if(!$report['incomplete'] && !$report['errors'] && !$report['warnings'])<div class="alert alert-info">No issues were detected among the checked saved bookings within this audit's coverage.</div>@endif
  @foreach($report['issues'] as $issue)<div class="card mb-2 border-start border-3 {{ $issue['severity']==='error'?'border-danger':'border-warning' }}"><div class="card-body"><strong>{{ ucfirst($issue['severity']) }} · {{ $issue['message'] }}</strong><div class="d-flex flex-wrap gap-2 mt-2">@foreach($issue['matches'] as $match)<a class="btn btn-sm btn-outline-primary" href="{{ $match['url'] }}">{{ $match['label'] }} · {{ $match['time'] }}</a>@endforeach</div>@if($issue['external'])<p class="small text-muted mt-2 mb-0">This finding also involves an occupied slot outside this event. Its private match details are omitted.</p>@endif</div></div>@endforeach
  @if($report['omitted_issues'])<div class="alert alert-warning">{{ $report['omitted_issues'] }} additional findings are included in the totals. Only the first 200 findings are displayed; narrow the day, venue or draw filter to review them.</div>@endif
  <p class="small text-muted mt-3">{{ $report['coverage'] }}</p>
</div>
@endsection
