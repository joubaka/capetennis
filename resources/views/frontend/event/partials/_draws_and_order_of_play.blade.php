{{-- resources/views/frontend/event/partials/_draws_and_order_of_play.blade.php --}}
<div class="card mb-4">
  <div class="card-header">
    <small class="card-text text-uppercase">Draws and Order of Play</small>
  </div>

  <div class="card-body">
    {{-- ✅ Published Draws --}}
    <div class="mb-3">
      <h6 class="fw-bold">Published Draws</h6>
      @forelse($eventDraws->where('published', true)->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)
        <h6 class="mt-3">{{ $typeName }}</h6>
        <div class="d-flex flex-wrap gap-2">
          @foreach($draws as $draw)
            <a href="{{ route('frontend.fixtures.index', $draw->id) }}"
               class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
              {{ $draw->drawName }}
            </a>
          @endforeach
        </div>
      @empty
        <div class="alert alert-secondary m-0">No draws published yet.</div>
      @endforelse
    </div>

    {{-- 🚧 Unpublished Draws (Admins only) --}}
    @if($eventDraws->where('published', false)->count())
      <div class="mt-4">
        <h6 class="fw-bold text-danger">Unpublished Draws</h6>
        @php $isAdmin = auth()->check() && in_array(auth()->id(), [1764, 584,585]); @endphp

        @foreach($eventDraws->where('published', false)->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)
          <h6 class="mt-3">{{ $typeName }}</h6>
          <div class="d-flex flex-wrap gap-2">
            @foreach($draws as $draw)
              @if($isAdmin)
                <a href="{{ route('frontend.fixtures.index', $draw->id) }}"
                   class="btn btn-sm btn-outline-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
                  {{ $draw->drawName }}
                  <span class="badge bg-danger ms-1">Not published</span>
                </a>
              @else
                <span class="btn btn-sm btn-light disabled">
                  {{ $draw->drawName }}
                  <span class="badge bg-danger ms-1">Not published</span>
                </span>
              @endif
            @endforeach
          </div>
        @endforeach
      </div>
    @endif

    {{-- 📍 Quick Links per Venue (Admins only) --}}
    @auth
      @php $isAdmin = auth()->check() && in_array(auth()->id(), [1764, 584, 585]); @endphp
      @if($isAdmin && !empty($fixturesPerVenueGrouped))
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
                  <a href="{{ route('fixtures.venue', ['event_id' => $event->id, 'venue_id' => $venueId]) }}"
                     class="btn btn-sm btn-outline-primary">
                    {{ $venueName }} Fixtures
                  </a>
                  <a href="{{ route('fixtures.order', ['eventId' => $event->id, 'venueId' => $venueId, 'date' => $firstDate]) }}"
                     class="btn btn-sm btn-outline-success">
                    Order of Play
                  </a>
                </div>
              @endif
            @endforeach
          </div>
        </div>
      @endif
    @endauth
  </div>
</div>
