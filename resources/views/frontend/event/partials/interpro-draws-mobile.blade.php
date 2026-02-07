      {{-- 🔹 Draws and Order of Play (Mobile / Tablet only) --}}
      <div class="card d-block d-md-none mb-4">
        <div class="card-body">

          {{-- PUBLISHED DRAW LINKS (PUBLIC) --}}
          <h6 class="fw-bold mb-2">Published Draws Mobile</h6>

          @forelse($eventDraws->where('published', true)
              ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

         

            <div class="d-flex flex-column gap-2 mt-1">
              @foreach($draws as $draw)
                <a href="{{ route('public.roundrobin.show', $draw->id) }}"
                   class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'primary' }} w-100">
                  {{ $draw->drawName }}
                </a>
              @endforeach
            </div>

          @empty
            <div class="alert alert-secondary">No published draws yet.</div>
          @endforelse

          {{-- 🔹 ADMIN DRAW LIST (SEPARATE) --}}
        
@if($isAdmin )
 @include('frontend.event.partials.interpro-admin-drawlist')
@endif


          {{-- UNPUBLISHED DRAW LINKS (ADMIN ONLY) --}}
          @php
            $isAdmin = auth()->check() && in_array(auth()->id(), [1764, 584, 585]);
          @endphp

          @if($isAdmin && $eventDraws->where('published', false)->count())
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

          {{-- QUICK LINKS PER VENUE (ADMIN ONLY) --}}
          @if($isAdmin && !empty($fixturesPerVenueGrouped))
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
