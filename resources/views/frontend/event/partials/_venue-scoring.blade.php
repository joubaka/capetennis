@can('event.score', $event)
  @once
    <style>
      .event-venue-scoring { padding: 16px; border: 1px solid #d5e1ef; border-radius: 10px; background: #f5f8fc; color: #173f7a; }
      .event-venue-scoring-intro { margin-bottom: 14px; }
      .event-venue-scoring-intro p { color: #52647a; line-height: 1.5; }
      .event-venue-scoring-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 10px; }
      .event-venue-scoring-link.btn { display: flex; align-items: center; gap: 10px; min-width: 0; min-height: 48px; padding: 12px; border: 1px solid #b9cde5; border-radius: 8px; background: #fff; color: #173f7a; text-align: left; white-space: normal; font-weight: 600; line-height: 1.4; }
      .event-venue-scoring-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
      .event-venue-scoring-link > .ti { flex-shrink: 0; font-size: 1.125rem; }
      .event-venue-scoring-count { flex-shrink: 0; min-width: 32px; padding: 5px 8px; border-radius: 5px; background: #e8eff8; color: #173f7a; text-align: center; font-size: .8125rem; }
      .event-venue-scoring-link.btn:hover, .event-venue-scoring-link.btn:focus-visible { border-color: #173f7a; background: #173f7a; color: #fff; }
      .event-venue-scoring-link.btn:focus-visible { outline: 3px solid #6b9cd3; outline-offset: 3px; }
    </style>
  @endonce
  <section class="event-venue-scoring mb-4" aria-label="Score fixtures by venue">
      <div class="event-venue-scoring-intro">
        <h6 class="mb-1 fw-bold">
          <i class="ti ti-scoreboard me-1" aria-hidden="true"></i> Score fixtures by venue
        </h6>
        <p class="small mb-0">Choose a venue to see its fixture queue and enter or correct scores.</p>
      </div>

      @if(($scoringVenues ?? collect())->isNotEmpty())
        <div class="event-venue-scoring-list">
          @foreach($scoringVenues as $scoringVenue)
            <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => 'published', 'venue' => $scoringVenue->id]) }}"
               class="btn event-venue-scoring-link">
              <i class="ti ti-map-pin" aria-hidden="true"></i>
              <span class="event-venue-scoring-name">{{ $scoringVenue->name }}</span>
              <span class="event-venue-scoring-count">{{ $scoringVenue->fixture_count }}<span class="visually-hidden"> fixtures</span></span>
            </a>
          @endforeach
        </div>
      @else
        <a href="{{ route('frontend.scoring.workspace', $event) }}" class="btn event-venue-scoring-link">
          Open scoring workspace
        </a>
      @endif
  </section>
@endcan
