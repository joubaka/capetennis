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
        <a class="btn draws-button draws-button-secondary" href="{{ route('backend.event-venue-schedule.index', $event) }}"><i class="ti ti-calendar-event me-1"></i> Venue schedule</a>
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
