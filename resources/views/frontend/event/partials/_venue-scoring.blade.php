@can('event.score', $event)
  <section class="alert alert-primary border-0 mb-4" aria-labelledby="venue-scoring-heading">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <h6 id="venue-scoring-heading" class="mb-1">
          <i class="ti ti-scoreboard me-1" aria-hidden="true"></i> Score fixtures by venue
        </h6>
        <p class="small mb-0">Choose a venue to see its fixture queue and enter or correct scores.</p>
      </div>

      @if(($scoringVenues ?? collect())->isNotEmpty())
        <div class="d-flex flex-wrap gap-2">
          @foreach($scoringVenues as $scoringVenue)
            <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $scoringVenue->id]) }}"
               class="btn btn-primary btn-sm">
              <i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ $scoringVenue->name }}
              <span class="badge bg-white text-primary ms-1">{{ $scoringVenue->fixture_count }}</span>
              <span class="visually-hidden"> fixtures</span>
            </a>
          @endforeach
        </div>
      @else
        <a href="{{ route('frontend.scoring.workspace', $event) }}" class="btn btn-outline-primary btn-sm">
          Open scoring workspace
        </a>
      @endif
    </div>
  </section>
@endcan
