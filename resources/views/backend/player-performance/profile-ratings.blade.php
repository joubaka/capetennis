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
    <p>One provisional singles rating across ages. Filter current player profiles by age group and gender, or search a player by name.</p>
    <x-rating-explanation />
    @if($sharedCoverage && !$snapshot['reason'])<p class="small text-muted" role="status">Shared-scale coverage: {{ $sharedCoverage['connected'] }} of {{ $sharedCoverage['eligible'] }} players with eligible standard-singles results within two years are connected to the reference group. Players awaiting connecting match evidence: {{ $sharedCoverage['eligible'] - $sharedCoverage['connected'] }}.</p>@endif
    @if($connectionPlan && !$snapshot['reason'] && $connectionPlan['component_count'] > 1)
        @include('backend.player-performance.connection-plan', ['plan' => $connectionPlan])
    @endif
    @if($comparisonGroup)<p>Showing comparison group {{ $comparisonGroup }}. <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.ratings') }}">Show all groups</a></p>@endif
    @if($withinGroup)<p class="alert alert-info" role="status"><strong>Within-group ratings only.</strong> These players have recorded connections to one another, but no established connection to the shared reference. Compare these estimates only within group {{ $comparisonGroup }}; a matching number in another group does not mean equal ability. Shared badges and profile ratings remain withheld.</p>@endif
    <p class="small text-muted">{{ $year }} season: age is the age reached by 31 December. U10 includes younger players; U18 includes ages 14–18. These profile groups do not establish event eligibility.</p>
    <form method="get" class="row g-2 mb-3">
        @if($comparisonGroup)<input type="hidden" name="comparison_group" value="{{ $comparisonGroup }}">@endif
        <div class="col-sm-6 col-lg-3"><label for="age-group" class="form-label">Age group</label><select name="age_group" id="age-group" class="form-select" style="min-height:44px"><option value="">All ages</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::AGE_GROUPS as $key => $label)<option value="{{ $key }}" @selected($ageGroup === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-3"><label for="gender" class="form-label">Gender</label><select name="gender" id="gender" class="form-select" style="min-height:44px"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $key => $label)<option value="{{ $key }}" @selected($gender === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-sm-8 col-lg-4"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="col-sm-4 col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100" style="min-height:44px">Show ratings</button></div>
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    <p>{{ $players->total() }} player profiles · {{ $withinGroup ? 'highest to lowest within-group singles rating' : ($unified ? 'highest to lowest shared singles rating across the selected ages' : 'highest to lowest rating within each current age group and gender') }}. Unrated players appear last.</p>
    <details class="small text-muted mb-3"><summary class="py-2">How ratings are ordered</summary>
    @if($withinGroup)
    <p>Ordering uses estimates fitted from played singles within the explicitly selected comparison group. Age and gender filters change membership, not the estimated strength. Positions apply only to this group and stay the same when searching or paging. These estimates do not establish a shared scale with other groups.</p>
    @elseif($unified)
    <p>Ordering uses each player's shared rating from eligible played singles results across age groups. Age and gender filters change the player list, not the rating. Equal scores use a stable name order. Positions stay the same when searching or paging.</p>
    <p>Scores are out of 100 and provisional. Compare players connected to the same standard-singles reference group. Disconnected players remain unrated on this shared scale until suitable match evidence connects them. Modified-ball results are excluded; older records with an assumed standard format are identified in the evidence. This is not an official selection ranking.</p>
    <p>Recent results carry more weight. Matches up to two years old can connect groups; comparisons relying on a link older than one year are marked Low confidence — historical connection.</p>
    @else
    <p>A shared calculation is pending. Existing separate context scores are not comparable across ages or disconnected groups.</p>
    @endif
    </details>
    @if($snapshot['reason'])<p class="alert alert-warning">Ratings are currently unavailable. Profile search remains available.</p>@else<p class="small text-muted">Last successful calculation: {{ $snapshot['built_at'] }} · evidence as of {{ $snapshot['snapshot_as_of'] ?? 'unknown' }}.</p>@endif
    @forelse($players->getCollection()->groupBy(fn ($player) => $unified ? 'shared' : $player['age_key'].'|'.$player['gender_key']) as $members)
    <section class="mb-4" aria-label="{{ $unified ? 'All-age singles ratings' : $members->first()['age_group'].' '.$members->first()['gender'] }}">
    <h2 class="h5">{{ $withinGroup ? 'Within-group singles ratings' : ($unified ? 'All-age singles ratings' : $members->first()['age_group'].' · '.$members->first()['gender']) }}</h2>
    <ul class="list-group mb-3">
    @foreach($members as $player)
        <li class="list-group-item d-flex gap-2" style="min-width:0">
        @if(!$snapshot['reason'] && (!$unified || $player['rating']))<strong class="text-muted" data-rating-position="{{ $player['position'] }}">{{ $player['position'] }}.</strong>@endif
        <div style="min-width:0; overflow-wrap:anywhere">
            <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.show', $player['id']) }}"><strong>{{ $player['name'] }}</strong></a>
            <p class="small text-muted mb-2">{{ $player['age_group'] }} · {{ $player['gender'] }}</p>
            @if($player['rating'])
                @php($rating = $player['rating'])
                <p class="mb-1"><strong>{{ number_format($rating['within_group_score'] ?? $rating['score'], 1) }}/100</strong> @if(isset($rating['within_group_score']))· within-group only @endif· {{ $rating['cohort'] }} · comparison group {{ $rating['component'] }} · {{ $rating['confidence_label'] }} confidence</p>
                @if(!empty($rating['historical_connection']))<p class="small text-warning-emphasis mb-1"><strong>Low confidence — historical connection</strong> · link dated {{ $rating['connection_reference_date'] ?? 'unknown' }}.</p>@endif
                @if(count($player['ratings']) > 1)<details><summary class="py-2">All saved ratings</summary>@foreach($player['ratings'] as $other)<p class="small mb-1">{{ number_format($other['score'], 1) }}/100 · {{ $other['cohort'] }} · comparison group {{ $other['component'] }} · last played {{ $other['last_played'] }}</p>@endforeach</details>@endif
                @if($unified && !empty($rating['standard_assumed_matches']))<p class="small text-muted">Includes {{ $rating['standard_assumed_matches'] }} legacy matches with an assumed standard playing format.</p>@endif
            @else<p class="mb-1">{{ $snapshot['reason'] ? 'Rating unavailable' : 'Unrated — no eligible saved estimate' }}</p>@if($player['rating_reason'] ?? null)<p class="small text-muted">{{ $player['rating_reason'] }}</p>@endif @endif
            <a href="{{ route('backend.player-performance.show', $player['id']) }}" class="small d-inline-flex align-items-center" style="min-height:44px">View profile ratings &amp; evidence</a>
        </div></li>
    @endforeach
    </ul></section>
    @empty<p>No matching player profiles.</p>@endforelse
    {{ $players->links('pagination::bootstrap-5') }}
    <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.ratings', ['view' => 'recorded']) }}">Advanced: ratings ordered within recorded comparison groups</a>
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
