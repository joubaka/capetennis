@if($event->announcements->isNotEmpty())
  <section id="event-announcements" class="card event-section-card event-announcements mb-4" data-event-announcements
    data-storage-key="ct:announcements:{{ $event->id }}:{{ auth()->id() ?? 'guest' }}">
    <div class="card-body p-3">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <i class="ti ti-speakerphone event-announcement-icon fs-4" aria-hidden="true"></i>
        <h5 class="mb-0">Announcements</h5>
        <span class="badge ms-auto" data-announcement-status aria-live="polite">New updates</span>
      </div>
      <details data-announcement-reader>
        <summary class="btn" data-announcement-toggle>View {{ $event->announcements->count() }} new {{ $event->announcements->count() === 1 ? 'announcement' : 'announcements' }}</summary>
        <div class="pt-3" data-announcement-list>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-3" data-announcement-controls hidden>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-announcement-all>View all announcements</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-announcement-mark-all>Mark all as read</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-announcement-close>Close</button>
          </div>
          @foreach($event->announcements as $announcement)
            <details class="event-announcement border rounded p-3 mb-2" data-announcement
              data-posted-at="{{ $announcement->created_at?->getTimestampMs() }}"
              data-revision="{{ $announcement->id }}:{{ hash('sha256', $announcement->title.'|'.$announcement->message) }}">
              <summary class="event-announcement-title">
                <span>{{ filled($announcement->title) ? $announcement->title : 'Event update' }}</span>
                <span class="badge bg-label-success" data-announcement-badge>New</span>
              </summary>
              <div class="small text-muted mt-2">{{ optional($announcement->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
              <div class="event-information-content mt-3">{!! $announcement->message !!}</div>
              <button type="button" class="btn btn-success mt-3" data-announcement-viewed hidden>Mark as read &amp; close</button>
            </details>
          @endforeach
          <p class="small text-muted mb-0 mt-3" data-announcement-storage-note hidden>Read status is remembered in this browser.</p>
        </div>
      </details>
      <button type="button" class="btn btn-sm btn-link mt-1 px-0" data-announcement-view-all hidden>View all announcements</button>
    </div>
  </section>
  @once
    <style>
      .event-announcements [hidden] { display: none !important; }
      .event-announcements {
        --announcement-accent: #146c43;
        --announcement-tint: #e6f4ec;
        border-top: 4px solid var(--announcement-accent) !important;
        scroll-margin-top: 6rem;
      }
      .event-announcements[data-recent-unread] {
        --announcement-accent: #9c3f00;
        --announcement-tint: #fff0dc;
        background: var(--announcement-tint);
      }
      .event-announcement-icon { color: var(--announcement-accent); }
      .event-announcements [data-announcement-status] {
        color: var(--announcement-accent);
        background: var(--announcement-tint);
        white-space: normal;
      }
      .event-announcements [data-announcement-toggle] {
        --bs-btn-color: #fff;
        --bs-btn-bg: var(--announcement-accent);
        --bs-btn-border-color: var(--announcement-accent);
        --bs-btn-hover-color: #fff;
        --bs-btn-hover-bg: var(--announcement-accent);
        --bs-btn-hover-border-color: var(--announcement-accent);
        --bs-btn-active-color: #fff;
        --bs-btn-active-bg: var(--announcement-accent);
        --bs-btn-active-border-color: var(--announcement-accent);
      }
      .event-announcements [data-announcement-toggle]:hover { text-decoration: underline; }
      .event-announcements [data-announcement-toggle]:focus-visible {
        outline: 2px solid var(--announcement-accent);
        outline-offset: 2px;
      }
      [data-announcement-menu-badge] { color: #fff; background: #146c43; }
      [data-announcement-menu-badge][data-recent-unread] { background: #9c3f00; }
      .event-announcements summary { cursor: pointer; }
      .event-announcements [data-announcement-toggle] { white-space: normal; min-height: 44px; text-align: left; }
      .event-announcement-title { overflow-wrap: anywhere; font-weight: 600; }
      .event-announcement-title .badge { margin-left: .5rem; }
      .event-announcements .event-information-content { overflow-wrap: anywhere; overflow-x: auto; }
      .event-announcements .event-information-content img { max-width: 100%; height: auto; }
      .event-announcements button { min-height: 44px; }
      @media (max-width: 575.98px) {
        .event-announcements [data-announcement-toggle] { width: 100%; }
        .event-announcements h5 { font-size: 1rem; }
      }
    </style>
    <script src="{{ asset('assets/js/event-announcements.js') }}?v={{ filemtime(public_path('assets/js/event-announcements.js')) }}" defer></script>
  @endonce
@endif
