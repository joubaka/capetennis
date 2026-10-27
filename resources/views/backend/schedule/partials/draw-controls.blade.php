@php
  $navigationDraws = $event->draws->sort(fn ($left, $right) => strnatcasecmp($left->drawName, $right->drawName) ?: $left->id <=> $right->id)->values();
  $selectedNavigationDraws = $guideSelection->where('event_id', $event->id)->values();
@endphp
@if($selectedNavigationDraws->isNotEmpty())
  <section class="card mb-3" aria-label="Draw navigation and publication">
    <div class="card-body d-grid gap-3">
      @foreach($selectedNavigationDraws as $publicationDraw)
        @php
          $navigationIndex = $navigationDraws->search(fn ($item) => $item->id === $publicationDraw->id);
          $navigationPrevious = $navigationIndex > 0 ? $navigationDraws[$navigationIndex - 1] : null;
          $navigationNext = $navigationIndex !== false && $navigationIndex < $navigationDraws->count() - 1 ? $navigationDraws[$navigationIndex + 1] : null;
          $navigationQuery = !empty($planningDate) ? ['date' => $planningDate] : [];
        @endphp
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div><h2 class="h6 mb-1">{{ $publicationDraw->drawName }}</h2><span class="small text-muted">Draw {{ $navigationIndex + 1 }} of {{ $navigationDraws->count() }}</span></div>
          <nav class="d-flex flex-wrap gap-2" aria-label="{{ $publicationDraw->drawName }} navigation">
            @if($navigationPrevious)<a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$navigationPrevious->id]] + $navigationQuery) }}" title="{{ $navigationPrevious->drawName }}">Previous draw</a>@else<span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">Previous draw</span>@endif
            <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.draw.roundrobin.show', $publicationDraw) }}#schedule">Open draw</a>
            @if($navigationNext)<a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$navigationNext->id]] + $navigationQuery) }}" title="{{ $navigationNext->drawName }}">Next draw</a>@else<span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">Next draw</span>@endif
          </nav>
        </div>
        @include('backend.draw.partials.publication-controls', ['publicationDraw' => $publicationDraw, 'publicationSurface' => 'standalone'])
      @endforeach
      <p class="small text-muted mb-0">Only saved match times can be published. Unsaved suggestions remain private.</p>
    </div>
  </section>
  @once
    <script src="{{ asset('js/draw-workspace-navigation.js') }}?v={{ filemtime(public_path('js/draw-workspace-navigation.js')) }}" defer></script>
  @endonce
@endif
