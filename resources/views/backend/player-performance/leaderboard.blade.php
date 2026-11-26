@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', $event ? 'Event player ratings' : 'Site-wide player ratings')
@section('content')
@include('backend.player-performance.partials.ctr-directory-style')
<div class="operational-page">
<style>
    .ratings-page .ratings-explanation summary, .ratings-page .rating-evidence summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; gap: .5rem; }
    .ratings-page summary::before { content: '+'; font-weight: 600; }
    .ratings-page details[open] > summary::before { content: '−'; }
    .ratings-page summary:focus-visible, .ratings-page a:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
    .ratings-page h2, .ratings-page h3 { overflow-wrap: anywhere; }
    .ratings-page .ctr-hero h2 { color: #fff !important; margin: 0 0 .35rem; }
    .ratings-page .ctr-hero summary { color: #fff; }
    .ratings-page .rating-evidence { display: inline-block; max-width: 100%; }
    .ratings-page .rating-evidence summary { display: inline-flex; width: fit-content; padding: .15rem .45rem; }
    .ratings-page .rating-evidence[open] { display: block; }
    .ratings-page .rating-evidence p { margin: .35rem 0; }
    .ratings-page .ctr-score { display: flex; flex-wrap: wrap; align-items: center; gap: .15rem; }
    .ratings-page .ratings-status { font-size: .875rem; }
    .ratings-page .ratings-filters button { min-height: 44px; width: 100%; }
    .ratings-page .ratings-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: start; gap: 1rem; }
    .ratings-page .ratings-tools { display: flex; flex-wrap: wrap; gap: .5rem; }
    .ratings-page .ratings-tools a { min-height: 44px; display: inline-flex; align-items: center; }
    .ratings-page .ratings-filters { margin: 0 0 1rem; }
    .ratings-page .ratings-filters-event { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .ratings-page .ratings-filters > div { width: auto; padding: 0; }
    @media (min-width: 576px) and (max-width: 991.98px) {
        .ratings-page .ratings-filters-event { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ratings-page .ratings-filters-event .ratings-submit { grid-column: 1/-1; }
    }
    @media (max-width: 575.98px) {
        .ratings-page .ratings-filters { grid-template-columns: minmax(0, 1fr); }
        .ratings-page .ctr-ranking-row { grid-template-columns: 1.5rem minmax(0, 1fr) 4.5rem; }
        .ratings-page .ctr-player { grid-column: 2; grid-row: 1; }
        .ratings-page .ctr-score { grid-column: 3; grid-row: 1; }
        .ratings-page .ctr-evidence { grid-column: 2/-1; grid-row: 2; }
    }
</style>
@include('backend.partials.operational-controls')
@if($event)
    @include('backend.event.partials.header', ['event' => $event, 'eventWorkspaceActive' => 'ratings', 'eventWorkspaceShowHome' => false])
@endif
<div class="ctr-directory ratings-page">
    <div class="ctr-hero ratings-heading">
        <div>
            @if($event)<h2 class="h3">Player ratings</h2>@else<h1 class="h3">Site-wide player ratings</h1>@endif
            <p>Private provisional singles ratings.</p>
        </div>
        <details class="ratings-explanation small">
            <summary>Review tools</summary>
            <div class="ratings-tools">
                @if($event)<a class="btn btn-outline-primary" href="{{ route('backend.player-performance.identity-review', $event) }}">Review historical identity evidence</a>@endif
                <a class="btn btn-outline-primary" href="{{ route('backend.player-performance.masters-review') }}">Review Masters qualification</a>
            </div>
        </details>
    </div>
    <div class="ctr-content">
    <p class="ctr-intro mb-2">Red CTR, Orange CTR and CTR (Green/Yellow) are separate rating pools. Compare players only within the same ball pool and comparison group. @if($event)The all-pools view shows separate lists, never a combined score.@endif</p>
    <details class="ratings-explanation small text-muted mb-2">
        <summary>How to read these ratings</summary>
        <p>Cohorts follow age order. Players are grouped by comparison group within each exact cohort, highest rating to lowest within each group, with unrated players last. Positions refer to each comparison group. Separate groups cannot be compared directly. Ratings are calculated from published evidence; they cannot be edited here.</p>
        <p>The score out of 100 represents relative ability within a connected comparison group, not a win percentage. Confidence describes the strength and freshness of the evidence, not rating accuracy. @if($unified ?? false)Age categories filter membership; they do not recalculate the shared rating. Disconnected players receive a provisional within-group score with low confidence until suitable played evidence calibrates them against other groups.@endif</p>
        <p class="mb-2">Includes recorded individual entries (including unpaid and withdrawn entries) and saved team rosters. Unrated members remain visible. A player entered in multiple cohorts appears once in each.</p>
    </details>
    @if($snapshot['reason'])
        <p class="text-muted ratings-status mb-2">Ratings are currently unavailable. Recorded players remain visible below.</p>
    @else
        <p class="text-muted ratings-status mb-2">Last successful calculation: {{ $snapshot['built_at'] }} · evidence as of {{ $snapshot['snapshot_as_of'] ?? 'unknown' }}.</p>
    @endif
    @if($refreshStatus['pending'] || $refreshStatus['failed'])
        <div class="alert alert-info py-2 mb-3" role="status">A rating update is pending. This page shows the latest available saved ratings.</div>
    @endif
    @if(!$refreshStatus['pending'] && !$refreshStatus['failed'] && $snapshot['reason'])<div class="alert alert-warning" role="status">{{ $snapshot['reason'] }}</div>@endif
    @if($limitReason)<div class="alert alert-warning" role="status">{{ $limitReason }}</div>@endif
    <form method="get" class="ctr-filters ratings-filters {{ $event ? 'ratings-filters-event' : '' }}">
        <div class="{{ $event ? 'col-md-4' : 'col-md-6' }}"><label for="cohort" class="form-label">{{ $event ? 'Division' : 'Exact age / gender / playing context' }}</label>
            <select name="cohort" id="cohort" class="form-select" style="min-height:44px">
                <option value="">{{ $event ? 'All event divisions' : 'Choose a cohort' }}</option>
                @foreach($cohorts as $cohort)<option value="{{ $cohort }}" @selected($selected === $cohort)>{{ $cohort }}</option>@endforeach
            </select>
        </div>
        @if($event)
        <div><label for="group_by" class="form-label">Group by</label>
            <select name="group_by" id="group_by" class="form-select" style="min-height:44px">
                <option value="division" @selected($groupBy === 'division')>Division</option>
                <option value="age" @selected($groupBy === 'age')>Player age</option>
            </select>
        </div>
        <div class="col-md-3"><label for="ball_type" class="form-label">Rating pool</label>
            <select name="ball_type" id="ball_type" class="form-select" style="min-height:44px">
                <option value="">All event pools (separate lists)</option>
                @foreach(['red' => 'Red CTR', 'orange' => 'Orange CTR', 'standard' => 'CTR (Green/Yellow)'] as $stage => $label)
                    <option value="{{ $stage }}" @selected($pool === $stage)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-12 {{ $event ? 'col-md-3' : 'col-md-4' }}"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="col-12 col-md-2 d-flex align-items-end ratings-submit"><button class="btn btn-primary" type="submit">Show ratings</button></div>
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    @if(!$event && !$selected)
        <p>Choose a cohort to see players across the site. Cohorts follow recorded event categories, rather than a player's current age.</p>
    @else
        <p class="small text-muted mb-3">{{ $players->total() }} player / cohort entries · ordered within separate comparison groups.</p>
        @if(!$event)<p class="small text-muted">Membership follows recorded categories and saved rating evidence. Legacy teams without a category are available in their event view as unresolved; they are not assigned an age group by guesswork.</p>@endif
        @if($groupBy === 'age')<p class="small text-muted">Age on {{ $ageReference }}{{ $ageReferenceFallback ? ' (today; event start date unavailable)' : ' (event start)' }}. Positions remain those of the original division and comparison group.</p>@endif
        @forelse($players->getCollection()->groupBy(fn ($row) => $groupBy === 'age' ? ($row['player_age'] ?? 'unknown') : 'division') as $age => $ageMembers)
            @if($groupBy === 'age')<h2 class="h4 mt-4" data-player-age-group="{{ $age }}">{{ $age === 'unknown' ? 'Unknown age' : 'Age '.$age }}</h2>@endif
            @foreach($ageMembers->groupBy(fn ($row) => $row['cohort'].'|'.($row['ball_stage'] ?? 'unknown')) as $group => $members)
            <section class="ctr-results-section mb-4" aria-label="{{ $members->first()['cohort'] }} · {{ $members->first()['ball_label'] }}"><h2 class="h5">{{ $members->first()['cohort'] }} · {{ $members->first()['ball_label'] }}</h2>
                @foreach($members->groupBy('component') as $component => $comparisonMembers)
                <h3 class="h6 mt-3">{{ $snapshot['reason'] ? 'Ratings unavailable' : ($comparisonMembers->first()['rating'] ? 'Comparison group: '.$component : 'Unrated players') }}</h3>
                <div class="ctr-column-head" aria-hidden="true"><span>Position</span><span>Player</span><span>CTR</span><span>Evidence</span></div>
                <ul class="list-group mb-3">
                @foreach($comparisonMembers as $member)
                    @php($displayRating = $snapshot['reason'] ? null : $member['rating'])
                    <li class="list-group-item ctr-ranking-row" data-event-rating-row>
                            <div class="ctr-place">@if($displayRating && $member['position'])<strong>{{ $member['position'] }}.</strong>@else<span aria-label="No position">—</span>@endif</div>
                            <div class="ctr-player">
                                @if($member['player_id'])<a href="{{ route('backend.player-performance.show', [$member['player_id'], 'ball_type' => $member['ball_stage'] ?? 'standard']) }}">{{ $member['name'] }}</a>@else<span>{{ $member['name'] }}</span>@endif
                                @if($event && $member['regions'])<p>({{ implode(', ', $member['regions']) }})</p>@endif
                                @if($groupBy === 'age')<p class="small text-muted">{{ $member['player_age'] === null ? 'Unknown age' : 'Age '.$member['player_age'] }}</p>@endif
                            </div>
                            <div class="ctr-score">@if($displayRating)<x-ctr-badge :stage="$member['ball_stage']" size="inline" :score="$displayRating['score']" :local="($displayRating['comparable'] ?? true) === false" />@elseif($snapshot['reason'] && $member['player_id'])<span class="small text-muted">Rating unavailable</span>@else<span class="small text-muted">Unrated</span>@endif</div>
                        <div class="ctr-evidence">
                        @if($displayRating)
                            <p><x-ctr-confidence-badge :rating="$displayRating" /></p>
                            <p>{{ $displayRating['played'] ?? 'Unknown' }} matches · {{ $displayRating['direct_opponents'] ?? 'Unknown' }} opponents</p>
                            @if(!empty($displayRating['historical_connection']))<p><strong>Low confidence — historical connection</strong> · link dated {{ $displayRating['connection_reference_date'] ?? 'unknown' }}.</p>@endif
                            <details class="rating-evidence text-muted"><summary>Rating evidence</summary>
                            <p>Last match {{ $displayRating['last_direct_match'] ?? 'unknown' }}</p>
                            <p class="small text-muted mb-1">Last eligible activity: {{ $displayRating['last_eligible_activity'] ?? $displayRating['last_played'] ?? 'unknown' }} · {{ $displayRating['baseline_status'] ?? 'No connected main-trial baseline' }}</p>
                            <p class="small text-muted mb-0">{{ $displayRating['confidence_explanation'] }}</p>
                            </details>
                        @else<p class="small text-muted mb-0">{{ !$member['player_id'] ? 'Imported roster member has no linked player profile.' : ($snapshot['reason'] ? 'A safe current estimate is unavailable; this does not mean the player has no rating evidence.' : ($member['rating_reason'] ?? 'No eligible saved estimate; check the player evidence or pending update.')) }}</p>@endif
                        </div>
                    </li>
                @endforeach
                </ul>
                @endforeach
            </section>
            @endforeach
        @empty<p>No matching players in this selection.</p>@endforelse
        {{ $players->links('pagination::bootstrap-5') }}
    @endif
    <div class="d-flex flex-wrap gap-3 mt-3">
        <a style="min-height:44px" href="{{ route('backend.player-performance.ratings') }}">Browse current age groups and gender</a>
        @if($event)<a style="min-height:44px" href="{{ route('backend.player-performance.ratings') }}">Site-wide ratings</a>@endif
        <a style="min-height:44px" href="{{ route('backend.player-performance.directory') }}">Search all player profiles</a>
        <a style="min-height:44px" href="{{ route('backend.player-performance.index') }}">A/B performance comparison</a>
    </div>
</div></div>
</div>
@endsection
