{{-- resources/views/frontend/event/partials/_draws_and_order_of_play.blade.php --}}
<div class="card mb-4">
  <div class="card-header">
    <small class="card-text text-uppercase">Draws and Order of Play</small>
  </div>

  <div class="card-body">
    {{-- ✅ Published Draws --}}
<div class="mb-3">
  <h6 class="fw-bold">Published Draws</h6>

  @php
    $publishedDraws = $eventDraws
        ->where('published', true)
        ->sortBy('drawType_id'); // 👈 order here
  @endphp

  @forelse(
      $publishedDraws->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other')
      as $typeName => $draws
  )
    <h6 class="mt-3">{{ $typeName }}</h6>

    <div class="d-flex flex-wrap gap-2">
      @foreach($draws as $draw)
        <div class="d-flex align-items-center gap-1">
          <a href="{{ route('frontend.fixtures.index', $draw->id) }}"
             class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
            {{ $draw->drawName }}
          </a>
          @php

            $isConvenorOrSuper = auth()->check() && ( (method_exists(auth()->user(), 'isConvenorForEvent') && auth()->user()->isConvenorForEvent($event->id)) || (method_exists(auth()->user(), 'hasRole') && (auth()->user()->hasRole('convenor') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-user'))) );
          @endphp
          {{-- debug removed: dd() halts execution. Use @dump($var) or @dd($var) during local debugging --}}
          @if($isConvenorOrSuper)
            <a href="{{ route('frontend.fixtures.enter-scores', ['draw' => $draw->id]) }}"
               class="btn btn-sm btn-light border"
               title="Insert Score">
              <i class="bi bi-clipboard-data"></i>
            </a>
          @endif
        </div>
      @endforeach
    </div>
  @empty
    <div class="alert alert-secondary m-0">No draws published yet.</div>
  @endforelse
    

   {{-- Venues Section --}}
    @if(isset($venues) && $venues->count())
      <div class="mt-4">
        <h6 class="fw-bold mb-2">Per Venue fixtures</h6>

        @php
          // Calculate convenor/admin permission once for this view
          $user = auth()->user();
          $isConvenorOrSuper = auth()->check() && ( (method_exists($user, 'isConvenorForEvent') && $user->isConvenorForEvent($event->id)) || (method_exists($user, 'hasRole') && ($user->hasRole('convenor') || $user->hasRole('admin') || $user->hasRole('super-user'))) );
        @endphp

        <div class="d-flex flex-wrap gap-2">
          @foreach($venues as $venue)
            <div class="d-flex align-items-center gap-2">
              <a href="{{ route('fixtures.venue', ['event_id' => $event->id, 'venue_id' => $venue->id]) }}"
                 class="btn btn-outline-primary btn-sm">
                {{ $venue->name }}
              </a>

              {{-- Convenor / Admin: quick Enter Scores (per-venue convenor view) --}}
              @if($isConvenorOrSuper)
                {{-- Convenor enter-scores page for this venue, filtered by event and venue --}}
                <a href="{{ route('frontend.fixtures.enter-scores.venue', ['event' => $event->id, 'venue' => $venue->id]) }}"
                   class="btn btn-sm btn-light border"
                   title="Enter scores for {{ $venue->name }}">
                  <i class="bi bi-clipboard-data"></i>
                </a>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endif



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

 

 


   
  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
