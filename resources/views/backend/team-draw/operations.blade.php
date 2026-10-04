@extends('layouts.backend')
@section('title', 'Team ties – '.$draw->drawName)
@section('content')
<div class="container-xxl">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">{{ $draw->drawName }}: team ties</h1>
    <div class="d-flex flex-wrap gap-2">
      @can('team-fixture.schedule', $draw)<a class="btn btn-outline-primary" href="{{ route('backend.team-schedule.page', $draw) }}">Schedule rubbers</a>@endcan
      <a class="btn btn-outline-primary" href="{{ route('backend.team-draw.standings', $draw) }}">Standings</a>
    </div>
  </div>
  <p>Review the players for each rubber, validate the complete tie, then publish it before entering scores. Draw publication controls public visibility separately.</p>
  @if($draw->locked || $draw->published)<div class="alert alert-info">This draw is protected. Tie validation and publication changes are unavailable.</div>@endif
  <div id="team-tie-message" class="d-none" role="status" aria-live="polite"></div>
  @forelse($ties->groupBy('round_nr') as $roundNumber => $roundTies)
    <h2 class="h5 mt-4">Round {{ $roundNumber }}</h2>
    @foreach($roundTies as $tie)
      @php($protected = $draw->locked || $draw->published || $tie->isLocked() || $tie->published_at || $tie->winner_team_id)
      <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div><h3 class="h6 mb-1">{{ $tie->homeTeam?->name ?? 'Home' }} vs {{ $tie->awayTeam?->name ?? 'Away' }}</h3>
            <span class="badge bg-label-secondary">{{ ucfirst($tie->status) }}</span>
          </div>
          <div class="d-flex flex-wrap gap-2">
            @can('validateTie', $tie)
              <button type="button" class="btn btn-sm btn-outline-primary team-tie-action" data-url="{{ route('team-draw.ties.validate', $tie) }}" @disabled($protected || $tie->status === 'validated')>Validate tie</button>
            @endcan
            @can('publishTie', $tie)
              <button type="button" class="btn btn-sm btn-primary team-tie-action" data-url="{{ route('team-draw.ties.publish', $tie) }}" @disabled($protected || $tie->status !== 'validated')>Publish tie</button>
            @endcan
          </div>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr><th>Rubber</th><th>Match</th><th>Schedule</th><th>Actions</th></tr></thead>
          <tbody>@forelse($tie->rubbers as $rubber)
            <tr><td>{{ $rubber->rubber_sequence }}</td><td>{{ $rubber->rubber_name ?? ucwords(str_replace('_', ' ', $rubber->rubber_code ?? 'Match')) }}</td>
              <td>{{ $rubber->scheduled_at?->format('d M H:i') ?? 'Unscheduled' }} @if($rubber->court_label)<span class="text-muted">{{ $rubber->court_label }}</span>@endif</td>
              <td><div class="d-flex flex-wrap gap-2">
                @can('team-fixture.view', $rubber)<a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.team-fixtures.show', $rubber) }}">Review players</a>@endcan
                @can('team-fixture.saveScore', $rubber)<a class="btn btn-sm btn-primary" href="{{ route('frontend.fixtures.enter-scores', $draw) }}">Enter scores</a>@endcan
                @can('team-fixture.update', $rubber)<a class="btn btn-sm btn-outline-primary" href="{{ route('backend.team-fixtures.edit', $rubber) }}">Edit schedule</a>@endcan
              </div></td>
            </tr>
          @empty<tr><td colspan="4">No rubbers have been generated for this tie.</td></tr>@endforelse</tbody>
        </table></div>
      </div>
    @endforeach
  @empty<div class="alert alert-info">No team ties have been generated.</div>@endforelse
</div>
@endsection
@section('page-script')
<script src="{{ asset('js/team-tie-operations.js') }}?v={{ filemtime(public_path('js/team-tie-operations.js')) }}"></script>
@endsection
