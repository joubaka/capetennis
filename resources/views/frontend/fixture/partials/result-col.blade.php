{{-- resources/views/frontend/fixture/partials/result-col.blade.php --}}

@forelse($fixture->fixtureResults as $r)
  <span class="badge bg-label-info me-1">
    {{ $r->registration1_score ?? $r->team1_score }} - {{ $r->registration2_score ?? $r->team2_score }}
  </span>
@empty
  <span class="text-muted">{{ $fixture instanceof \App\Models\Fixture && $fixture->isWalkover() ? 'W/O' : 'No score' }}</span>
@endforelse
@if($fixture instanceof \App\Models\Fixture && $fixture->isRetired()) <span class="badge bg-label-warning">RET</span> @endif
