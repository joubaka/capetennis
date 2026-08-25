<p>Masters invitation update: <strong>{{ $action }}</strong></p>
<p>Event: {{ $invitation->batch?->event?->name ?? 'Masters event' }}</p>
<p>Age group: {{ $invitation->categoryEvent?->category?->name ?? 'Unknown' }}</p>
<p>Player: {{ $invitation->player?->full_name ?? $invitation->player_id }}</p>
<p>Ranking position: {{ $invitation->ranking_position }}</p>
<p>Invitation status: {{ $invitation->status }}</p>
@if($replacement)
  <p>Replacement: {{ $replacement->player?->full_name ?? $replacement->player_id }} (ranking position {{ $replacement->ranking_position }})</p>
  <p>Replacement status: {{ $replacement->status }}</p>
@endif
