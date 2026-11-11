<h2>{{ $report['event_name'] }} — Private nomination review</h2>
<p>Please review the attached selected players, up to five reserves, and explanations for the next five candidates outside each nomination list and the Inter-Districts players outside the saved top 32.</p>
<p>The explanations show counted wins, losses and recorded playing numbers. Result flags support review; other omissions have no individual reason recorded.</p>
<p>This is a private review copy. Please keep the report within the selected review group.</p>
@if ($report['unsaved_divisions'])<p><strong>Divisions without a saved roster, excluded from this report:</strong> {{ implode(', ', $report['unsaved_divisions']) }}.</p>@endif
<p><strong>Included divisions:</strong> {{ implode(', ', array_column($report['sections'], 'division')) }}.</p>
