@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', $event ? 'Event player ratings' : 'Site-wide player ratings')
@section('content')
<div class="operational-page">
<style>
    .ratings-page .ratings-explanation summary, .ratings-page .rating-evidence summary { cursor: pointer; min-height: 44px; display: flex; align-items: center; gap: .5rem; }
    .ratings-page summary::before { content: '+'; font-weight: 600; }
    .ratings-page details[open] > summary::before { content: '−'; }
    .ratings-page summary:focus-visible, .ratings-page a:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
    .ratings-page .rating-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .35rem 1rem; align-items: center; }
    .ratings-page .rating-player { min-width: 0; overflow-wrap: anywhere; }
    .ratings-page h2, .ratings-page h3 { overflow-wrap: anywhere; }
    .ratings-page .rating-score { text-align: right; white-space: nowrap; }
    .ratings-page .rating-score small { display: block; }
    .ratings-page .rating-evidence { font-size: .875rem; }
    .ratings-page .rating-evidence p { margin-bottom: .35rem; }
    .ratings-page .ratings-status { font-size: .875rem; }
    .ratings-page .ratings-filters button { min-height: 44px; width: 100%; }
    @media (max-width: 575.98px) { .ratings-page .card-body { padding: 1rem; } .ratings-page .rating-row { column-gap: .5rem; } .ratings-page .list-group-item { padding: .65rem .75rem; } }
</style>
@include('backend.partials.operational-controls')
@if($event)
    @include('backend.event.partials.header', ['event' => $event, 'eventWorkspaceActive' => 'ratings', 'eventWorkspaceShowHome' => false])
@endif
<div class="card ratings-page"><div class="card-body">
    @if($event)<h2 class="h3">Player ratings</h2>@else<h1 class="h3">Site-wide player ratings</h1>@endif
    <p class="mb-1">Private provisional singles ratings. Compare players only within the same exact cohort and comparison group.</p>
    <details class="ratings-explanation small text-muted mb-2">
        <summary>How to read these ratings</summary>
        <p>Cohorts follow age order. Players are grouped by comparison group within each exact cohort, highest rating to lowest within each group, with unrated players last. Positions refer to each comparison group. Separate groups cannot be compared directly. Ratings are calculated from published evidence; they cannot be edited here.</p>
        <p>The score out of 100 represents relative ability within a connected comparison group, not a win percentage. Confidence describes the strength and freshness of the evidence, not rating accuracy.</p>
        <p class="mb-2">Includes recorded individual entries (including unpaid and withdrawn entries) and saved team rosters. Unrated members remain visible. A player entered in multiple cohorts appears once in each.</p>
    </details>
    @if($snapshot['reason'])
        <p class="text-muted ratings-status mb-2">Ratings are currently unavailable. Recorded players remain visible below.</p>
    @else
        <p class="text-muted ratings-status mb-2">Last successful calculation: {{ $snapshot['built_at'] }} · evidence as of {{ $snapshot['snapshot_as_of'] ?? 'unknown' }}.</p>
    @endif
    @if($refreshStatus['failed'])<div class="alert alert-warning" role="status">The latest background refresh failed. {{ $snapshot['reason'] ? 'Ratings remain unavailable until a successful refresh.' : 'Showing the last successful calculation; it does not include the failed update.' }}</div>
    @elseif($refreshStatus['pending'] && !$snapshot['reason'] && !($snapshot['snapshot_stale'] ?? false))<div class="alert alert-info py-2 mb-3" role="status">A background update is pending. Showing the last saved calculation. Reload this page later to see an updated calculation.</div>@endif
    @if($snapshot['reason'])<div class="alert alert-warning" role="status">{{ $snapshot['reason'] }}</div>
    @elseif($snapshot['snapshot_stale'] ?? false)<div class="alert alert-warning" role="status">The saved calculation is stale and awaiting background refresh.</div>@endif
    @if($limitReason)<div class="alert alert-warning" role="status">{{ $limitReason }}</div>@endif
    <form method="get" class="row g-2 mb-3 ratings-filters">
        <div class="col-md-6"><label for="cohort" class="form-label">Exact age / gender / playing context</label>
            <select name="cohort" id="cohort" class="form-select" style="min-height:44px">
                <option value="">{{ $event ? 'All event cohorts (separate groups)' : 'Choose a cohort' }}</option>
                @foreach($cohorts as $cohort)<option value="{{ $cohort }}" @selected($selected === $cohort)>{{ $cohort }}</option>@endforeach
            </select>
        </div>
        <div class="col-8 col-md-4"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="col-4 col-md-2 d-flex align-items-end"><button class="btn btn-primary" type="submit">Show ratings</button></div>
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    @if(!$event && !$selected)
        <p>Choose a cohort to see players across the site. Cohorts follow recorded event categories, rather than a player's current age.</p>
    @else
        <p class="small text-muted mb-3">{{ $players->total() }} player / cohort entries · ordered within separate comparison groups.</p>
        @if(!$event)<p class="small text-muted">Membership follows recorded categories and saved rating evidence. Legacy teams without a category are available in their event view as unresolved; they are not assigned an age group by guesswork.</p>@endif
        @forelse($players->getCollection()->groupBy('cohort') as $group => $members)
            <section class="mb-4" aria-label="{{ $group }}"><h2 class="h5">{{ $group }}</h2>
                @foreach($members->groupBy('component') as $component => $comparisonMembers)
                <h3 class="h6 mt-3">{{ $snapshot['reason'] ? 'Ratings unavailable' : ($comparisonMembers->first()['rating'] ? 'Comparison group: '.$component : 'Unrated players') }}</h3>
                <ul class="list-group mb-3">
                @foreach($comparisonMembers as $member)
                    @php($displayRating = $snapshot['reason'] ? null : $member['rating'])
                    <li class="list-group-item">
                        <div class="rating-row">
                            <div class="rating-player">@if($displayRating && $member['position'])<span class="text-muted me-2">{{ $member['position'] }}.</span>@endif
                                @if($member['player_id'])<a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.show', $member['player_id']) }}">{{ $member['name'] }}</a>@else<span>{{ $member['name'] }}</span>@endif
                                @if($event && $member['regions'])<span class="text-muted">({{ implode(', ', $member['regions']) }})</span>@endif
                            </div>
                            <div class="rating-score">@if($displayRating)<strong>{{ number_format($displayRating['score'], 1) }}/100</strong><small class="text-muted">{{ $displayRating['confidence_label'] }} confidence</small>@elseif($snapshot['reason'] && $member['player_id'])<strong>Rating unavailable</strong>@else<strong>Unrated</strong>@endif</div>
                        </div>
                        @if($displayRating)
                            <details class="rating-evidence text-muted"><summary>Rating evidence</summary>
                            <p class="small text-muted mb-1">Last eligible activity: {{ $displayRating['last_eligible_activity'] ?? $displayRating['last_played'] ?? 'unknown' }} · {{ $displayRating['baseline_status'] ?? 'No connected main-trial baseline' }}</p>
                            <p class="small text-muted mb-0">{{ $displayRating['confidence_explanation'] }}</p>
                            </details>
                        @else<p class="small text-muted mb-0">{{ !$member['player_id'] ? 'Imported roster member has no linked player profile.' : ($snapshot['reason'] ? 'A safe current estimate is unavailable; this does not mean the player has no rating evidence.' : 'No eligible saved estimate for this exact cohort; check the player evidence or pending update.') }}</p>@endif
                    </li>
                @endforeach
                </ul>
                @endforeach
            </section>
        @empty<p>No matching players in this selection.</p>@endforelse
        {{ $players->links('pagination::bootstrap-5') }}
    @endif
    <div class="d-flex flex-wrap gap-3 mt-3">
        @if($event)<a style="min-height:44px" href="{{ route('backend.player-performance.ratings') }}">Site-wide ratings</a>@endif
        <a style="min-height:44px" href="{{ route('backend.player-performance.directory') }}">Search all player profiles</a>
        <a style="min-height:44px" href="{{ route('backend.player-performance.index') }}">A/B performance comparison</a>
    </div>
</div></div>
</div>
@endsection
