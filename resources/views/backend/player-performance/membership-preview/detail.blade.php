@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player detail · private membership preview')
@section('content')
<div class="mp-preview">
@include('backend.player-performance.membership-preview.chrome')
@if($selectedPlayer)
@if(str_starts_with($selected, 'u10 '))<p class="mp-note">U10 ball type and court size are unverified. The recorded cohort does not establish compatible playing formats.</p>@endif
@include('backend.player-performance.membership-preview.player-evidence', ['player' => $selectedPlayer])
<p class="mp-note mt-3">The model limits one event's total influence and gives older evidence less weight. Finishing order and trial references provide separate context. This page does not calculate a rating change for each match. Recorded cohort labels do not establish current-season age eligibility.</p>
@if($evidence['reason'])<div class="alert alert-warning" role="status">{{ $evidence['reason'] }} Saved estimates above are not a live assessment of changed results.</div>@endif
@if($evidence['available'])
<p class="mp-subtle">Verified saved-source record: {{ $evidence['players'][$selectedPlayer['id']]['wins'] }} wins · {{ $evidence['players'][$selectedPlayer['id']]['losses'] }} losses.</p>
@include('backend.player-performance.membership-preview.match-history', ['historyName' => $selectedPlayer['name']])
@endif
@endif
@include('backend.player-performance.membership-preview.footer')
</div>
@endsection
