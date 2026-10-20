@extends('layouts.backend')
@section('title', 'Team ties – '.$draw->drawName)
@section('content')
@include('backend.draw.partials.guide-modal')
<style>[data-tie-card][hidden],[data-tie-round-group][hidden]{display:none!important;}</style>
<div class="container-xxl">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">{{ $draw->drawName }}: team ties</h1>
    <div class="d-flex flex-wrap gap-2">
      @can('event.manage', $draw->event)<a data-draw-guide-open class="btn btn-primary" href="{{ route('draw.guide.show', [$draw->event_id, $draw]) }}">Continue setup</a>@endcan
      @can('team-fixture.schedule', $draw)<a class="btn btn-outline-primary" href="{{ route('backend.team-schedule.page', $draw) }}">Schedule rubbers</a>@endcan
      <a class="btn btn-outline-primary" href="{{ route('backend.team-draw.standings', $draw) }}">Standings</a>
    </div>
  </div>
  @if(isset($draw->team_draw_selection['competition']))
    @php
      $competition = $draw->team_draw_selection['competition'];
      $playoffReadiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
    @endphp
    @include('backend.team-draw.partials.playoff-readiness')
    <div class="card card-body mb-3">
      <h2 class="h5">{{ ($competition['phase'] ?? '') === 'playoff' ? 'Placement playoffs' : 'One round-robin group' }}</h2>
      @if($draw->team_scoring_rules['undecided'] ?? false)
        <p>Scoring: <strong>To be decided.</strong> Scheduling and lineup review are available. Confirm scoring before publishing ties or entering results.</p>
      @else<p>Scoring has been confirmed for this phase.</p>@endif
      @can('team-draw.updateTeamDraw', $draw)
        @if(!$draw->published && !$draw->locked && !$draw->teamTies()->whereNotNull('published_at')->exists())
          <a class="btn btn-outline-primary align-self-start" href="{{ route('team-draw.competition.scoring', $draw) }}">Review and confirm scoring</a>
        @endif
      @endcan
      @if(($competition['phase'] ?? '') === 'round_robin')
        <p class="mt-3">Complete every encounter and resolve tied standings before qualifying teams. Playoff qualification freezes these round-robin results.</p>
        @if(!empty($competition['playoff_draw_id']))
          <a href="{{ route('backend.team-draw.operations', $competition['playoff_draw_id']) }}">Open placement playoffs</a>
        @else
          @can('team-draw.createFormat', $draw->event)
            <form method="POST" action="{{ route('team-draw.competition.playoffs', $draw) }}">@csrf<button class="btn btn-primary" @disabled(!($playoffReadiness['can_create'] ?? false))>Create 1st/2nd and 3rd/4th playoffs</button></form>
          @endcan
        @endif
      @else
        <a href="{{ route('backend.team-draw.standings', $competition['source_draw_id']) }}">Round-robin standings</a>
        <p class="mt-2">Tie 1 decides places 1 and 2. Tie 2 decides places 3 and 4. A level match total remains unresolved and must be reviewed by the organiser.</p>
        @foreach($ties as $placementTie)
          @php($outcome = app(\App\Services\TeamStandingsService::class)->tieOutcome($placementTie))
          <p>{{ $placementTie->tie_nr === 1 ? '1st / 2nd' : '3rd / 4th' }}:
            @if($outcome['complete'] && $outcome['winner_team_id'])
              {{ $outcome['winner_team_id'] === $placementTie->home_team_id ? $placementTie->home_side_name : $placementTie->away_side_name }} wins ({{ $outcome['home'] }}–{{ $outcome['away'] }})
            @else Awaiting a decisive result @endif
          </p>
        @endforeach
      @endif
    </div>
  @endif
  @if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <p><strong>1. Review players → 2. Validate tie → 3. Publish tie.</strong></p>
  <p>Review the players for each rubber, validate the complete tie, then publish it before entering scores. Draw publication controls public visibility separately.</p>
  @if($draw->locked || $draw->published)<div class="alert alert-info">This draw is protected. Tie validation and publication changes are unavailable.</div>@endif
  <div id="team-tie-message" class="d-none" role="status" aria-live="polite"></div>
  <div class="card card-body mb-3"><div class="row g-3">
    <div class="col-md-6"><label for="tie-round-filter" class="form-label">Round</label><select id="tie-round-filter" class="form-select"><option value="">All rounds</option>@foreach($ties->pluck('round_nr')->unique()->sort() as $round)<option value="{{ $round }}">Round {{ $round }}</option>@endforeach</select></div>
    <div class="col-md-6"><label for="tie-status-filter" class="form-label">Tie status</label><select id="tie-status-filter" class="form-select"><option value="">All statuses</option>@foreach($ties->pluck('status')->unique() as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div>
  </div><p class="small mt-2 mb-0" data-tie-filter-count role="status" aria-live="polite"></p></div>
  @forelse($ties->groupBy('round_nr') as $roundNumber => $roundTies)
<section data-tie-round-group><h2 class="h5 mt-4">Round {{ $roundNumber }}</h2>
    @foreach($roundTies as $tie)
      @php($protected = $draw->locked || $draw->published || $tie->isLocked() || $tie->published_at || $tie->winner_team_id)
      <details class="card mb-3" data-tie-card data-round="{{ $roundNumber }}" data-status="{{ $tie->status }}">
        <summary class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2" style="min-height:44px">
          <div><h3 class="h6 mb-1">{{ $tie->home_side_name ?? 'Home' }} vs {{ $tie->away_side_name ?? 'Away' }}</h3>
            <span class="badge bg-label-secondary">{{ ucfirst($tie->status) }}</span>
          </div>
        </summary>
          <div class="d-flex flex-wrap gap-2 p-3">
            @can('validateTie', $tie)
              <button type="button" class="btn btn-sm btn-outline-primary team-tie-action" data-url="{{ route('team-draw.ties.validate', $tie) }}" @disabled($protected || $tie->status === 'validated')>Validate tie</button>
            @endcan
            @can('publishTie', $tie)
              <button type="button" class="btn btn-sm btn-primary team-tie-action" data-url="{{ route('team-draw.ties.publish', $tie) }}" @disabled($protected || $tie->status !== 'validated')>Publish tie</button>
            @endcan
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
      </details>
    @endforeach
    </section>
  @empty<div class="alert alert-info">No team ties have been generated.</div>@endforelse
</div>
@endsection
@section('page-script')
<script>
const tieCards = Array.from(document.querySelectorAll('[data-tie-card]'));
function filterTies() {
  const round = document.getElementById('tie-round-filter').value, status = document.getElementById('tie-status-filter').value;
  let shown = 0;
  tieCards.forEach(card => { card.hidden = Boolean((round && card.dataset.round !== round) || (status && card.dataset.status !== status)); if (!card.hidden) shown++; });
  document.querySelectorAll('[data-tie-round-group]').forEach(group => { group.hidden = !Array.from(group.querySelectorAll('[data-tie-card]')).some(card => !card.hidden); });
  document.querySelector('[data-tie-filter-count]').textContent = `${shown} of ${tieCards.length} ties shown`;
}
document.getElementById('tie-round-filter').addEventListener('change', filterTies);
document.getElementById('tie-status-filter').addEventListener('change', filterTies);
filterTies();
</script>
<script src="{{ asset('js/team-tie-operations.js') }}?v={{ filemtime(public_path('js/team-tie-operations.js')) }}"></script>
@endsection
