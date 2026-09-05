 {{-- 🔹 Draws and Order of Play (Desktop only) --}}
      <div class="d-none d-md-block">

        <div class="card mb-4">
          <div class="card-header">
            <small class="card-text text-uppercase">Draws and Order of Play</small>
          </div>

          <div class="card-body">

            @php
              $deskUser = auth()->user();
              $canScoreEvent = $deskUser && $deskUser->can('event.score', $event);
              $canViewUnpublished = $deskUser && (
                (method_exists($deskUser, 'isConvenorForEvent') && $deskUser->isConvenorForEvent($event->id)) ||
                (method_exists($deskUser, 'is_convenor') && $deskUser->is_convenor($event->id)) ||
                (method_exists($deskUser, 'hasRole') && ($deskUser->hasRole('admin') || $deskUser->hasRole('super-user')))
              );
            @endphp

            {{-- PUBLISHED DRAW LINKS --}}
            <div class="mb-3">
              <h6 class="fw-bold">Published draws</h6>

              @forelse($eventDraws->where('published', true)
                  ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

                <div class="d-flex flex-wrap gap-2 mt-1">
                  @foreach($draws as $draw)
                    <div class="d-flex align-items-center gap-1">
                      <a href="{{ route('public.roundrobin.show', $draw->id) }}"
                         class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
                        {{ $draw->drawName }}
                        <span class="badge {{ $draw->oop_published ? 'bg-label-light' : 'bg-label-secondary' }} ms-1">
                          {{ $draw->oop_published ? 'Times available' : 'Times to follow' }}
                        </span>
                      </a>
                      @if($canScoreEvent)
                        <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]) }}"
                           class="btn btn-sm btn-light border"
                           title="Score {{ $draw->drawName }}">
                          <i class="ti ti-scoreboard me-1" aria-hidden="true"></i>Score
                          <span class="visually-hidden"> {{ $draw->drawName }}</span>
                        </a>
                      @endif
                    </div>
                  @endforeach
                </div>

              @empty
                <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> Match times may be published separately.</div>
              @endforelse

            </div>

            {{-- 🔹 ADMIN DRAW LIST (SEPARATE) --}}
            @if($canViewUnpublished)
              @include('frontend.event.partials.interpro-admin-drawlist')
            @endif

            {{-- UNPUBLISHED DRAWS (Admin / Super-user / Convenor only) --}}
            @if($canViewUnpublished && $eventDraws->where('published', false)->count())
              <div class="mt-4">
                <h6 class="fw-bold text-danger">Unpublished Draws</h6>

                @foreach($eventDraws->where('published', false)
                    ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

                  <h6 class="mt-3">{{ $typeName }}</h6>

                  <div class="d-flex flex-wrap gap-2">
                    @foreach($draws as $draw)
                      <a href="{{ route('public.roundrobin.show', $draw->id) }}"
                         class="btn btn-sm btn-outline-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
                        {{ $draw->drawName }}
                        <span class="badge bg-danger ms-1">Unpublished</span>
                      </a>
                    @endforeach
                  </div>

                @endforeach

              </div>
            @endif

            {{-- QUICK LINKS PER VENUE (Admin / Super-user / Convenor only) --}}
            @if($canViewUnpublished && !empty($fixturesPerVenueGrouped))
              <div class="mt-4">
                <h6 class="fw-bold mb-2">Quick Links per Venue</h6>

                <div class="d-flex flex-column gap-2">

                  @foreach($fixturesPerVenueGrouped as $venueName => $fixtures)
                    @php
                      $venueId = optional($fixtures->first()->venue)->id;
                      $firstDate = optional($fixtures->first()->scheduled_at)?->toDateString();
                    @endphp

                    @if($venueId && $firstDate)
                      <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('fixtures.venue', [
                            'event_id' => $event->id,
                            'venue_id' => $venueId
                        ]) }}"
                           class="btn btn-sm btn-outline-primary">
                          {{ $venueName }} Fixtures
                        </a>

                        <a href="{{ route('fixtures.order', [
                            'eventId' => $event->id,
                            'venueId' => $venueId,
                            'date'    => $firstDate
                        ]) }}"
                           class="btn btn-sm btn-outline-success">
                          Order of Play
                        </a>
                      </div>
                    @endif
                  @endforeach

                </div>

              </div>
            @endif

          </div>
        </div>

      </div>
