<div class="card mb-4"><div class="card-body">
    @if(\App\Services\Performance\PlayerRatingBadgeService::visible())
        @php($ratingStatus = app(\App\Services\Performance\PlayerAbilityRefreshState::class)->status())
        <p class="small" data-rating-status>Saved ratings last updated: {{ $ratingStatus['last_updated'] ?? 'Not yet updated' }} · {{ $ratingStatus['failed'] ? 'Update failed; retry pending.' : ($ratingStatus['pending'] ? 'Update pending.' : 'Up to date.') }}</p>
        <p class="small"><a href="{{ url()->current() }}" data-rating-detail-version="{{ $ratingStatus['version'] }}" hidden>Reload this page for the latest detailed rating and evidence.</a></p>
    @endif
    @if($ability['snapshot_stale'] ?? false)<p class="alert alert-warning text-dark">Last successful update: {{ $ability['snapshot_as_of'] ?? 'Pending' }}. Awaiting the background refresh.</p>@endif
    <h2 class="h4">Cape Tennis Shared Ability · provisional index</h2>
    @if($ability['reason'])
        <p class="alert alert-warning text-dark">{{ $ability['reason'] }}</p>
    @elseif($ability['headline'])
        @php($estimate = $ability['headline'])
        <p class="h2">{{ $estimate['score'] === null ? 'Estimate withheld' : number_format($estimate['score'], 1).'/100' }}</p>
        <p>{{ $estimate['cohort'] }} · singles · comparison group {{ $estimate['component'] }} · {{ $estimate['component_players'] }} connected players</p>
        <p>Evidence confidence: {{ \App\Services\Performance\AbilityConfidenceDisplay::label((int) $estimate['confidence_index'], $estimate) }}. {{ $estimate['played'] }} played matches and {{ $estimate['inferred'] }} weaker inferred finish comparisons for this player.</p>
        <p class="small">{{ $estimate['confidence_explanation'] ?? '' }}</p>
        <p>Own same-cohort evidence: {{ $estimate['recent_played'] }} matches in the last 90 days; {{ number_format($estimate['effective_played'], 2) }} recency-weighted matches; {{ $estimate['direct_opponents'] }} distinct opponents across {{ $estimate['played_events'] }} played events.</p>
        <p>Last direct-match date (schedule/event proxy): {{ $estimate['last_direct_match'] ?? 'None' }}. Last eligible evidence: {{ $estimate['last_eligible_activity'] ?? 'None' }}. Confidence as of {{ $estimate['confidence_as_of'] }}.</p>
        <p>{{ $estimate['baseline_status'] ?? 'No connected main-trial baseline' }}.
            @if($estimate['baseline_source'] ?? null)
                {{ $estimate['baseline_source']['event_name'] }} · {{ $estimate['baseline_source']['date'] }} · {{ $estimate['baseline_source']['covered_players'] }}/{{ $estimate['baseline_source']['trial_players'] }} trial players in the largest connected benchmark group. This is a model-derived trial-match reference, not a published finishing rank.
            @else
                This local estimate has no connected main-trial reference. Compare only within this cohort and comparison group.
            @endif
        </p>
        <p class="small">Low, Medium and High describe the strength and freshness of recorded evidence, not rating accuracy or win probability. The nightly update reduces it as evidence ages. Dates use the match schedule where credible, otherwise conservative event start dates ({{ $estimate['proxy_dated_matches'] }} matches). Play outside recorded eligible Cape Tennis results is unknown.</p>
        @if($estimate['played'] === 0)<p>With little direct evidence, the model pulls this estimate toward 50. That does not establish average playing strength.</p>@endif
        @if($estimate['reason'])<p>{{ $estimate['reason'] }}</p>@endif
    @else
        <p class="h3">Unrated</p><p>No eligible connected individual singles evidence.</p>
    @endif
    <p class="alert alert-info text-dark">Compare only players in the same cohort and comparison group. This uncalibrated 0–100 index is not a win probability. Disconnected groups and separate performance scores cannot be compared.</p>
    <p>Calculated {{ $ability['built_at'] }} · <a href="{{ route('backend.player-performance.show', $player->id) }}">View shared comparison evidence</a></p>
</div></div>
