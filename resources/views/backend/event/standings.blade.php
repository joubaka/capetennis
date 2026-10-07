@extends('layouts.backend')
@section('title', 'Standings – '.$event->name)
@section('content')
<div data-backend-wide>
  @include('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Event standings and competition statistics'])
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h1 class="h4 mb-0">Standings &amp; statistics</h1><a class="btn btn-outline-primary" href="{{ route('backend.scoreboard.team.show', $event) }}">View match results</a></div>
  <section class="card card-body mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div><h2 class="h5 mb-1">Public team standings</h2><span class="badge bg-label-{{ $event->standings_published ? 'success' : 'secondary' }}">{{ $event->standings_published ? 'Published' : 'Unpublished' }}</span></div>
      @can('team-draw.createFormat', $event)
      <form method="POST" action="{{ route('admin.events.standings.publication', $event) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="standings_published" value="{{ $event->standings_published ? '0' : '1' }}">
        <button class="btn {{ $event->standings_published ? 'btn-outline-secondary' : 'btn-primary' }}" style="min-height:44px" type="submit">{{ $event->standings_published ? 'Unpublish standings' : 'Publish standings' }}</button>
      </form>
      @endcan
    </div>
    <p class="small text-muted mb-0 mt-3">Publishing shows Team standings and match totals on the public event page and enables public draw standings. Only published draws and ties contribute. These are current running standings, not final tournament placings. Event, draw, schedule and final results publication remain separate.</p>
  </section>
  <form method="GET" class="card card-body mb-4">
    <div class="row g-3 align-items-end">
      @foreach(['gender' => 'Gender', 'age' => 'Age group', 'category' => 'Category'] as $key => $label)
      <div class="col-12 col-md-3"><label for="standings-{{ $key }}" class="form-label">{{ $label }}</label>
        <select id="standings-{{ $key }}" name="{{ $key }}" class="form-select"><option value="">All {{ strtolower($label) }}</option>
          @foreach($options[$key] as $option)<option value="{{ $option }}" @selected(($filters[$key] ?? '') === $option)>{{ $option }}</option>@endforeach
        </select>
      </div>
      @endforeach
      <div class="col-12 col-md-3 d-flex flex-wrap gap-2"><button class="btn btn-primary">Apply filters</button><a class="btn btn-outline-secondary" href="{{ route('admin.events.standings', $event) }}">Reset</a></div>
    </div>
  </form>
  @include('frontend.fixtures.partials.live-results-status')
  <div data-live-results="admin-event-standings">
  <div class="row g-3 mb-4">
    @foreach(['draws' => 'Draws', 'teams' => 'Teams in ties', 'ties' => 'Completed ties', 'rubbers' => 'Completed rubbers'] as $key => $label)
      <div class="col-6 col-lg-3"><div class="card card-body h-100"><span class="text-muted">{{ $label }}</span><strong class="h3 mb-0">{{ $stats[$key] }}</strong></div></div>
    @endforeach
  </div>
  <section class="card mb-4"><div class="card-header"><h2 class="h5 mb-2">{{ array_filter($filters) ? 'Filtered' : 'Full event' }} standings by region / school</h2>
    <p class="mb-0">Totals combine team ties across the selected draws. Completed rubbers earn points; ties count as played once all required rubbers are complete. These are running standings, not final tournament placings.</p>
    @if($mixedRules)<p class="text-warning mb-0 mt-2">Draws use different scoring rules. Totals are shown alphabetically without an overall rank; use each draw's standings for its ranking.</p>@endif
  </div>
  @include('backend.event.partials.standings-table', ['rows' => $overall])
  </section>
  <div class="row g-3 mb-4">
    @foreach(['gender' => 'Gender', 'age' => 'Age group', 'category' => 'Category'] as $key => $label)
    <section class="col-12 col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">{{ $label }} breakdown</h2></div>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ $label }}</th><th>Draws</th><th>Teams</th><th>Ties</th><th>Rubbers</th></tr></thead><tbody>
        @forelse($breakdowns[$key] as $name => $counts)<tr><th scope="row">{{ $name }}</th>@foreach($counts as $count)<td>{{ $count }}</td>@endforeach</tr>@empty<tr><td colspan="5">No matching draws.</td></tr>@endforelse
      </tbody></table></div>
    </div></section>
    @endforeach
  </div>
  <h2 class="h4">Standings by draw</h2>
  <p>Ranks follow each draw's saved scoring rules. Teams tied on all configured criteria share a rank. Gender and age groups come from recorded categories, draw names and gender labels; unlabelled groups appear as Unspecified.</p>
  @forelse($sections as $section)
    <section class="card mb-4"><div class="card-header"><h3 class="h5 mb-1">{{ $section['draw']->drawName }}</h3><span class="text-muted">{{ $section['category'] }} · {{ $section['gender'] }} · {{ $section['age'] }}</span></div>
      @include('backend.event.partials.standings-table', ['rows' => $section['rows']])
    </section>
  @empty<div class="alert alert-info">No team draws match these filters.</div>@endforelse
  <p class="text-muted">Legacy fixtures contribute completed rubbers, sets, games and points by region. They do not count as completed team ties.</p>
  </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
