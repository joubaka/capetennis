{{-- resources/views/frontend/event/partials/_draws_and_order_of_play.blade.php --}}
<div class="card mb-4">
  <div class="card-header">
    <small class="card-text text-uppercase">Draws and Order of Play</small>
  </div>

  <div class="card-body">
    @include('frontend.event.partials._venue-scoring')

    {{-- ✅ Published Draws --}}
<div class="mb-3">
  <h6 class="fw-bold">Published Draws</h6>

  @php
    $publishedDraws = $eventDraws
        ->where('published', true)
        ->sortByDesc('drawType_id'); // 👈 order here
  @endphp

  @forelse(
      $publishedDraws->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other')
      as $typeName => $draws
  )
    <h6 class="mt-3">{{ $typeName }}</h6>

    <div class="d-flex flex-wrap gap-2">
      @foreach($draws as $draw)
        <div class="d-flex align-items-center gap-1">
          <a href="{{ $draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id) }}"
             class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
            {{ $draw->drawName }}
            <span class="badge {{ $draw->oop_published ? 'bg-label-light' : 'bg-label-secondary' }} ms-1">
              {{ $draw->oop_published ? 'Times available' : 'Times to follow' }}
            </span>
          </a>
          @php

            $canScoreEvent = auth()->check() && auth()->user()->can('event.score', $event);
          @endphp
          {{-- debug removed: dd() halts execution. Use @dump($var) or @dd($var) during local debugging --}}
          @if($canScoreEvent)
            <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]) }}"
               class="btn btn-sm btn-light border"
               title="Score {{ $draw->drawName }}">
              <i class="bi bi-clipboard-data"></i> Score
            </a>
          @endif
        </div>
      @endforeach
    </div>
  @empty
    <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> They will appear here when released; match times may follow later.</div>
  @endforelse
    

   {{-- Venues Section --}}
    @if(isset($venues) && $venues->count() && ($drawPublicationSummary['schedule_published'] ?? 0) > 0)
      <div class="mt-4">
        <h6 class="fw-bold mb-2">Published match schedules by venue</h6>

        @php
          // Calculate convenor/admin permission once for this view
          $user = auth()->user();
        @endphp

        <div class="d-flex flex-wrap gap-2">
          @foreach($venues as $venue)
            <div class="d-flex align-items-center gap-2">
              <a href="{{ route('fixtures.venue', ['event_id' => $event->id, 'venue_id' => $venue->id]) }}"
                 class="btn btn-outline-primary btn-sm">
                {{ $venue->name }}
              </a>
            </div>
          @endforeach
        </div>
      </div>
    @endif



</div>

    {{-- 🚧 Unpublished Draws (Admin / Super-user / Convenor only) --}}
    @if($eventDraws->where('published', false)->count())
      @php
        $user = auth()->user();
        $canViewUnpublished = $user && (
          (method_exists($user, 'isConvenorForEvent') && $user->isConvenorForEvent($event->id)) ||
          (method_exists($user, 'is_convenor') && $user->is_convenor($event->id)) ||
          (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super-user')))
        );
      @endphp

      @if($canViewUnpublished)
        <div class="mt-4">
          <h6 class="fw-bold text-danger">Unpublished Draws</h6>

          @foreach($eventDraws->where('published', false)->sortByDesc('drawType_id')->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)
            <h6 class="mt-3">{{ $typeName }}</h6>
            <div class="d-flex flex-wrap gap-2">
              @foreach($draws as $draw)
                <a href="{{ $draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('frontend.fixtures.index', $draw->id) }}"
                   class="btn btn-sm btn-outline-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
                  {{ $draw->drawName }}
                  <span class="badge bg-danger ms-1">Unpublished</span>
                  @if($draw->oop_published)<span class="badge bg-info ms-1">Times preview</span>@endif
                </a>
              @endforeach
            </div>
          @endforeach
        </div>
      @endif
    @endif

 

 


   
  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
