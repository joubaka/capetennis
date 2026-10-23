@extends('layouts/layoutMaster')
@section('title', $event->name.' – '.$age.' standings')
@section('content')
<div class="container py-4">
  <h1 class="h4">{{ $event->name }} — {{ $age }} standings</h1>
  @if($standings === null)
    <p role="status">Standings will be available after the last match in this age group has been processed.</p>
    <a class="btn btn-outline-primary" style="min-height:44px" href="{{ request()->url() }}">Check again</a>
  @else
    @if($standings['mixedRules'])
      <p>Draws use different scoring rules. Totals are shown without a combined ranking.</p>
    @endif
    <div class="table-responsive">
      <table class="table"><thead><tr><th>Rank</th><th>Region / team</th><th>Played</th><th>Wins</th><th>Match points</th><th>Tie points</th><th>Total points</th></tr></thead><tbody>
      @forelse($standings['rows'] as $row)
        <tr><td>{{ $row['rank'] ?? '—' }}</td><td>{{ $row['name'] }}</td><td>{{ $row['played'] }}</td><td>{{ $row['wins'] }}</td><td data-match-points>{{ $row['match_points'] }}</td><td data-tie-points>{{ $row['tie_points'] }}</td><td>{{ $row['points'] }}</td></tr>
      @empty
        <tr><td colspan="7">No standings available for this age group.</td></tr>
      @endforelse
      </tbody></table>
    </div>
  @endif
</div>
@endsection
