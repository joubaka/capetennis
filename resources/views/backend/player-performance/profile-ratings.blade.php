@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Cape Tennis all-age ratings')
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="card"><div class="card-body">
    <h1 class="h3">Cape Tennis all-age ratings</h1>
    @if($refreshStatus['pending'] || $refreshStatus['failed'])
        <div class="alert alert-info py-2 mb-3" role="status" data-ratings-refresh-message>Updating ratings. This page will refresh automatically when the calculation is ready.</div>
        <button type="button" class="btn btn-outline-primary mb-3" data-ratings-refresh-retry hidden>Retry ratings update</button>
    @endif
    <p>Choose an age group and gender to find players with a calculated rating group.</p>
    @if($diagnosticOrder && !$snapshot['reason'])<div class="alert alert-warning" role="status"><strong>Diagnostic score order — not a calibrated overall ability ranking.</strong> Local scores are not calibrated between comparison groups. These cross-group scores are uncalibrated. List numbers show numeric score order only; they do not establish who is stronger across groups. @if($ageOrder)Numbers restart within each current age group; age does not change rating scores.@endif</div>@endif
    @if($comparisonGroup)<p>Showing comparison group {{ $comparisonGroup }}. <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.ratings') }}">Show all groups</a></p>@endif
    @if($withinGroup)<p class="alert alert-info" role="status"><strong>Within-group ratings only.</strong> These players have recorded connections to one another, but no established connection to the shared reference. Compare these estimates only within group {{ $comparisonGroup }}; a matching number in another group does not mean equal ability. Provisional badges and profile ratings carry the same low-confidence, within-group restriction.</p>@endif
    <p class="small text-muted">{{ $year }} season: groups use the year players were born, from 1 January to 31 December, regardless of whether their birthday has passed. U10 includes younger players; U18 includes ages 14–18. These profile groups do not establish event eligibility.</p>
    <form method="get" class="row g-2 mb-3">
        @if($comparisonGroup)<input type="hidden" name="comparison_group" value="{{ $comparisonGroup }}">@endif
        <div class="col-sm-6 col-lg-3"><label for="age-group" class="form-label">Age group / birth year</label><select name="age_group" id="age-group" class="form-select" style="min-height:44px"><option value="">All ages</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::birthYearLabels($year) as $key => $label)<option value="{{ $key }}" @selected($ageGroup === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-3"><label for="gender" class="form-label">Gender</label><select name="gender" id="gender" class="form-select" style="min-height:44px"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $key => $label)<option value="{{ $key }}" @selected($gender === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-sm-8 col-lg-4"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="col-sm-4 col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100" style="min-height:44px">Show players</button></div>
        @unless($comparisonGroup)<div class="col-12"><details><summary class="py-2">More options</summary><label for="rating-sort" class="form-label">List order</label><select name="sort" id="rating-sort" class="form-select" style="min-height:44px"><option value="shared" @selected($sort === 'shared')>Shared-scale order</option><option value="score" @selected($sort === 'score')>Ranked list — all scores</option><option value="age" @selected($sort === 'age')>Rank by age group</option></select></details></div>@endunless
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    <p>{{ $players->total() }} players · @if($snapshot['reason'])players appear after a valid rating calculation.@elseif($diagnosticOrder)diagnostic score order, highest to lowest {{ $ageOrder ? 'within each current age group' : 'across all saved scores' }}. List numbers are not overall ability positions.@else{{ $withinGroup ? 'highest to lowest within-group singles rating' : ($combined ? 'highest to lowest shared-scale singles rating' : ($unified ? 'highest to lowest singles rating within the selected comparison group' : 'highest to lowest rating within each current age group and gender')) }}. @if($combined)Players awaiting calibration follow without an overall position.@endif @endif</p>
    <details class="small text-muted mb-3"><summary class="py-2">Rating details</summary>
    <x-rating-explanation />
    @if($sharedCoverage && !$snapshot['reason'])<p>Shared-scale coverage: {{ $sharedCoverage['connected'] }} of {{ $sharedCoverage['eligible'] }} players with eligible singles results within two years are connected to the reference group.</p>@endif
    @if($connectionPlan && !$snapshot['reason'] && $connectionPlan['component_count'] > 1)
        @include('backend.player-performance.connection-plan', ['plan' => $connectionPlan])
    @endif
    @if($snapshot['reason'])
    <p>Players appear after a valid rating calculation confirms their comparison group.</p>
    @elseif($diagnosticOrder)
    <p>All numeric headline scores appear highest to lowest across comparison groups{{ $ageOrder ? ', separately within each current age group' : '' }}. Equal scores use surname, first name, then player ID. List numbers stay the same when searching or paging within the selected age and gender filters. This diagnostic order does not make local estimates comparable.</p>
    @elseif($withinGroup)
    <p>Ordering uses estimates fitted from played singles within the explicitly selected comparison group. Age and gender filters change membership, not the estimated strength. Positions apply only to this group and stay the same when searching or paging. These estimates do not establish a shared scale with other groups.</p>
    @elseif($combined)
    <p>Players connected to the shared reference appear first, highest rating to lowest. Players awaiting calibration follow alphabetically; their local estimates do not determine an overall position. Age and gender filters change the player list, not the rating. Equal scores use a stable name order. Positions stay the same when searching or paging.</p>
    <p>Scores are out of 100 and provisional. Compare players connected to the same singles reference group. Disconnected players receive a provisional within-group estimate with low confidence until suitable match evidence calibrates them against the reference. Verified completed singles count across all ball colours and court formats, without category-based rating offsets. This is not an official selection ranking.</p>
    <p>Recent results carry more weight. Matches up to two years old can connect groups; comparisons relying on a link older than one year are marked Low confidence — historical connection.</p>
    @elseif($unified)
    <p>Players in the selected shared reference group appear highest rating to lowest. Positions stay the same when searching or paging. Scores are provisional ability estimates, not official selection positions.</p>
    @else
    <p>A shared calculation is pending. Existing separate context scores are not comparable across ages or disconnected groups.</p>
    @endif
    </details>
    @if($snapshot['reason'])<p class="alert alert-warning">Ratings are currently unavailable. Players will appear when a valid calculation is available.</p>@else<p class="small text-muted">Last updated: {{ $snapshot['built_at'] }}.</p>@endif
    @forelse($players->getCollection()->groupBy(fn ($player) => $ageOrder ? $player['age_key'] : ($combined ? 'all' : ($unified ? ($player['rating']['component'] ?? 'Unrated') : $player['age_key'].'|'.$player['gender_key']))) as $members)
    <section class="mb-4" aria-label="{{ $ageOrder ? $members->first()['age_group'] : ($combined || $unified ? 'All-age singles ratings' : $members->first()['age_group'].' '.$members->first()['gender']) }}">
    <h2 class="h5">{{ $ageOrder ? $members->first()['age_group'] : ($combined ? 'All players' : ($withinGroup ? 'Within-group singles ratings' : ($unified ? 'Comparison group '.($members->first()['rating']['component'] ?? 'Unrated') : $members->first()['age_group'].' · '.$members->first()['gender']))) }}</h2>
    <ul class="list-group mb-3">
    @foreach($members as $player)
        <li class="list-group-item d-flex gap-2" style="min-width:0">
        @if(!$snapshot['reason'] && $player['position'] !== null && (!$unified || $player['rating']))<strong class="text-muted" {{ $diagnosticOrder ? 'data-score-list-position' : 'data-rating-position' }}="{{ $player['position'] }}" @if($diagnosticOrder)aria-label="List number {{ $player['position'] }} in diagnostic score order{{ $ageOrder ? ' for '.$player['age_group'] : '' }}"@endif>{{ $player['position'] }}.</strong>@endif
        <div style="min-width:0; overflow-wrap:anywhere">
            <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.show', $player['id']) }}"><strong>{{ $player['name'] }}</strong></a>
            <p class="small text-muted mb-2">{{ $player['age_group'] }} · {{ $player['gender'] }}</p>
            @if($player['rating'])
                @php($rating = $player['rating'])
                @if($player['awaiting_calibration'])<p class="mb-1 text-warning-emphasis"><strong>Awaiting calibration · {{ $diagnosticOrder ? 'no overall ability rank' : 'no overall position' }}</strong></p>@endif
                <p class="mb-1"><strong>{{ number_format($rating['within_group_score'] ?? $rating['score'], 1) }}/100</strong> @if(isset($rating['within_group_score']) || $player['awaiting_calibration'])· within-group only @endif· {{ $rating['cohort'] }} @unless($combined || $diagnosticOrder)· comparison group {{ $rating['component'] }} @endunless· {{ $rating['confidence_label'] }} confidence</p>
                @if(!empty($rating['historical_connection']))<p class="small text-warning-emphasis mb-1"><strong>Low confidence — historical connection</strong> · link dated {{ $rating['connection_reference_date'] ?? 'unknown' }}.</p>@endif
                @if(($rating['comparable'] ?? true) === false)<p class="small text-warning-emphasis mb-1"><strong>Provisional within-group rating · low confidence.</strong> Not calibrated against other groups.</p>@endif
                @if(count($player['ratings']) > 1)<details><summary class="py-2">All saved ratings</summary>@foreach($player['ratings'] as $other)<p class="small mb-1">{{ number_format($other['score'], 1) }}/100 · {{ $other['cohort'] }} · comparison group {{ $other['component'] }} · last played {{ $other['last_played'] }}</p>@endforeach</details>@endif
            @else<p class="mb-1">{{ $snapshot['reason'] ? 'Rating unavailable' : 'Unrated — no eligible saved estimate' }}</p>@if($player['rating_reason'] ?? null)<p class="small text-muted">{{ $player['rating_reason'] }}</p>@endif @endif
        </div></li>
    @endforeach
    </ul></section>
    @empty<p>No matching player profiles.</p>@endforelse
    {{ $players->links('pagination::bootstrap-5') }}
    <details><summary class="py-2">Other rating views</summary><a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.ratings', ['view' => 'recorded']) }}">Ratings within recorded comparison groups</a></details>
</div></div></div>
@if($refreshStatus['pending'] || $refreshStatus['failed'])
<script>
window.CTLeaderboardRefresh = {
    requestUrl: @json(route('backend.player-performance.ratings.refresh')),
    statusUrl: @json(route('backend.player-performance.ratings.status')),
    csrf: @json(csrf_token()),
    version: @json($refreshStatus['version']),
    snapshotVersion: @json($refreshStatus['snapshot_version'] ?? null)
};
</script>
<script src="{{ asset('assets/js/player-ratings-leaderboard-refresh.js') }}?v={{ filemtime(public_path('assets/js/player-ratings-leaderboard-refresh.js')) }}"></script>
@endif
@endsection
