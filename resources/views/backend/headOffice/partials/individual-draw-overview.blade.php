<div class="draws-workspace">
  <header class="draws-event-header">
    <div class="draws-event-identity">
      <span class="draws-event-mark">@include('backend.headOffice.partials.draw-icon', ['icon' => 'bracket'])</span>
      <div>
        <p class="draws-eyebrow">Tournament workspace <span>Individual event</span></p>
        <h1>{{ $event->name }}</h1>
      </div>
    </div>
    <div class="draws-event-actions">
      @if($event->draws->isNotEmpty())
        <a class="btn draws-button draws-button-secondary" href="{{ route('backend.event-venue-schedule.index', $event) }}"><i class="ti ti-calendar-event me-1"></i> Schedule all matches</a>
        <button type="button" class="btn draws-button draws-button-secondary" data-bs-toggle="modal" data-bs-target="#printAllDrawsModal">@include('backend.headOffice.partials.draw-icon', ['icon' => 'print']) Print draws</button>
      @endif
      <button type="button" class="btn draws-button draws-button-primary" data-bs-toggle="modal" data-bs-target="#createDrawModal">@include('backend.headOffice.partials.draw-icon', ['icon' => 'plus']) New draw</button>
    </div>
  </header>

  @include('backend.event.partials.workspace-nav', ['eventWorkspaceActive' => 'draws'])

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  @if($event->draws->isNotEmpty())
    @php
      $drawCount = $event->draws->count();
      $generatedCount = $event->draws->where('draw_fixtures_count', '>', 0)->count();
      $publishedCount = $event->draws->where('published', true)->count();
      $scheduledCount = $event->draws->where('order_of_play_count', '>', 0)->count();
      $schedulePublishedCount = $event->draws->where('oop_published', true)->count();
    @endphp
    <section class="card mb-3" aria-labelledby="release-readiness-heading">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <h2 id="release-readiness-heading" class="h5 mb-1">Parent release readiness</h2>
            <p class="text-muted small mb-0">Publishing a draw reveals players and structure. Publishing its schedule separately reveals match times and venues. Neither step happens automatically.</p>
          </div>
          @if($event->published)
            <a class="btn btn-sm btn-outline-primary" href="{{ route('events.show', $event) }}" target="_blank" rel="noopener">
              Preview parent page <i class="ti ti-external-link ms-1" aria-hidden="true"></i>
            </a>
          @else
            <span class="badge bg-label-warning">Event page is not published</span>
          @endif
        </div>
        <div class="row g-2 mt-2">
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Draws generated</small><strong>{{ $generatedCount }}/{{ $drawCount }}</strong></div></div>
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Draws published</small><strong>{{ $publishedCount }}/{{ $drawCount }}</strong></div></div>
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Schedules prepared</small><strong>{{ $scheduledCount }}/{{ $drawCount }}</strong></div></div>
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Schedules published</small><strong>{{ $schedulePublishedCount }}/{{ $drawCount }}</strong></div></div>
        </div>
        @if($scheduledCount > $schedulePublishedCount)
          <div class="alert alert-warning mt-3 mb-0" role="status">
            <strong>{{ $scheduledCount - $schedulePublishedCount }} {{ Str::plural('schedule', $scheduledCount - $schedulePublishedCount) }} prepared but not public.</strong>
            Use <em>Publish times</em> to preview time, venue and court details before releasing the draw publicly.
          </div>
        @endif
      </div>
    </section>
  @endif

  <section class="card draws-overview" aria-labelledby="draws-heading">
    <div class="draws-toolbar">
      <div>
        <h2 id="draws-heading">Tournament draws <span class="draws-total">{{ $event->draws->count() }}</span></h2>
        <p>Player draws, match schedules and publication—all in one place.</p>
      </div>
      @if($event->draws->isNotEmpty())
        <div class="draws-filters">
          <label class="visually-hidden" for="draw-search">Search draws</label>
          <div class="draws-search">@include('backend.headOffice.partials.draw-icon', ['icon' => 'search'])<input id="draw-search" type="search" class="form-control" placeholder="Search draws or formats…" autocomplete="off"></div>
        </div>
      @endif
    </div>
    @if($event->draws->isNotEmpty())
      <div class="draws-list-controls">
        <div class="draws-status-filters" role="group" aria-label="Filter draws by publication status">
          <input type="hidden" id="draw-status" value="">
          <button type="button" data-draw-filter="" aria-pressed="true">All draws <span>{{ $event->draws->count() }}</span></button>
          <button type="button" data-draw-filter="0" aria-pressed="false"><span class="draws-status-dot is-draft" aria-hidden="true"></span>Draft <span>{{ $event->draws->where('published', false)->count() }}</span></button>
          <button type="button" data-draw-filter="1" aria-pressed="false"><span class="draws-status-dot is-published" aria-hidden="true"></span>Published <span>{{ $event->draws->where('published', true)->count() }}</span></button>
        </div>
        <p id="draw-filter-count" role="status" aria-live="polite">Showing {{ $event->draws->count() }} draws</p>
      </div>
      <div class="draws-bulk-actions" aria-label="Actions for selected draws">
        <label class="draws-select-all" for="draw-select-all">
          <input class="form-check-input" id="draw-select-all" type="checkbox">
          <span>Select all visible</span>
        </label>
        <span id="draw-selection-count" class="draws-selection-count" role="status" aria-live="polite">0 selected</span>
        <div class="draws-bulk-buttons">
          <button type="button" class="btn draws-button draws-button-secondary" id="schedule-selected" disabled>
            @include('backend.headOffice.partials.draw-icon', ['icon' => 'calendar']) Schedule selected
          </button>
          @if($event->draws->contains(fn($draw) => Gate::allows('publish', $draw)))
            <button type="button" class="btn draws-button draws-button-primary" id="publish-selected-draws" disabled>
              <i class="ti ti-eye" aria-hidden="true"></i> Publish draws
            </button>
            <button type="button" class="btn draws-button draws-button-secondary" id="publish-selected-times" disabled>
              <i class="ti ti-clock" aria-hidden="true"></i> Publish times
            </button>
          @endif
        </div>
      </div>
      <div class="draws-column-headings" aria-hidden="true"><span>Division</span><span>Draw format</span><span>Matches</span><span>Venue</span><span class="draws-actions-label">Manage draw</span></div>
    @endif
    @forelse($event->draws as $draw)
      @include('backend.headOffice.partials.individual-draw-row')
    @empty
      <div class="text-center p-5">
        <h3 class="h5">Create your first draw</h3>
        <p class="text-muted mb-0">Use New draw to name a division, then choose its format and players.</p>
      </div>
    @endforelse
    @if($event->draws->isNotEmpty())
      <div id="draw-no-results" class="text-center p-4" hidden>
        <p class="mb-2">No draws match these filters.</p>
        <button id="draw-clear-filters" type="button" class="btn btn-sm btn-outline-secondary">Clear filters</button>
      </div>
    @endif
  </section>
</div>
