@if($application->court_plan)
<div class="alert alert-info mt-3">
@if(($application->court_plan['model'] ?? null) === 'court-ratio-v2')
<strong>Proposed court allocation</strong>
<p class="mb-1">{{ $application->court_plan['assigned_total'] }} of {{ $application->court_plan['inputs']['courts'] }} full-size courts assigned · {{ $application->court_plan['suggested_entries'] }} suggested places.</p>
<p class="mb-1">@foreach($application->court_plan['rows'] as $row){{ $row['name'] }}: {{ $row['assigned_courts'] }} courts → {{ $row['limit'] }} places. @endforeach</p>
<p class="mb-0">Red uses 16 players per full-size court with four mini courts; Orange, Green and Yellow use four players per court. Requested limits may be lower. Layout and schedule still require review.</p>
@else
<strong>Original time-based estimate</strong>
<p class="small mb-1">Historical time-based estimate, retained as originally submitted.</p>
<p class="mb-1">{{ $application->court_plan['inputs']['courts'] }} courts × {{ number_format($application->court_plan['inputs']['hours_hundredths'] / 100, 2) }} hours; {{ $application->court_plan['inputs']['slot_minutes'] }}-minute average slots and {{ $application->court_plan['inputs']['contingency_percent'] }}% contingency.</p>
<p class="mb-0">{{ $application->court_plan['suggested_entries'] }} suggested places, {{ $application->court_plan['planned_matches'] }} planned matches within {{ $application->court_plan['usable_minutes'] }} usable court-minutes. Four-player groups plus group-winner knockouts; final schedule, rest and layouts still need review. Requested limits may be lower than this estimate.</p>
@endif
</div>
@if(isset($application->court_plan['financial_projection']))
  @include('frontend.host-events.financial-projection', ['projection' => $application->court_plan['financial_projection']])
@endif
@else
<p class="small text-muted">No court-capacity estimate was recorded for this earlier proposal.</p>
@endif
