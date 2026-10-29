@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Active leaderboard · membership preview')
@section('content')
<div class="mp-preview">
@include('backend.player-performance.membership-preview.chrome')
@include('backend.player-performance.membership-preview.overview')
<p class="mp-note">Private positions use the saved cohort and connected comparison group. They are not official selection ranks. Current age eligibility, playing conditions and these proposed activity criteria have not been verified for competition use. Equal displayed ratings share a position; filtering and paging do not renumber positions.</p>
@include('backend.player-performance.membership-preview.filters')
@if($activePlayers->isEmpty())
<section class="mp-empty"><div class="mp-empty-icon" aria-hidden="true">—</div><h2>No active players on this page</h2><p class="mp-subtle">@if($reason)An active leaderboard cannot be assessed from the available saved data.@elseif($selected && ($cohort_summary[$selected]['active'] ?? 0) === 0)No saved {{ $cohorts[$selected] }} profiles currently meet all proposed active criteria. Their other evidence remains in the directory without an active position.@elseif($active_count === 0)No displayable saved profiles currently meet all proposed criteria. Other evidence remains in the directory without an active position.@else No active players match these filters or page.@endif</p><a class="mp-btn" href="{{ route('backend.player-performance.membership-preview.directory', array_filter(['cohort' => $selected])) }}">Explore the player directory</a></section>
@else
@include('backend.player-performance.membership-preview.active-groups', ['activeRows' => $activePlayers->items()])
@endif
<p class="mp-subtle mt-3">{{ $activePlayers->total() }} active profiles match these filters · up to 25 per page.</p>
{{ $activePlayers->links('pagination::bootstrap-5') }}
@include('backend.player-performance.membership-preview.rules')
@include('backend.player-performance.membership-preview.footer')
</div>
@endsection
