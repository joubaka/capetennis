@extends('layouts/layoutMaster')
@section('title', 'Standings – '.$event->name)
@section('content')
<div id="public-age-points" class="container-xxl py-4">
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
    #public-age-points .points-groups { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr)); gap:1.25rem; }
    #public-age-points .points-card { min-width:0; background:#fff; border:1px solid #dfe7e2; border-radius:14px; overflow:hidden; }
    #public-age-points .points-card-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:1.1rem 1.25rem; background:#233f53; border-bottom:4px solid #88a993; }
    #public-age-points .points-card:nth-child(even) .points-card-header { background:#3f6656; border-bottom-color:#a9c0a5; }
    #public-age-points .points-card-header h2 { color:#fff; font-size:1.15rem; font-weight:600; margin:0; }
    #public-age-points .points-card-header span { color:#deebe4; font-size:.75rem; }
    #public-age-points .points-table { width:100%; table-layout:fixed; border-collapse:collapse; }
    #public-age-points .points-table th, #public-age-points .points-table td { padding:.9rem 1.25rem; }
    #public-age-points .points-table thead { color:#617168; background:#f6f8f5; font-size:.7rem; letter-spacing:.04em; text-transform:uppercase; }
    #public-age-points .points-table th:last-child, #public-age-points .points-table td:last-child { width:100px; text-align:right; }
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
    <a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}">Back to tournament</a>
  </div>
  <p class="points-intro">Running points totals by age group. Boys’, girls’ and mixed points across all match types from published draws and ties are combined for each region / school.</p>
  <form method="GET" class="points-filter">
    <div class="points-filter-field">
      <label for="standings-age">Age group</label>
      <select class="form-select" id="standings-age" name="age">
        <option value="">All age groups</option>
        @foreach(collect($options['age'])->sort(fn ($a, $b) => strnatcasecmp($a, $b)) as $option)<option value="{{ $option }}" @selected(($filters['age'] ?? '') === $option)>{{ $option }}</option>@endforeach
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Apply filter</button>
    <a class="btn btn-outline-secondary" href="{{ route('frontend.events.standings', $event) }}">Reset</a>
  </form>
  @include('frontend.fixtures.partials.live-results-status')
  <div data-live-results="event-standings">
    <div class="points-groups">
      @forelse($ageGroups as $age => $rows)
      <section class="points-card" data-age-points="{{ $age }}">
        <div class="points-card-header"><h2>{{ $age === 'Unspecified' ? 'Age group unspecified' : $age }}</h2><span>Running totals</span></div>
        <table class="points-table" aria-label="{{ $age }} points by region or school">
          <thead><tr><th scope="col">Region / school</th><th scope="col">Points</th></tr></thead>
          <tbody>
            @foreach($rows as $row)
            <tr><th scope="row"><span class="points-region" style="--region-color:{{ $row['color'] }}" aria-hidden="true"></span>{{ $row['name'] }}</th><td><span class="points-pill" data-points="{{ $row['points'] }}">{{ $row['points'] }}</span></td></tr>
            @endforeach
          </tbody>
        </table>
        @if($ageMixedRules[$age])<p class="points-empty">Points use each draw’s scoring rules. Regions / schools are listed alphabetically.</p>@endif
        @if(!collect($rows)->contains(fn ($row) => $row['points'] != 0))<p class="points-empty">No completed match points yet.</p>@endif
      </section>
      @empty<p class="points-empty">No published points or participants for this age group yet.</p>@endforelse
    </div>
    <p class="points-note">Points follow each draw’s saved scoring rules. These are running totals, not final tournament placings or an overall event ranking.</p>
  </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
