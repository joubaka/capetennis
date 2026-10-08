@extends('layouts.backend')
@section('title', 'Team ties – '.$draw->drawName)
@section('content')
<style>[data-tie-card][hidden],[data-tie-round-group][hidden]{display:none!important;}</style>
<div class="container-xxl">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">{{ $draw->drawName }}: team ties</h1>
    <div class="d-flex flex-wrap gap-2">
      @can('team-fixture.schedule', $draw)<a class="btn btn-outline-primary" href="{{ route('backend.team-schedule.page', $draw) }}">Schedule rubbers</a>@endcan
      <a class="btn btn-outline-primary" href="{{ route('backend.team-draw.standings', $draw) }}">Standings</a>
    </div>
  </div>
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
