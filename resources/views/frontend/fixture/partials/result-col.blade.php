{{-- resources/views/frontend/fixture/partials/result-col.blade.php --}}

@forelse($fixture->fixtureResults as $r)
  <span class="badge bg-label-info me-1">
    {{ $r->team1_score }} - {{ $r->team2_score }}
  </span>
@empty
  <span class="text-muted">No score</span>
@endforelse
