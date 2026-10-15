@extends('layouts/layoutMaster')
@section('title', 'Standings – '.$event->name)
@section('content')
<div id="public-age-points" class="container-xxl py-4">
  @php
    $standingsRoute = ($publicPreview ?? false) ? 'admin.events.standings.preview' : 'frontend.events.standings';
  @endphp
  @if($publicPreview ?? false)
    <div class="alert alert-info" role="status"><strong>Private preview</strong> — this shows the public standings layout using only published draws and ties. Previewing does not publish anything.</div>
  @endif
  <style>
    #public-age-points { max-width: 1120px; min-width: 0; }
    #public-age-points .points-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem; }
    #public-age-points h1 { color:#203b50; font-size:clamp(1.5rem, 4vw, 2rem); font-weight:700; letter-spacing:-.03em; overflow-wrap:anywhere; margin:0; }
    #public-age-points .points-intro { color:#61756c; margin-bottom:1.5rem; }
    #public-age-points .points-filter { display:flex; flex-wrap:wrap; align-items:end; gap:.75rem; padding:1rem; background:#edf3ed; border:1px solid #dce6dc; border-radius:12px; margin-bottom:1.25rem; }
    #public-age-points .points-filter-field { min-width:0; flex:1 1 12rem; max-width:24rem; }
    #public-age-points .points-filter label { color:#425c50; font-size:.85rem; margin-bottom:.35rem; display:block; }
    #public-age-points .form-select, #public-age-points .btn { min-height:44px; }
    #public-age-points a.btn { color:#365940; border-color:#b9cdbf; background:#fff; }
    #public-age-points a.btn:hover, #public-age-points a.btn:focus-visible { color:#284631; background:#e7f0e5; border-color:#88a993; }
    #public-age-points .points-groups { display:grid; grid-template-columns:1fr; gap:1.25rem; }
    #public-age-points .points-card { min-width:0; background:#fff; border:1px solid #dfe7e2; border-radius:14px; overflow:hidden; }
    #public-age-points .points-card-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:1.1rem 1.25rem; background:#233f53; border-bottom:4px solid #88a993; }
    #public-age-points .points-card:nth-child(even) .points-card-header { background:#3f6656; border-bottom-color:#a9c0a5; }
    #public-age-points .points-card-header h2 { color:#fff; font-size:1.15rem; font-weight:600; margin:0; }
    #public-age-points .points-card-header span { color:#deebe4; font-size:.75rem; }
    #public-age-points .points-table-scroll { overflow-x:auto; }
    #public-age-points .points-table { width:100%; border-collapse:collapse; }
    #public-age-points .points-position { width:60px; text-align:center; font-size:1.1rem; font-weight:700; color:#365940; }
    #public-age-points .points-record { display:block; color:#617168; font-size:.8rem; font-weight:400; margin-top:.3rem; }
    #public-age-points .points-details { border-top:1px solid #edf1ec; padding:1rem 1.25rem; }
    #public-age-points .points-details summary { cursor:pointer; color:#365940; font-weight:600; min-height:44px; display:flex; align-items:center; }
    #public-age-points .points-details summary::after { content:'⌄'; margin-left:auto; padding-left:.75rem; font-size:1.2rem; }
    #public-age-points .points-details[open] summary::after { content:'⌃'; }
    #public-age-points .points-details .points-table { min-width:520px; }
    #public-age-points .points-table tbody tr[data-position="1"] { background:#f1f6ed; }
    #public-age-points .points-table th, #public-age-points .points-table td { padding:.9rem 1.25rem; }
    #public-age-points .points-table thead { color:#617168; background:#f6f8f5; font-size:.7rem; letter-spacing:.04em; text-transform:uppercase; }
    #public-age-points .points-table td, #public-age-points .points-table thead th:last-child { width:90px; text-align:right; }
    #public-age-points .points-table thead th { text-align:left; }
    #public-age-points .points-table td.points-position { width:48px; text-align:center; }
    #public-age-points .points-details .points-table thead th:not(:first-child) { width:90px; text-align:right; }
    #public-age-points .points-table tbody tr + tr { border-top:1px solid #edf1ec; }
    #public-age-points .points-table tbody th { color:#2d4350; font-weight:600; font-size:.9rem; overflow-wrap:anywhere; }
    #public-age-points .points-pill { display:inline-block; min-width:40px; padding:.35rem .6rem; border-radius:8px; background:#e7f0e5; color:#365940; font-size:1rem; font-weight:700; }
    #public-age-points .points-region { display:inline-block; width:4px; height:1rem; border-radius:3px; margin-right:.5rem; vertical-align:middle; background:var(--region-color); }
    #public-age-points .points-empty { padding:1rem 1.25rem; color:#617168; font-size:.85rem; margin:0; }
    #public-age-points .points-note { font-size:.85rem; color:#617168; margin:1rem 0; }
    @media(max-width:575.98px) {
      #public-age-points .points-filter-field { max-width:none; flex-basis:100%; }
      #public-age-points .points-table th, #public-age-points .points-table td { padding:.85rem .9rem; }
      #public-age-points .points-card-header { padding:1rem; }
    }
  </style>
  <div class="points-heading">
    <h1>{{ $event->name }}</h1>
    @if($publicPreview ?? false)
    <a class="btn btn-outline-secondary" href="{{ route('admin.events.standings', $event) }}">Back to admin standings</a>
    @else
    <a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Back to tournament</a>
    @endif
  </div>
  <p class="points-intro"><strong>Team standings</strong> by age group. See each region / school’s position, total points and match record, with boys’, girls’ and mixed points combined. Only published draws and ties contribute.</p>
  <form method="GET" action="{{ route($standingsRoute, $event) }}" class="points-filter">
    <div class="points-filter-field">
      <label for="standings-age">Age group</label>
      <select class="form-select" id="standings-age" name="age">
        <option value="">All age groups</option>
        @foreach(collect($options['age'])->sort(fn ($a, $b) => strnatcasecmp($a, $b)) as $option)<option value="{{ $option }}" @selected(($filters['age'] ?? '') === $option)>{{ $option }}</option>@endforeach
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Apply filter</button>
    <a class="btn btn-outline-secondary" href="{{ route($standingsRoute, $event) }}">Reset</a>
  </form>
  @include('frontend.fixtures.partials.live-results-status')
  <div data-live-results="event-standings">
    @include('frontend.fixtures.partials.team-placement-results')
    <div class="points-groups">
      @forelse($ageStandings as $age => $rows)
      @php
        $showOther = collect($rows)->contains(fn ($row) => $row['gender_points']['other'] != 0);
        $colors = collect($ageGroups[$age] ?? [])->pluck('color', 'name');
        $hasResults = collect($rows)->contains(fn ($row) => $row['rubber_wins'] + $row['rubber_losses'] > 0);
      @endphp
      <section class="points-card" data-age-points="{{ $age }}">
        <div class="points-card-header"><h2>{{ $age === 'Unspecified' ? 'Age group unspecified' : $age.' standings' }}</h2><span>{{ count($rows) }} regions / schools</span></div>
        <div class="points-table-scroll" tabindex="0" role="region" aria-label="{{ $age }} points table; scroll horizontally if needed">
        <table class="points-table" aria-label="{{ $age }} points by region or school">
          <thead><tr><th scope="col">Pos.</th><th scope="col">Region / school</th><th scope="col">Points</th></tr></thead>
          <tbody>
            @foreach($rows as $row)
            <tr data-position="{{ $hasResults ? $row['rank'] : '' }}"><td class="points-position">{{ $hasResults ? ($row['rank'] ?? '—') : '—' }}</td><th scope="row"><span class="points-region" style="--region-color:{{ $colors->get($row['name'], '#617168') }}" aria-hidden="true"></span>{{ $row['name'] }}<span class="points-record">{{ $row['rubber_wins'] }} won · {{ $row['rubber_losses'] }} lost · {{ $row['rubber_wins'] + $row['rubber_losses'] }} matches</span></th><td><span class="points-pill" data-points="{{ $row['points'] }}">{{ $row['points'] }}</span></td></tr>
            @endforeach
          </tbody>
        </table>
        </div>
        <details class="points-details">
          <summary>Points breakdown and match statistics</summary>
          <div class="points-table-scroll" tabindex="0" role="region" aria-label="{{ $age }} detailed statistics; scroll horizontally if needed">
            <table class="points-table" aria-label="{{ $age }} points breakdown">
              <thead><tr><th scope="col">Region / school</th><th scope="col">Boys</th><th scope="col">Girls</th><th scope="col">Mixed</th>@if($showOther)<th scope="col">Other</th>@endif<th scope="col">Sets W–L</th><th scope="col">Games W–L</th></tr></thead>
              <tbody>@foreach($rows as $row)<tr><th scope="row">{{ $row['name'] }}</th>@foreach(['boys', 'girls', 'mixed'] as $bucket)<td data-gender-points="{{ $bucket }}">{{ $row['gender_points'][$bucket] }}</td>@endforeach@if($showOther)<td data-gender-points="other">{{ $row['gender_points']['other'] }}</td>@endif<td>{{ $row['sets_for'] }}–{{ $row['sets_against'] }}</td><td>{{ $row['games_for'] }}–{{ $row['games_against'] }}</td></tr>@endforeach</tbody>
            </table>
          </div>
        </details>
        @if($showOther)<p class="points-empty">Other includes points without a specified gender, including bonuses from those draws.</p>@endif
        @if($ageMixedRules[$age])<p class="points-empty">Points use each draw’s scoring rules. Regions / schools are listed alphabetically.</p>@endif
        @if(!$hasResults)<p class="points-empty">No completed match points yet.</p>@endif
      </section>
      @empty<p class="points-empty">No published points or participants for this age group yet.</p>@endforelse
    </div>
    <p class="points-note">Positions follow each draw’s saved scoring and tie-break rules; equal records can share a position. Standings reflect completed published results and may change while play continues. Different scoring rules within an age group mean no combined positions are shown.</p>
  </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
