{{-- DRAWS & ORDER OF PLAY --}}
@if(($drawPublicationSummary['total'] ?? 0) > 0)
  <div id="event-draws-match-times" class="card event-draws-card mb-4" tabindex="-1" data-event-draw-section aria-label="Draws and match times">
    <div class="card-header event-draws-header">
      <span class="event-draws-header-icon" aria-hidden="true"><i class="ti ti-tournament"></i></span>
      <div>
        <h5 class="mb-1">Draws and match times</h5>
        <p class="text-muted small mb-0">Choose your draw, then find your name in the schedule for your match date, time and venue.</p>
      </div>
    </div>
    <div class="card-body">
      @include('frontend.event.partials._venue-scoring')

      @if($eventDraws->isEmpty())
        <div class="alert alert-info mb-0" role="status">
          <div class="fw-semibold">The draws are being finalised.</div>
          <div class="small">They will appear here as soon as the organiser publishes them. Match times and venues may follow later.</div>
        </div>
      @else
        <div class="event-draw-grid">
          @php
            $sortedDraws = $eventDraws->sortBy([
              ['published', 'desc'],
              [fn($d) => $d->draw_types?->ageCategory ?? $d->drawName ?? '', 'asc'],
              ['drawName', 'asc'],
            ]);
            $canScoreEvent = $canScoreEvent ?? (auth()->check() && auth()->user()->can('event.score', $event));
          @endphp

          @foreach($sortedDraws as $draw)
            @php
              $drawSummary = app(\App\Services\PublicDrawPresentation::class)->summary($draw, $scheduleRows ?? null);
              $drawVenueNames = $drawSummary['venues'];
              $publicDrawUrl = $draw->usesFlexibleMonrad()
                ? route('public.flexible-monrad.show', $draw)
                : route('public.roundrobin.show', $draw);
              $canViewDraw = isset($canViewDrawById) ? $canViewDrawById->get($draw->id, false) : (auth()->check() && auth()->user()->can('view', $draw));
            @endphp

            <article class="event-draw-card">
              <div>
                <div class="event-draw-card-heading">
                  <div class="event-draw-name">{{ $draw->drawName ?? 'Draw #'.$draw->id }}</div>
                  @if($draw->published)
                    <span class="event-draw-state">{{ $drawSummary['available'] ? 'Draw & times live' : 'Draw live' }}</span>
                  @elseif($canViewDraw)
                    <span class="event-draw-state">Draft</span>
                  @endif
                </div>
                <div class="event-draw-meta">
                  @if($drawVenueNames->isNotEmpty())
                    <span><i class="ti ti-map-pin" aria-hidden="true"></i><span>{{ $drawSummary['provisional_venues'] ? 'Planned venue: ' : '' }}{{ $drawVenueNames->join(', ') }}</span></span>
                  @endif
                  @if($drawSummary['available'] && $drawSummary['first_time'])
                    <span><i class="ti ti-clock" aria-hidden="true"></i><span>Published matches from {{ \Carbon\Carbon::parse($drawSummary['first_time'])->format('D j M Y, H:i') }}<small class="d-block">Check the schedule for your own match time.</small></span></span>
                  @endif
                </div>
              </div>

              <div class="event-draw-actions">
                @if($draw->published)
                  <a href="{{ $publicDrawUrl }}#draw" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-tournament me-1" aria-hidden="true"></i>View draw
                  </a>
                  @if($drawSummary['available'])
                    <a href="{{ $publicDrawUrl }}#schedule" class="btn btn-sm btn-success">
                      <i class="ti ti-clock me-1" aria-hidden="true"></i>View schedule
                    </a>
                  @else
                    <span class="badge bg-label-secondary align-self-center">Times to follow</span>
                  @endif

                  @if($canScoreEvent)
                    <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]) }}"
                       class="btn btn-sm btn-light border event-draw-score"
                       title="Score {{ $draw->drawName }}">
                      <span>Enter scores per draw</span>
                      <span class="visually-hidden"> {{ $draw->drawName }}</span>
                    </a>
                  @endif
                @elseif($canViewDraw)
                  <a href="{{ $publicDrawUrl }}#draw" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-tournament me-1" aria-hidden="true"></i>Preview draw
                  </a>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </div>
  </div>
@endif
