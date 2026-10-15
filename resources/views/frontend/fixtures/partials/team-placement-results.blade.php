@foreach(($placementSections ?? []) as $placementSection)
  <section class="card mb-4" data-placement-results="{{ $placementSection['draw']->id }}">
    <div class="card-header"><h2 class="h5 mb-1">{{ $placementSection['draw']->drawName }}</h2><p class="mb-0">Playoff results determine final places. These matches do not add points to the round-robin standings.</p></div>
    <div class="card-body">
      @forelse($placementSection['results'] as $placement)
        <div class="mb-3" style="overflow-wrap:anywhere">
          <h3 class="h6">{{ $placement['label'] }} playoff</h3>
          <p class="mb-1">{{ $placement['home_name'] }} {{ $placement['home_wins'] }}–{{ $placement['away_wins'] }} {{ $placement['away_name'] }}</p>
          @if($placement['winner_name'])
            <p class="mb-0"><strong>{{ $placement['position'] }}:</strong> {{ $placement['winner_name'] }}<br><strong>{{ $placement['position'] + 1 }}:</strong> {{ $placement['loser_name'] }}</p>
          @elseif($placement['complete'])
            <p class="mb-0 text-muted">The encounter is tied. Final places await the agreed tie-break.</p>
          @else
            <p class="mb-0 text-muted">Final places await completed matches.</p>
          @endif
        </div>
      @empty
        <p class="mb-0 text-muted">No placement results are available.</p>
      @endforelse
    </div>
  </section>
@endforeach
