@extends('layouts/layoutMaster')

@section('title', 'Order of Play – '.$venue->name)

@section('content')
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <div><h3>Order of Play</h3><div>{{ $event->name }}</div><strong>{{ $venue->name }}</strong></div>
    <a class="btn btn-outline-primary align-self-start" href="{{ route('events.show', $event) }}">Back to tournament</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($availableDates as $availableDate)
      <a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('fixtures.order', [$event->id, $venue->id, $availableDate, 'draw_id' => $selectedDrawId]) }}">{{ \Carbon\Carbon::parse($availableDate)->format('D j M') }}</a>
    @endforeach
    <a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('fixtures.order', [$event->id, $venue->id, 'all', 'draw_id' => $selectedDrawId]) }}">All days</a>
  </div>
  <form method="GET" action="{{ route('fixtures.order', [$event->id, $venue->id, $date]) }}" class="mb-4">
    <label for="order-draw" class="form-label">Age group / draw</label>
    <div class="d-flex gap-2">
      <select id="order-draw" name="draw_id" class="form-select">
        <option value="">All draws at this venue</option>
        @foreach($availableDraws as $availableDraw)
          <option value="{{ $availableDraw->id }}" @selected((int) $selectedDrawId === (int) $availableDraw->id)>{{ $availableDraw->drawName }}</option>
        @endforeach
      </select>
      <button class="btn btn-outline-primary" type="submit">Apply</button>
    </div>
  </form>
  @forelse($fixtures->groupBy(fn ($fixture) => \Carbon\Carbon::parse($fixture->scheduled_at)->toDateString()) as $fixtureDate => $dayFixtures)
    <h4 class="mt-4">{{ \Carbon\Carbon::parse($fixtureDate)->format('l, j F Y') }}</h4>
    @foreach($dayFixtures->groupBy('draw_id') as $drawFixtures)
      <div class="mb-4">
        @include('frontend.fixture.fixture-table', ['draw' => $drawFixtures->first()->draw, 'fixtures' => $drawFixtures, 'fixtureTableId' => 'venue-draw-'.$drawFixtures->first()->draw_id.'-'.$fixtureDate])
      </div>
    @endforeach
  @empty
    <p class="alert alert-info">No published matches at this venue for this selection.</p>
  @endforelse
@endsection
