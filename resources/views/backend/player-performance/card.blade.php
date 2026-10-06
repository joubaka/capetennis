<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Cape Tennis Performance Score</h2>
    <p class="h3">{{ $performance['headline'] ? number_format($performance['headline']['score'], 1).'/100' : 'Unrated' }}</p>
    @if($performance['headline'])
        <p>Provisional singles · {{ $performance['headline']['cohort'] }} ({{ $performance['headline']['band'] }}) · {{ $performance['headline']['finish_count'] }} finishes / {{ $performance['headline']['match_count'] }} matches across {{ $performance['headline']['count'] }} events · Latest: {{ $performance['headline']['last_played'] }}</p>
    @else
        <p>No eligible published singles results. Doubles, where available, are shown separately.</p>
    @endif
    <p>Private, uncalibrated pilot v2 · all published history, weighted toward recent results. Tournament finishes and completed matches contribute. Qualification is not assessed.</p>
    <a href="{{ route('backend.player-performance.show', $player->id) }}">View rating evidence and other cohorts</a>
</div></div>
