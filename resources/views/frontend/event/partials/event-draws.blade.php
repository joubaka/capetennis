{{-- DRAWS & ORDER OF PLAY --}}
@if(($drawPublicationSummary['total'] ?? 0) > 0)
  <div id="event-draws-match-times" class="card event-draws-card mb-4" tabindex="-1">
    <div class="card-header event-draws-header">
      <span class="event-draws-header-icon" aria-hidden="true"><i class="ti ti-tournament"></i></span>
      <div>
        <h5 class="mb-1">Draws and match times</h5>
        <p class="text-muted small mb-0">Open a draw to see the bracket, venue and published match times.</p>
      </div>
    </div>
    <div class="card-body">
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
            $canScoreEvent = auth()->check() && auth()->user()->can('event.score', $event);
          @endphp

          @foreach($sortedDraws as $draw)
            @php
              $firstSchedule = $draw->order_of_play->whereNotNull('time')->sortBy('time')->first();
              $drawVenueNames = $draw->venues->pluck('name')->filter()->unique()->values();
              $publicDrawUrl = $draw->usesFlexibleMonrad()
                ? route('public.flexible-monrad.show', $draw)
                : route('public.roundrobin.show', $draw);
              $canViewDraw = auth()->check() && auth()->user()->can('view', $draw);
            @endphp

            <article class="event-draw-card">
              <div>
                <div class="event-draw-card-heading">
                  <div class="event-draw-name">{{ $draw->drawName ?? 'Draw #'.$draw->id }}</div>
                  @if($draw->published)
                    <span class="event-draw-state">{{ $draw->oop_published ? 'Draw & times live' : 'Draw live' }}</span>
                  @elseif($canViewDraw)
                    <span class="event-draw-state">Draft</span>
                  @endif
                </div>
                <div class="event-draw-meta">
                  @if($drawVenueNames->isNotEmpty())
                    <span><i class="ti ti-map-pin" aria-hidden="true"></i><span>{{ $drawVenueNames->join(', ') }}</span></span>
                  @endif
                  @if($draw->oop_published && $firstSchedule?->time)
                    <span><i class="ti ti-clock" aria-hidden="true"></i><span>First match {{ \Carbon\Carbon::parse($firstSchedule->time)->format('H:i') }}</span></span>
                  @endif
                </div>
              </div>

              <div class="event-draw-actions">
                @if($draw->published)
                  <a href="{{ $publicDrawUrl }}#draw" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-tournament me-1" aria-hidden="true"></i>View draw
                  </a>
                  @if($draw->oop_published)
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
                      <span>Score</span>
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
