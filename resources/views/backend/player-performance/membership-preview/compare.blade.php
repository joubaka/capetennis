@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Compare players · private membership preview')
@section('content')
<div class="mp-preview">
@include('backend.player-performance.membership-preview.chrome')
@if(str_starts_with($selected, 'u10 '))<p class="mp-note">U10 ball type and court size are unverified. A shared recorded cohort or comparison group does not verify compatible playing formats.</p>@endif
<form method="get" class="mp-filters"><div><label for="compare-cohort">1. Choose a recorded age group</label><select id="compare-cohort" name="cohort" required><option value="">Choose a group</option>@foreach($cohorts as $key => $label)<option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>@endforeach</select></div><button class="mp-btn" type="submit">Choose players in this group</button></form>
@if($selected && !$reason)
<form method="get" class="mp-filters"><input type="hidden" name="cohort" value="{{ $selected }}">@foreach(['first' => 'First player', 'second' => 'Second player'] as $field => $label)@php($selectedId = ($field === 'first' ? $firstPlayer : $secondPlayer)['id'] ?? null)<div><label for="compare-{{ $field }}">{{ $label }}</label><select id="compare-{{ $field }}" name="{{ $field }}" required><option value="">Choose a player</option>@foreach($options as $option)<option value="{{ $option['id'] }}" @selected($selectedId === $option['id'])>{{ $option['name'] }} · {{ $option['cohort_label'] }} · {{ $option['status'] }}</option>@endforeach</select></div>@endforeach<button class="mp-btn" type="submit">Compare these players</button></form>
@endif
@if($firstPlayer && $secondPlayer)
@if($assessment['comparable'])<section class="mp-note"><strong>{{ $assessment['provisional'] ? 'Provisional comparison' : 'Descriptive saved comparison' }}</strong><br>Saved displayed score gap ({{ $firstPlayer['name'] }} minus {{ $secondPlayer['name'] }}): {{ number_format($assessment['gap'], 1) }} points. This does not predict a winner or establish an official ranking. @if($assessment['provisional'])Evidence is still provisional; nearby ordering can change.@endif<br>Recorded eligible source matches only, in this cohort: {{ $assessment['head_to_head']['played'] }} head-to-head · {{ $assessment['head_to_head']['first_wins'] }} wins for {{ $firstPlayer['name'] }} · {{ $assessment['head_to_head']['second_wins'] }} wins for {{ $secondPlayer['name'] }}.</section>@else<section class="mp-note"><strong>Not directly comparable</strong><br>{{ $assessment['reason'] }} No numeric gap or winner probability is presented.</section>@endif
@if($evidence['reason'])<div class="alert alert-warning mt-3">{{ $evidence['reason'] }}</div>@endif
<div class="mp-grid mt-3"><div>@include('backend.player-performance.membership-preview.player-evidence', ['player' => $firstPlayer])@if($evidence['available'])@include('backend.player-performance.membership-preview.match-history', ['historyName' => $firstPlayer['name'], 'historyPage' => $firstHistory])@endif</div><div>@include('backend.player-performance.membership-preview.player-evidence', ['player' => $secondPlayer])@if($evidence['available'])@include('backend.player-performance.membership-preview.match-history', ['historyName' => $secondPlayer['name'], 'historyPage' => $secondHistory])@endif</div></div>
@endif
<p class="mp-subtle mt-3">Compare within the same recorded cohort and connected group. Inactive and unrated profiles retain separate evidence cards. Current-season age eligibility and playing conditions are not established here. No per-match rating changes are invented.</p>
@include('backend.player-performance.membership-preview.footer')
</div>
@endsection
