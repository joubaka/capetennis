@extends('layouts/layoutMaster')
@section('title', 'Team standings – '.$draw->drawName)
@section('content')
<div class="container-xxl">
  <h1 class="h4">{{ $draw->drawName }}: team standings</h1>
  <div class="d-flex flex-wrap gap-2 mb-3">
    @if($draw->event->standings_published)<a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.events.standings', $draw->event) }}">Full event standings</a>@endif
    <a class="btn btn-outline-secondary" style="min-height:44px" href="{{ route('frontend.fixtures.index', $draw) }}">View match results</a>
  </div>
  @include('frontend.fixtures.partials.live-results-status')
  <div data-live-results="draw-standings">
  @if(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff')
    @include('frontend.fixtures.partials.team-placement-results', ['placementSections' => [['draw' => $draw, 'category' => $draw->team_draw_selection['competition']['group'], 'age' => $draw->team_draw_selection['competition']['group'], 'gender' => 'mixed', 'results' => app(\App\Services\TeamPlacementResultsService::class)->forDraw($draw, true)]]])
  @elseif($draw->team_scoring_rules['undecided'] ?? false)
    <div class="alert alert-info">Scoring is to be decided. Confirm scoring before entering results or qualifying teams.</div>
  @else
  <p>Completed matches contribute points, match wins, sets and games. Team ties count as played once every required match is complete. Teams tied on all configured criteria share a rank.</p>
  <div class="card"><div class="table-responsive" tabindex="0" role="region" aria-label="Standings table; scroll sideways for all statistics"><table class="table align-middle mb-0 text-nowrap">
    <thead><tr><th>Rank</th><th>Team / region</th><th>Ties played</th><th>Ties won</th><th>Ties drawn</th><th>Ties lost</th><th>Match points</th><th>Tie points</th><th>Total points</th><th>Matches W–L</th><th>Sets F–A</th><th>Games F–A</th></tr></thead>
    <tbody>@forelse($rows as $row)<tr>
      <td>{{ $row['rank'] }}</td><th>{{ $row['name'] }}</th><td>{{ $row['played'] }}</td><td>{{ $row['wins'] }}</td><td>{{ $row['draws'] }}</td><td>{{ $row['losses'] }}</td><td data-match-points>{{ $row['match_points'] }}</td><td data-tie-points>{{ $row['tie_points'] }}</td><td>{{ $row['points'] }}</td>
      <td>{{ $row['rubber_wins'] }}–{{ $row['rubber_losses'] }}</td><td>{{ $row['sets_for'] }}–{{ $row['sets_against'] }}</td><td>{{ $row['games_for'] }}–{{ $row['games_against'] }}</td>
    </tr>@empty<tr><td colspan="12">No published team ties or legacy match results are available.</td></tr>@endforelse</tbody>
  </table></div></div>
  <p class="text-muted mt-2">Legacy matches count towards match wins and scores, but do not count as completed team ties.</p>
  @endif
  </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
