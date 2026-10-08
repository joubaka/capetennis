@extends('layouts.backend')
@section('content')
@php
  $lineup = $team_fixture->lineup_display;
  $home = $lineup['home'];
  $away = $lineup['away'];
@endphp
<div class="container-xxl">
  <a href="{{ url()->previous() }}" class="btn btn-link mb-3">&larr; Back</a>
  <div class="card mb-3"><div class="card-body">
    <h1 class="h4">{{ $home['region'] }} vs {{ $away['region'] }}</h1>
    <div class="row g-3 mt-1">
      <div class="col-md-6"><strong>Scheduled</strong><div>{{ $team_fixture->scheduled_at?->format('D j M Y H:i') ?? 'Unscheduled' }}</div></div>
      <div class="col-md-6"><strong>Venue / court</strong><div>{{ $team_fixture->venue?->name ?? 'Venue not assigned' }} @if($team_fixture->court_label) · {{ $team_fixture->court_label }} @endif</div></div>
    </div>
  </div></div>
  <div class="card mb-3"><div class="card-header"><h2 class="h5 mb-0">Match Players</h2></div><div class="card-body">
    @if(empty($home['players']) && empty($away['players']))
      <div class="alert alert-info mb-0">No player rows linked to this fixture yet.</div>
    @else
      @foreach(range(0, max(count($home['players']), count($away['players'])) - 1) as $index)
        <div class="row g-2 border-bottom py-3">
          <div class="col-12 col-sm-6"><span class="small text-muted">Home · {{ $home['region'] }}</span><div>@if($player = $home['players'][$index] ?? null) @if($player['rank'])<strong>({{ $player['rank'] }})</strong>@endif {{ $player['name'] }} @else Players to be confirmed @endif</div></div>
          <div class="col-12 col-sm-6"><span class="small text-muted">Away · {{ $away['region'] }}</span><div>@if($player = $away['players'][$index] ?? null) @if($player['rank'])<strong>({{ $player['rank'] }})</strong>@endif {{ $player['name'] }} @else Players to be confirmed @endif</div></div>
        </div>
      @endforeach
    @endif
  </div></div>
  <details class="card mb-3"><summary class="card-header" style="min-height:44px">Fixture details</summary><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Fixture</dt><dd class="col-sm-9">#{{ $team_fixture->id }}</dd>
      <dt class="col-sm-3">Event</dt><dd class="col-sm-9">{{ $team_fixture->draw?->event?->name ?? '—' }}</dd>
      <dt class="col-sm-3">Draw</dt><dd class="col-sm-9">{{ $team_fixture->draw?->drawName ?? '—' }}</dd>
      <dt class="col-sm-3">Round / Tie</dt><dd class="col-sm-9">{{ $team_fixture->round_name ?? $team_fixture->round }} / {{ $team_fixture->tie_name ?? $team_fixture->tie }}</dd>
    </dl>
  </div></details>
</div>
@endsection
