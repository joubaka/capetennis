@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', $event ? 'Event player ratings' : 'Site-wide player ratings')
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
@if($event)
    @include('backend.event.partials.workspace-nav', ['eventWorkspaceActive' => 'ratings'])
@endif
<div class="card"><div class="card-body">
    <h1 class="h3">{{ $event ? $event->name.' · player ratings' : 'Site-wide player ratings' }}</h1>
    <p>Private provisional singles ratings. Highest to lowest within each exact cohort and comparison group. Positions refer to this selection. Separate groups cannot be compared. Ratings are calculated from published evidence; they cannot be edited here.</p>
    <p class="text-muted">Saved calculation: {{ $snapshot['built_at'] }} · as of {{ $snapshot['snapshot_as_of'] ?? 'pending' }}.</p>
    @if($refreshStatus['failed'])<div class="alert alert-warning" role="status">The latest background refresh failed. Saved estimates remain provisional.</div>
    @elseif($refreshStatus['pending'] && !$snapshot['reason'] && !($snapshot['snapshot_stale'] ?? false))<div class="alert alert-info" role="status">A background update is pending. Showing the last saved calculation.</div>@endif
    @if($snapshot['reason'])<div class="alert alert-warning" role="status">{{ $snapshot['reason'] }}</div>
    @elseif($snapshot['snapshot_stale'] ?? false)<div class="alert alert-warning" role="status">The saved calculation is stale and awaiting background refresh.</div>@endif
    @if($limitReason)<div class="alert alert-warning" role="status">{{ $limitReason }}</div>@endif
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-6"><label for="cohort" class="form-label">Exact age / gender / playing context</label>
            <select name="cohort" id="cohort" class="form-select" style="min-height:44px">
                <option value="">{{ $event ? 'All event cohorts (separate groups)' : 'Choose a cohort' }}</option>
                @foreach($cohorts as $cohort)<option value="{{ $cohort }}" @selected($selected === $cohort)>{{ $cohort }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4"><label for="search" class="form-label">Player name</label><input type="search" name="search" id="search" maxlength="100" class="form-control" style="min-height:44px" value="{{ $search }}"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary" style="min-height:44px">Show ratings</button></div>
    </form>
    @foreach($errors->all() as $error)<p class="text-danger">{{ $error }}</p>@endforeach
    @if(!$event && !$selected)
        <p>Choose a cohort to see players across the site. Cohorts follow recorded event categories, rather than a player's current age.</p>
    @else
        <p>{{ $players->total() }} player / cohort entries. Includes recorded individual entries (including unpaid and withdrawn entries) and saved team rosters. Unrated members remain visible. A player entered in multiple cohorts appears once in each.</p>
        @if(!$event)<p class="small text-muted">Membership follows recorded categories and saved rating evidence. Legacy teams without a category are available in their event view as unresolved; they are not assigned an age group by guesswork.</p>@endif
        @forelse($players->getCollection()->groupBy(fn ($row) => $row['cohort'].' · '.$row['component']) as $group => $members)
            <section class="mb-4" aria-label="{{ $group }}"><h2 class="h5">{{ $group }}</h2>
                <ul class="list-group">
                @foreach($members as $member)
                    <li class="list-group-item">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>@if($member['position'])<span class="text-muted me-2">{{ $member['position'] }}.</span>@endif
                                @if($member['player_id'])<a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.show', $member['player_id']) }}">{{ $member['name'] }}</a>@else<span>{{ $member['name'] }}</span>@endif
                            </div>
                            <div class="align-self-center">@if($member['rating'])<strong>{{ number_format($member['rating']['score'], 1) }}/100</strong> · {{ $member['rating']['confidence_label'] }} confidence @else<strong>Unrated</strong>@endif</div>
                        </div>
                        @if($member['rating'])
                            <p class="small text-muted mb-1">Last eligible activity: {{ $member['rating']['last_eligible_activity'] ?? $member['rating']['last_played'] ?? 'unknown' }} · {{ $member['rating']['baseline_status'] ?? 'No connected main-trial baseline' }}</p>
                            <p class="small text-muted mb-0">{{ $member['rating']['confidence_explanation'] }}</p>
                        @else<p class="small text-muted mb-0">{{ $member['player_id'] ? 'No eligible saved estimate for this exact cohort; check the player evidence or pending update.' : 'Imported roster member has no linked player profile.' }}</p>@endif
                    </li>
                @endforeach
                </ul>
            </section>
        @empty<p>No matching players in this selection.</p>@endforelse
        {{ $players->links() }}
    @endif
    <div class="d-flex flex-wrap gap-3 mt-3">
        @if($event)<a style="min-height:44px" href="{{ route('backend.player-performance.ratings') }}">Site-wide ratings</a>@endif
        <a style="min-height:44px" href="{{ route('backend.player-performance.directory') }}">Search all player profiles</a>
        <a style="min-height:44px" href="{{ route('backend.player-performance.index') }}">A/B performance comparison</a>
    </div>
</div></div>
</div>
@endsection
