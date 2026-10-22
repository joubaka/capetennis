@extends('layouts.backend')
@section('title', 'Team standings – '.$draw->drawName)
@section('content')
<div class="container-xxl">
  <h1 class="h4">{{ $draw->drawName }}: team standings</h1>
  @include('backend.team-draw.partials.organiser-decisions')
  @include('frontend.fixtures.partials.live-results-status')
  <div data-live-results="admin-draw-standings">
  @if(app(\App\Services\TeamRoundRobinMatrixService::class)->supports($draw))
    @include('backend.team-draw.partials.round-robin-matrix', ['matrix' => app(\App\Services\TeamRoundRobinMatrixService::class)->forDraw($draw, $rows)])
  @endif
  @if(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff')
    @include('frontend.fixtures.partials.team-placement-results', ['placementSections' => [['draw' => $draw, 'category' => $draw->team_draw_selection['competition']['group'], 'age' => $draw->team_draw_selection['competition']['group'], 'gender' => 'mixed', 'results' => app(\App\Services\TeamPlacementResultsService::class)->forDraw($draw, false)]]])
  @elseif($draw->team_scoring_rules['undecided'] ?? false)
    <div class="alert alert-info">Scoring is to be decided. Confirm scoring before entering results or qualifying teams.</div>
  @else
  <p>Completed rubbers contribute points. Team ties count as played once every rubber is complete. Teams tied on all configured criteria share a rank.</p>
  <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Rank</th><th>Team</th><th>Played</th><th>Won</th><th>Drawn</th><th>Lost</th><th>Points</th><th>Rubbers</th><th>Sets</th><th>Games</th></tr></thead>
    <tbody>@forelse($rows as $row)<tr>
      <td>{{ $row['rank'] }}</td><th>{{ $row['name'] }}</th><td>{{ $row['played'] }}</td><td>{{ $row['wins'] }}</td><td>{{ $row['draws'] }}</td><td>{{ $row['losses'] }}</td><td>{{ $row['points'] }}</td>
      <td>{{ $row['rubber_wins'] }}–{{ $row['rubber_losses'] }}</td><td>{{ $row['sets_for'] }}–{{ $row['sets_against'] }}</td><td>{{ $row['games_for'] }}–{{ $row['games_against'] }}</td>
    </tr>@empty<tr><td colspan="10">No team ties have been generated.</td></tr>@endforelse</tbody>
  </table></div></div>
  @endif
  </div>
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
