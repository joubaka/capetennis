      {{-- 🔹 Draws and Order of Play (Mobile / Tablet only) --}}
      <div class="card d-block d-md-none mb-4">
        <div class="card-body">

          @php
            $mobileUser = auth()->user();
            $canViewUnpublished = $mobileUser && (
              (method_exists($mobileUser, 'isConvenorForEvent') && $mobileUser->isConvenorForEvent($event->id)) ||
              (method_exists($mobileUser, 'is_convenor') && $mobileUser->is_convenor($event->id)) ||
              (method_exists($mobileUser, 'hasRole') && ($mobileUser->hasRole('admin') || $mobileUser->hasRole('super-user')))
            );
          @endphp

          {{-- PUBLISHED DRAW LINKS (PUBLIC) --}}
          <h6 class="fw-bold mb-2">Published draws</h6>

          @forelse($eventDraws->where('published', true)
              ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

            <div class="d-flex flex-column gap-2 mt-1">
              @foreach($draws as $draw)
                <a href="{{ route('public.roundrobin.show', $draw->id) }}"
                   class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'primary' }} w-100">
                  {{ $draw->drawName }}
                  <span class="badge {{ $draw->oop_published ? 'bg-label-light' : 'bg-label-secondary' }} ms-1">
                    {{ $draw->oop_published ? 'Times available' : 'Times to follow' }}
                  </span>
                </a>
              @endforeach
            </div>

          @empty
            <div class="alert alert-info"><strong>Draws are being finalised.</strong> Match times may be published separately.</div>
          @endforelse

          {{-- 🔹 ADMIN DRAW LIST (SEPARATE) --}}
          @if($canViewUnpublished)
            @include('frontend.event.partials.interpro-admin-drawlist')
          @endif

          {{-- UNPUBLISHED DRAW LINKS (Admin / Super-user / Convenor only) --}}

          @if($canViewUnpublished && $eventDraws->where('published', false)->count())
            <h6 class="fw-bold text-danger mt-4">Unpublished Draws</h6>

            @foreach($eventDraws->where('published', false)
                ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

              <div class="fw-bold mt-2">{{ $typeName }}</div>

              <div class="d-flex flex-column gap-2 mt-1">
                @foreach($draws as $draw)
                  <a href="{{ route('public.roundrobin.show', $draw->id) }}"
                     class="btn btn-sm btn-outline-{{ $draw->draw_types?->btn_color ?? 'secondary' }} w-100">
                    {{ $draw->drawName }}
                    <span class="badge bg-danger ms-1">Not published</span>
                  </a>
                @endforeach
              </div>

            @endforeach
          @endif

          {{-- QUICK LINKS PER VENUE (Admin / Super-user / Convenor only) --}}
          @if($canViewUnpublished && !empty($fixturesPerVenueGrouped))
            <h6 class="fw-bold mt-4 mb-2">Quick Links per Venue</h6>

            <div class="d-flex flex-column gap-2">

              @foreach($fixturesPerVenueGrouped as $venueName => $fixtures)
                @php
                  $venueId   = optional($fixtures->first()->venue)->id;
                  $firstDate = optional($fixtures->first()->scheduled_at)?->toDateString();
                @endphp

                @if($venueId && $firstDate)

                  <a href="{{ route('fixtures.venue', [
                      'event_id' => $event->id,
                      'venue_id' => $venueId
                  ]) }}"
                     class="btn btn-sm btn-outline-primary w-100">
                    {{ $venueName }} Fixtures
                  </a>

                  <a href="{{ route('fixtures.order', [
                      'eventId' => $event->id,
                      'venueId' => $venueId,
                      'date'    => $firstDate
                  ]) }}"
                     class="btn btn-sm btn-outline-success w-100">
                    Order of Play – {{ $venueName }}
                  </a>

                @endif
              @endforeach

            </div>
          @endif

        </div>
      </div>
