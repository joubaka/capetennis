<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Cape Tennis Shared Ability · provisional index</h2>
    @if($ability['reason'])
        <p class="alert alert-warning text-dark">{{ $ability['reason'] }}</p>
    @elseif($ability['headline'])
        @php($estimate = $ability['headline'])
        <p class="h2">{{ $estimate['score'] === null ? 'Estimate withheld' : number_format($estimate['score'], 1).'/100' }}</p>
        <p>{{ $estimate['cohort'] }} · singles · comparison group {{ $estimate['component'] }} · {{ $estimate['component_players'] }} connected players</p>
        <p>Evidence confidence C{{ $estimate['confidence_index'] }} · {{ $estimate['confidence_band'] }}. {{ $estimate['played'] }} played matches and {{ $estimate['inferred'] }} weaker inferred finish comparisons for this player.</p>
        <p>Own same-cohort evidence: {{ $estimate['recent_played'] }} matches in the last 90 days; {{ number_format($estimate['effective_played'], 2) }} recency-weighted matches; {{ $estimate['direct_opponents'] }} distinct opponents across {{ $estimate['played_events'] }} played events.</p>
        <p>Last direct-match date (schedule/event proxy): {{ $estimate['last_direct_match'] ?? 'None' }}. Last eligible evidence: {{ $estimate['last_eligible_activity'] ?? 'None' }}. Confidence as of {{ $estimate['confidence_as_of'] }}.</p>
        <p class="small">C0–100 is an evidence confidence heuristic, not an accuracy percentage or win probability. Inactivity reduces it daily. Dates use the match schedule where credible, otherwise conservative event start dates ({{ $estimate['proxy_dated_matches'] }} matches). Play outside recorded eligible Cape Tennis results is unknown.</p>
        @if($estimate['played'] === 0)<p>With little direct evidence, the model pulls this estimate toward 50. That does not establish average playing strength.</p>@endif
        @if($estimate['reason'])<p>{{ $estimate['reason'] }}</p>@endif
    @else
        <p class="h3">Unrated</p><p>No eligible connected individual singles evidence.</p>
    @endif
    <p class="alert alert-info text-dark">Compare only players in the same cohort and comparison group. This uncalibrated 0–100 index is not a win probability. Disconnected groups and separate performance scores cannot be compared.</p>
    <p>Calculated {{ $ability['built_at'] }} · <a href="{{ route('backend.player-performance.show', $player->id) }}">View shared comparison evidence</a></p>
</div></div>
