        <li class="list-group-item ctr-ranking-row" data-ctr-ranking-row>
        <div class="ctr-place">@if(!$snapshot['reason'] && $player['position'] !== null && (!$unified || $player['rating']))<strong {{ $diagnosticOrder ? 'data-score-list-position' : 'data-rating-position' }}="{{ $player['position'] }}" @if($diagnosticOrder)aria-label="List number {{ $player['position'] }} in diagnostic score order{{ $ageOrder ? ' for '.$player['age_group'] : '' }}"@endif>{{ $player['position'] }}.</strong>@else<span aria-label="No overall position">—</span>@endif</div>
        <div class="ctr-player">
            <a href="{{ route('backend.player-performance.show', $player['id']) }}">{{ $player['name'] }}@if($player['birth_year'] ?? null) ({{ $player['birth_year'] }})@endif</a>
            <p>{{ $player['age_group'] }} · {{ $player['gender'] }}</p>
        </div>
            @if($player['rating'])
                @php($rating = $player['rating'])
                <div class="ctr-score"><x-ctr-badge :compact="true" :score="$rating['within_group_score'] ?? $rating['score']" :local="isset($rating['within_group_score']) || $player['awaiting_calibration'] || ($rating['comparable'] ?? true) === false" /></div>
                <div class="ctr-evidence"><p><x-ctr-confidence-badge :rating="$rating" /></p>
                @if(!empty($rating['historical_connection']))<p class="text-warning-emphasis">Historical connection</p>@endif
                @if($player['awaiting_calibration'])<p>Awaiting calibration</p>@else<p>{{ $rating['cohort'] }}</p>@endif
                </div>
                <div class="ctr-row-detail">
                @if($player['awaiting_calibration'])<p class="mb-1 text-warning-emphasis"><strong>Awaiting calibration · {{ $diagnosticOrder ? 'no overall ability rank' : 'no overall position' }}</strong></p>@endif
                @if(!empty($rating['historical_connection']))<p class="small text-warning-emphasis mb-1"><strong>Low confidence — historical connection</strong> · link dated {{ $rating['connection_reference_date'] ?? 'unknown' }}.</p>@endif
                @if(($rating['comparable'] ?? true) === false)<p class="small text-warning-emphasis mb-1"><strong>Provisional within-group rating · low confidence.</strong> Not calibrated against other groups.</p>@endif
                @if(count($player['ratings']) > 1)<details><summary class="py-2">All saved ratings</summary>@foreach($player['ratings'] as $other)<p class="small mb-1">{{ number_format($other['score'], 1) }}/100 · {{ $other['cohort'] }} · comparison group {{ $other['component'] }} · last played {{ $other['last_played'] }}</p>@endforeach</details>@endif
                </div>
            @else<div class="ctr-score">{{ $snapshot['reason'] ? 'Rating unavailable' : 'Unrated' }}</div><div class="ctr-evidence">@if($player['rating_reason'] ?? null){{ $player['rating_reason'] }}@endif</div>@endif
        </li>
