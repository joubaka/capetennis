@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player ratings by age group')
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="card"><div class="card-body">
    <h1 class="h3">Player ratings by age group</h1>
    @if($refreshStatus['pending'] || $refreshStatus['failed'])
        <div class="alert alert-info py-2 mb-3" role="status" data-ratings-refresh-message>Updating ratings. This page will refresh automatically when the calculation is ready.</div>
        <button type="button" class="btn btn-outline-primary mb-3" data-ratings-refresh-retry hidden>Retry ratings update</button>
    @endif
    <p>Browse current player profiles by age group and gender, or search a player by name.</p>
    <p class="small text-muted">{{ $year }} season: age is the age reached by 31 December. U10 includes younger players; U18 includes ages 14–18. These profile groups do not establish event eligibility.</p>
    <form method="get" class="row g-2 mb-3">
        <div class="col-sm-6 col-lg-3"><label for="age-group" class="form-label">Age group</label><select name="age_group" id="age-group" class="form-select" style="min-height:44px"><option value="">All ages</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::AGE_GROUPS as $key => $label)<option value="{{ $key }}" @selected($ageGroup === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-3"><label for="gender" class="form-label">Gender</label><select name="gender" id="gender" class="form-select" style="min-height:44px"><option value="">All genders</option>@foreach(\App\Services\Performance\PlayerRatingProfileDirectory::GENDERS as $key => $label)<option value="{{ $key }}" @selected($gender === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-sm-8 col-lg-4"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="col-sm-4 col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100" style="min-height:44px">Show ratings</button></div>
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    <p>{{ $players->total() }} player profiles · alphabetical by surname. Numbers identify directory rows, not rating ranks.</p>
    <p class="small text-muted">Saved scores are out of 100 and compare players only within the same recorded age / gender / playing context and comparison group. Moving into a new age group does not recalculate or combine historical scores.</p>
    @if($snapshot['reason'])<p class="alert alert-warning">Ratings are currently unavailable. Profile search remains available.</p>@else<p class="small text-muted">Last successful calculation: {{ $snapshot['built_at'] }} · evidence as of {{ $snapshot['snapshot_as_of'] ?? 'unknown' }}.</p>@endif
    <ol class="list-group list-group-numbered mb-3" start="{{ $players->firstItem() ?? 1 }}" style="counter-reset:section {{ ($players->firstItem() ?? 1) - 1 }}">
    @forelse($players as $player)
        <li class="list-group-item d-flex gap-2" style="min-width:0"><div style="min-width:0; overflow-wrap:anywhere">
            <a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.show', $player['id']) }}"><strong>{{ $player['name'] }}</strong></a>
            <p class="small text-muted mb-2">{{ $player['age_group'] }} · {{ $player['gender'] }}</p>
            @forelse($player['ratings'] as $rating)
                <p class="mb-1"><strong>{{ number_format($rating['score'], 1) }}/100</strong> · {{ $rating['cohort'] }} · comparison group {{ $rating['component'] }} · {{ $rating['confidence_label'] }} confidence</p>
            @empty<p class="mb-1">{{ $snapshot['reason'] ? 'Rating unavailable' : 'Unrated — no eligible saved estimate' }}</p>@endforelse
            <a href="{{ route('backend.player-performance.show', $player['id']) }}" class="small d-inline-flex align-items-center" style="min-height:44px">View profile ratings &amp; evidence</a>
        </div></li>
    @empty<li class="list-group-item">No matching player profiles.</li>@endforelse
    </ol>
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
