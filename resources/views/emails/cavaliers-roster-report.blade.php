<h2>{{ $report['event_name'] }} — Private nomination review</h2>
<p>Please review the attached nomination report with the selected players and up to five ordered reserves in each prepared division, followed by the player explanation list.</p>
<p>The evidence distinguishes players who lost all counted singles and players whose only wins were against opponents who lost all counted singles. These result flags support review; an individual selection decision is only confirmed where recorded. Other omissions have no individual reason recorded.</p>
<p>This is a private review copy. Please keep the report within the selected review group.</p>
@if ($report['unsaved_divisions'])<p><strong>Divisions without a saved roster, excluded from this report:</strong> {{ implode(', ', $report['unsaved_divisions']) }}.</p>@endif
<p><strong>Included divisions:</strong> {{ implode(', ', array_column($report['sections'], 'division')) }}.</p>
