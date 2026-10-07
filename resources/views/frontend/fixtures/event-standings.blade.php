@extends('layouts/layoutMaster')
@section('title', 'Standings – '.$event->name)
@section('content')
<div class="container-xxl py-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">{{ $event->name }}: standings</h1>
    <a class="btn btn-outline-secondary" style="min-height:44px" href="{{ route('events.show', $event) }}">Back to tournament</a>
  </div>
  <p>Completed matches contribute points, match wins, sets and games. Team ties count as played once every required match is complete. These are running standings.</p>
  <form method="GET" class="card card-body mb-3">
    <div class="row g-3 align-items-end">
      @foreach(['gender' => 'Gender', 'age' => 'Age group', 'category' => 'Category'] as $key => $label)
        <div class="col-12 col-md-3">
          <label class="form-label" for="standings-{{ $key }}">{{ $label }}</label>
          <select class="form-select" style="min-height:44px" id="standings-{{ $key }}" name="{{ $key }}">
            <option value="">All {{ strtolower($label) }}</option>
            @foreach($options[$key] as $option)<option value="{{ $option }}" @selected(($filters[$key] ?? '') === $option)>{{ $option }}</option>@endforeach
          </select>
        </div>
      @endforeach
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-primary" style="min-height:44px">Apply filters</button>
        <a class="btn btn-outline-secondary" style="min-height:44px" href="{{ route('frontend.events.standings', $event) }}">Reset</a>
      </div>
    </div>
  </form>
  @include('frontend.fixtures.partials.live-results-status')
  <div data-live-results="event-standings">
    <section class="card mb-4">
      <div class="card-header"><h2 class="h5 mb-0">{{ array_filter($filters) ? 'Filtered' : 'Full event' }} standings by region / school</h2>
        @if($mixedRules)<p class="mb-0 mt-2">Draws use different scoring rules. Overall totals are shown without a rank; each draw keeps its own ranking.</p>@endif
      </div>
      @include('backend.event.partials.standings-table', ['rows' => $overall])
    </section>
    <h2 class="h4">Standings by draw</h2>
    @forelse($sections as $section)
      <section class="card mb-4">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <h3 class="h5 mb-0">{{ $section['draw']->drawName }}</h3>
          <a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.fixtures.index', $section['draw']) }}">View match results</a>
        </div>
        @include('backend.event.partials.standings-table', ['rows' => $section['rows']])
      </section>
    @empty<div class="alert alert-info">No published team draws match these filters.</div>@endforelse
    <p class="text-muted">Legacy matches contribute match wins, sets, games and points by region, but do not count as completed team ties.</p>
  </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
