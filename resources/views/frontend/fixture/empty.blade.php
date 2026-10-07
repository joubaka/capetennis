@extends('layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
  @if($draw->published)
    @include('frontend.fixtures.partials.live-results-status')
  @endif
  <div @if($draw->published) data-live-results="{{ $draw->isTeamDraw() ? 'team-fixtures' : 'individual-fixtures' }}" @endif>
  <div class="card" data-live-results-empty>
    <div class="card-body">
      <h3 class="mb-2">{{ $draw->drawName }}</h3>
      <p class="text-muted">{{ $event->name }}</p>
      <div class="alert alert-info" role="status">
        No matches are available to view yet. Please check back for updates.
      </div>
      <a href="{{ route('events.show', $event) }}" class="btn btn-outline-secondary">Back to tournament</a>
    </div>
  </div>
  </div>
  @include('frontend.fixtures.partials.live-results-script')
@endsection
