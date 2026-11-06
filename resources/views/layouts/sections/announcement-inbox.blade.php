@once
  <div class="modal fade" id="announcement-inbox" tabindex="-1" aria-labelledby="announcement-inbox-title"
       aria-hidden="true" data-announcement-inbox="{{ route('announcement-inbox') }}" data-account-id="{{ auth()->id() }}">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="announcement-inbox-title">Event announcements</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted" data-inbox-status role="status">Loading announcements…</p>
          <ul class="list-group list-group-flush" data-inbox-list></ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-inbox-clear disabled>Clear all unread</button>
        </div>
      </div>
    </div>
    <template data-inbox-item>
      <li class="list-group-item px-0">
        <a class="fw-semibold text-break" data-inbox-link></a>
        <div class="small text-muted text-break" data-inbox-event></div>
      </li>
    </template>
  </div>
  <style>
    [data-announcement-menu-badge] { color: #fff; background: #146c43; }
    [data-announcement-menu-badge][data-recent-unread] { background: #9c3f00; }
    button[data-announcement-menu-badge] { z-index: 1; min-width: 24px; min-height: 24px; }
  </style>
  @include('layouts.sections.announcement-script')
@endonce
