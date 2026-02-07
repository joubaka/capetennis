@forelse($team_fixture->fixtureResults as $r)
  {{ $r->team1_score }}-{{ $r->team2_score }}@if(!$loop->last), @endif
@empty
  <span class="text-muted">No result</span>
@endforelse

