@extends('layouts.backend')
@section('title', 'Event Scoreboard')

@section('content')
@include('backend.event.partials.header', [
  'eventWorkspaceActive' => 'more',
  'eventWorkspaceIcon' => 'ti-scoreboard',
  'eventWorkspaceSubtitle' => 'Team scoreboard',
])
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="ti ti-trophy me-1"></i>Event scoreboard</h5>
      </div>
      <div class="card-body">

        @forelse($scoreboard as $age => $regions)
          @include('backend.scoreboard.partials.age-table', [
            'age' => $age,
            'regions' => $regions
          ])
        @empty
          <p class="text-muted text-center">No scoreboard data found.</p>
        @endforelse

      </div>
    </div>
  </div>
</div>
@endsection
